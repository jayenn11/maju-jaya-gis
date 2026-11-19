<?php
/**
 * Endpoint API CRUD Manajemen & Monitoring Pengiriman
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 *
 * Fitur:
 * - List pengiriman + Live Search + Filter Status + Light Pagination
 * - Auto-generate kode pengiriman berurutan (KRM007, dst.)
 * - Transaksi database (PDO::beginTransaction):
 *   * Simpan pengiriman baru & kurangi stok produk secara atomik
 *   * Edit pengiriman & sesuaikan selisih stok produk
 *   * Hapus pengiriman: kembalikan stok ke tabel products jika status masih 'Menunggu'
 * - Perubahan status pengiriman ('Menunggu' -> 'Dalam pengiriman' -> 'Selesai dikirim')
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir. Silakan login kembali.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$validVehicles = ['Mobil Boks', 'Pickup Boks', 'Van Pendingin', 'Motor Box'];
$validStatuses = ['Menunggu', 'Dalam pengiriman', 'Selesai dikirim'];

/**
 * Menghasilkan kode pengiriman otomatis berikutnya (KRM001, KRM002, ...)
 */
function generateNextShipmentCode(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT shipment_code FROM shipments WHERE shipment_code LIKE 'KRM%' ORDER BY id DESC LIMIT 50");
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 0;
    foreach ($codes as $c) {
        if (preg_match('/^KRM-?(\d+)$/i', $c, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }
    if ($maxNum === 0) {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM shipments');
        $maxNum = (int)$countStmt->fetchColumn();
    }
    return sprintf('KRM%03d', $maxNum + 1);
}

if ($method === 'GET') {
    if ($action === 'next_code') {
        echo json_encode([
            'status' => 'success',
            'code'   => generateNextShipmentCode($pdo),
        ]);
        exit;
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('
            SELECT s.*,
                   w.name AS warehouse_name,
                   w.address AS warehouse_address,
                   w.latitude AS warehouse_lat,
                   w.longitude AS warehouse_lng,
                   c.code AS customer_code,
                   c.name AS customer_name,
                   c.category AS customer_category,
                   c.pic_name AS customer_pic,
                   c.phone AS customer_phone,
                   c.address AS customer_address,
                   c.latitude AS customer_lat,
                   c.longitude AS customer_lng,
                   p.code AS product_code,
                   p.name AS product_name,
                   p.category AS product_category,
                   p.unit AS product_unit,
                   p.weight_per_unit AS product_weight_per_unit,
                   p.stock AS product_current_stock
            FROM shipments s
            INNER JOIN warehouses w ON s.warehouse_id = w.id
            INNER JOIN customers c ON s.customer_id = c.id
            INNER JOIN products p ON s.product_id = p.id
            WHERE s.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Data pengiriman tidak ditemukan.']);
            exit;
        }

        echo json_encode(['status' => 'success', 'data' => $row]);
        exit;
    }

    // List pengiriman + Live Search + Auto-Filter Status + Pagination
    $search  = trim((string)($_GET['search'] ?? ''));
    $status  = trim((string)($_GET['status'] ?? ''));
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 10)));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(s.shipment_code LIKE :s1 OR c.name LIKE :s2 OR c.code LIKE :s3 OR p.name LIKE :s4 OR s.driver_name LIKE :s5 OR s.vehicle_type LIKE :s6 OR c.address LIKE :s7 OR COALESCE(s.notes, "") LIKE :s8)';
        $like = '%' . $search . '%';
        $params['s1'] = $like;
        $params['s2'] = $like;
        $params['s3'] = $like;
        $params['s4'] = $like;
        $params['s5'] = $like;
        $params['s6'] = $like;
        $params['s7'] = $like;
        $params['s8'] = $like;
    }

    if ($status !== '' && $status !== 'Semua' && in_array($status, $validStatuses, true)) {
        $where[] = 's.status = :status';
        $params['status'] = $status;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countSql = "
        SELECT COUNT(*)
        FROM shipments s
        INNER JOIN customers c ON s.customer_id = c.id
        INNER JOIN products p ON s.product_id = p.id
        $whereSql
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $sql = "
        SELECT s.*,
               c.code AS customer_code,
               c.name AS customer_name,
               c.category AS customer_category,
               c.address AS customer_address,
               p.code AS product_code,
               p.name AS product_name,
               p.unit AS product_unit,
               p.weight_per_unit AS product_weight_per_unit
        FROM shipments s
        INNER JOIN customers c ON s.customer_id = c.id
        INNER JOIN products p ON s.product_id = p.id
        $whereSql
        ORDER BY s.shipment_date DESC, s.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Ambil juga katalog stok produk terkini agar form modal selalu sinkron
    $prodStmt = $pdo->query('SELECT id, code, name, category, unit, weight_per_unit, stock FROM products ORDER BY code ASC');
    $productsList = $prodStmt->fetchAll();

    echo json_encode([
        'status'     => 'success',
        'data'       => $rows,
        'next_code'  => generateNextShipmentCode($pdo),
        'products'   => $productsList,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ],
    ]);
    exit;
}

if ($method === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $action = $input['action'] ?? $action;

    // 1. Ubah Status Pengiriman ('Menunggu' -> 'Dalam pengiriman' -> 'Selesai dikirim')
    if ($action === 'update_status') {
        $id        = (int)($input['id'] ?? 0);
        $newStatus = trim((string)($input['status'] ?? ''));

        if ($id <= 0 || !in_array($newStatus, $validStatuses, true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ID pengiriman atau status tujuan tidak valid.']);
            exit;
        }

        $checkStmt = $pdo->prepare('SELECT id, shipment_code, status FROM shipments WHERE id = :id LIMIT 1');
        $checkStmt->execute(['id' => $id]);
        $shipment = $checkStmt->fetch();

        if (!$shipment) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Data pengiriman tidak ditemukan.']);
            exit;
        }

        $updStmt = $pdo->prepare('UPDATE shipments SET status = :status WHERE id = :id');
        $updStmt->execute(['status' => $newStatus, 'id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => "Status pengiriman {$shipment['shipment_code']} berhasil diperbarui menjadi \"{$newStatus}\".",
        ]);
        exit;
    }

    // 2. Hapus Pengiriman (Kembalikan stok ke products jika status masih 'Menunggu')
    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ID pengiriman tidak valid.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM shipments WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $shipment = $stmt->fetch();

            if (!$shipment) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Data pengiriman tidak ditemukan.']);
                exit;
            }

            $restoredMsg = '';
            if ($shipment['status'] === 'Menunggu') {
                $restoreStmt = $pdo->prepare('UPDATE products SET stock = stock + :qty WHERE id = :pid');
                $restoreStmt->execute([
                    'qty' => (int)$shipment['quantity'],
                    'pid' => (int)$shipment['product_id'],
                ]);
                $restoredMsg = ' Stok barang (' . (int)$shipment['quantity'] . ' unit) telah dikembalikan ke gudang.';
            }

            $delStmt = $pdo->prepare('DELETE FROM shipments WHERE id = :id');
            $delStmt->execute(['id' => $id]);

            $pdo->commit();

            echo json_encode([
                'status'  => 'success',
                'message' => 'Data pengiriman ' . $shipment['shipment_code'] . ' berhasil dihapus.' . $restoredMsg,
            ]);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus pengiriman: ' . $e->getMessage()]);
            exit;
        }
    }

    // 3. Tambah (create) atau Edit (update) Pengiriman
    if ($action === 'create' || $action === 'update') {
        $id              = (int)($input['id'] ?? 0);
        $shipmentCode    = strtoupper(trim((string)($input['shipment_code'] ?? '')));
        $customerId      = (int)($input['customer_id'] ?? 0);
        $productId       = (int)($input['product_id'] ?? 0);
        $quantityRaw     = trim((string)($input['quantity'] ?? ''));
        $vehicleType     = trim((string)($input['vehicle_type'] ?? ''));
        $driverName      = trim((string)($input['driver_name'] ?? ''));
        $distanceRaw     = trim((string)($input['distance_km'] ?? ''));
        $durationRaw     = trim((string)($input['duration_minutes'] ?? ''));
        $routeGeometry   = isset($input['route_geometry']) && trim((string)$input['route_geometry']) !== '' ? trim((string)$input['route_geometry']) : null;
        $statusVal       = trim((string)($input['status'] ?? 'Menunggu'));
        $shipmentDate    = trim((string)($input['shipment_date'] ?? date('Y-m-d')));
        $notes           = isset($input['notes']) ? trim((string)$input['notes']) : null;

        if ($customerId <= 0 || $productId <= 0 || $quantityRaw === '' || $vehicleType === '' || $driverName === '' || $distanceRaw === '' || $durationRaw === '' || $shipmentDate === '') {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Seluruh field bertanda bintang (*) wajib diisi lengkap.']);
            exit;
        }

        if (!preg_match('/^\d+$/', $quantityRaw) || (int)$quantityRaw <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Jumlah muatan (quantity) harus berupa angka bulat lebih dari 0.']);
            exit;
        }
        $quantity = (int)$quantityRaw;

        if (!in_array($vehicleType, $validVehicles, true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Jenis kendaraan harus salah satu dari: ' . implode(', ', $validVehicles) . '.']);
            exit;
        }

        if (!in_array($statusVal, $validStatuses, true)) {
            $statusVal = 'Menunggu';
        }

        if (!is_numeric($distanceRaw) || (float)$distanceRaw <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Jarak tempuh (km) harus berupa angka lebih dari 0.']);
            exit;
        }
        $distanceKm = round((float)$distanceRaw, 2);

        if (!is_numeric($durationRaw) || (int)$durationRaw <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Estimasi waktu tempuh (menit) harus lebih dari 0.']);
            exit;
        }
        $durationMinutes = max(1, (int)ceil((float)$durationRaw));

        // Ambil Gudang Utama aktif
        $whStmt = $pdo->query('SELECT id, latitude, longitude FROM warehouses ORDER BY id ASC LIMIT 1');
        $warehouse = $whStmt->fetch();
        $warehouseId = (int)($warehouse['id'] ?? 1);

        // Pastikan pelanggan valid
        $custStmt = $pdo->prepare('SELECT id, name, latitude, longitude FROM customers WHERE id = :id LIMIT 1');
        $custStmt->execute(['id' => $customerId]);
        $customer = $custStmt->fetch();
        if (!$customer) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Pelanggan tujuan tidak ditemukan.']);
            exit;
        }

        // Jika route_geometry kosong, bangun garis dasar Gudang -> Pelanggan
        if ($routeGeometry === null && $warehouse) {
            $routeGeometry = json_encode([
                'type'        => 'LineString',
                'coordinates' => [
                    [(float)$warehouse['longitude'], (float)$warehouse['latitude']],
                    [(float)$customer['longitude'], (float)$customer['latitude']],
                ],
            ]);
        }

        try {
            $pdo->beginTransaction();

            if ($action === 'create') {
                // Kunci baris produk untuk validasi & pengurangan stok atomik
                $prodStmt = $pdo->prepare('SELECT id, code, name, unit, weight_per_unit, stock FROM products WHERE id = :id FOR UPDATE');
                $prodStmt->execute(['id' => $productId]);
                $product = $prodStmt->fetch();

                if (!$product) {
                    $pdo->rollBack();
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Barang yang dipilih tidak ditemukan.']);
                    exit;
                }

                $availableStock = (int)$product['stock'];
                if ($quantity > $availableStock) {
                    $pdo->rollBack();
                    http_response_code(422);
                    echo json_encode([
                        'status'  => 'error',
                        'message' => "Stok \"{$product['name']}\" tidak mencukupi (Tersedia: {$availableStock} {$product['unit']}, Diminta: {$quantity} {$product['unit']}).",
                    ]);
                    exit;
                }

                $totalWeight = round($quantity * (float)$product['weight_per_unit'], 2);

                if ($shipmentCode === '') {
                    $shipmentCode = generateNextShipmentCode($pdo);
                } else {
                    $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE shipment_code = :code');
                    $dupStmt->execute(['code' => $shipmentCode]);
                    if ((int)$dupStmt->fetchColumn() > 0) {
                        $shipmentCode = generateNextShipmentCode($pdo);
                    }
                }

                $insertStmt = $pdo->prepare('
                    INSERT INTO shipments (
                        shipment_code, warehouse_id, customer_id, product_id,
                        quantity, total_weight, vehicle_type, driver_name,
                        distance_km, duration_minutes, route_geometry,
                        status, shipment_date, notes
                    ) VALUES (
                        :shipment_code, :warehouse_id, :customer_id, :product_id,
                        :quantity, :total_weight, :vehicle_type, :driver_name,
                        :distance_km, :duration_minutes, :route_geometry,
                        :status, :shipment_date, :notes
                    )
                ');
                $insertStmt->execute([
                    'shipment_code'    => $shipmentCode,
                    'warehouse_id'     => $warehouseId,
                    'customer_id'      => $customerId,
                    'product_id'       => $productId,
                    'quantity'         => $quantity,
                    'total_weight'     => $totalWeight,
                    'vehicle_type'     => $vehicleType,
                    'driver_name'      => $driverName,
                    'distance_km'      => $distanceKm,
                    'duration_minutes' => $durationMinutes,
                    'route_geometry'   => $routeGeometry,
                    'status'           => $statusVal,
                    'shipment_date'    => $shipmentDate,
                    'notes'            => $notes,
                ]);

                // Kurangi stok barang
                $deductStmt = $pdo->prepare('UPDATE products SET stock = stock - :qty WHERE id = :id');
                $deductStmt->execute([
                    'qty' => $quantity,
                    'id'  => $productId,
                ]);

                $pdo->commit();

                echo json_encode([
                    'status'  => 'success',
                    'message' => "Jadwal pengiriman {$shipmentCode} ke {$customer['name']} berhasil dibuat dan stok barang telah diperbarui.",
                ]);
                exit;
            }

            // Action === 'update'
            if ($id <= 0) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'ID pengiriman tidak valid untuk diperbarui.']);
                exit;
            }

            $oldStmt = $pdo->prepare('SELECT * FROM shipments WHERE id = :id FOR UPDATE');
            $oldStmt->execute(['id' => $id]);
            $oldShipment = $oldStmt->fetch();

            if (!$oldShipment) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Data pengiriman tidak ditemukan.']);
                exit;
            }

            // Kembalikan stok lama terlebih dahulu
            $restoreOldStmt = $pdo->prepare('UPDATE products SET stock = stock + :old_qty WHERE id = :old_pid');
            $restoreOldStmt->execute([
                'old_qty' => (int)$oldShipment['quantity'],
                'old_pid' => (int)$oldShipment['product_id'],
            ]);

            // Cek dan kurangi stok produk baru
            $prodStmt = $pdo->prepare('SELECT id, code, name, unit, weight_per_unit, stock FROM products WHERE id = :id FOR UPDATE');
            $prodStmt->execute(['id' => $productId]);
            $product = $prodStmt->fetch();

            if (!$product) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Barang yang dipilih tidak ditemukan.']);
                exit;
            }

            $availableStock = (int)$product['stock'];
            if ($quantity > $availableStock) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode([
                    'status'  => 'error',
                    'message' => "Stok \"{$product['name']}\" tidak mencukupi (Tersedia: {$availableStock} {$product['unit']}, Diminta: {$quantity} {$product['unit']}).",
                ]);
                exit;
            }

            $deductNewStmt = $pdo->prepare('UPDATE products SET stock = stock - :new_qty WHERE id = :new_pid');
            $deductNewStmt->execute([
                'new_qty' => $quantity,
                'new_pid' => $productId,
            ]);

            $totalWeight = round($quantity * (float)$product['weight_per_unit'], 2);
            if ($shipmentCode === '') {
                $shipmentCode = $oldShipment['shipment_code'];
            }

            $updStmt = $pdo->prepare('
                UPDATE shipments
                SET shipment_code = :shipment_code,
                    customer_id = :customer_id,
                    product_id = :product_id,
                    quantity = :quantity,
                    total_weight = :total_weight,
                    vehicle_type = :vehicle_type,
                    driver_name = :driver_name,
                    distance_km = :distance_km,
                    duration_minutes = :duration_minutes,
                    route_geometry = COALESCE(:route_geometry, route_geometry),
                    status = :status,
                    shipment_date = :shipment_date,
                    notes = :notes
                WHERE id = :id
            ');
            $updStmt->execute([
                'shipment_code'    => $shipmentCode,
                'customer_id'      => $customerId,
                'product_id'       => $productId,
                'quantity'         => $quantity,
                'total_weight'     => $totalWeight,
                'vehicle_type'     => $vehicleType,
                'driver_name'      => $driverName,
                'distance_km'      => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'route_geometry'   => $routeGeometry,
                'status'           => $statusVal,
                'shipment_date'    => $shipmentDate,
                'notes'            => $notes,
                'id'               => $id,
            ]);

            $pdo->commit();

            echo json_encode([
                'status'  => 'success',
                'message' => "Data pengiriman {$shipmentCode} berhasil diperbarui.",
            ]);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan transaksi: ' . $e->getMessage()]);
            exit;
        }
    }
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Metode atau aksi tidak dikenali.']);
