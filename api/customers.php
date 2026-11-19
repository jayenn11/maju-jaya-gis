<?php
/**
 * Endpoint API CRUD Master Data Pelanggan (Toko, Minimarket, Restoran)
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
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

/**
 * Menghasilkan kode pelanggan otomatis berikutnya (PLG-XXX)
 */
function generateNextCustomerCode(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT code FROM customers WHERE code LIKE 'PLG-%' ORDER BY id DESC LIMIT 50");
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 0;
    foreach ($codes as $c) {
        if (preg_match('/^PLG-(\d+)$/i', $c, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }
    if ($maxNum === 0) {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM customers');
        $maxNum = (int)$countStmt->fetchColumn();
    }
    return sprintf('PLG-%03d', $maxNum + 1);
}

if ($method === 'GET') {
    if ($action === 'next_code') {
        echo json_encode([
            'status' => 'success',
            'code'   => generateNextCustomerCode($pdo),
        ]);
        exit;
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Data pelanggan tidak ditemukan.']);
            exit;
        }

        echo json_encode(['status' => 'success', 'data' => $row]);
        exit;
    }

    // List pelanggan + live search + filter kategori + pagination
    $search   = trim((string)($_GET['search'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = max(1, min(100, (int)($_GET['per_page'] ?? 10)));
    $offset   = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(c.code LIKE :s1 OR c.name LIKE :s2 OR c.pic_name LIKE :s3 OR c.phone LIKE :s4 OR c.address LIKE :s5)';
        $like = '%' . $search . '%';
        $params['s1'] = $like;
        $params['s2'] = $like;
        $params['s3'] = $like;
        $params['s4'] = $like;
        $params['s5'] = $like;
    }

    if ($category !== '' && $category !== 'Semua' && in_array($category, ['Toko', 'Minimarket', 'Restoran'], true)) {
        $where[] = 'c.category = :category';
        $params['category'] = $category;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $sql = "
        SELECT c.*,
               (SELECT COUNT(*) FROM shipments s WHERE s.customer_id = c.id) AS shipment_count
        FROM customers c
        $whereSql
        ORDER BY c.id DESC
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

    echo json_encode([
        'status'     => 'success',
        'data'       => $rows,
        'next_code'  => generateNextCustomerCode($pdo),
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

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ID pelanggan tidak valid.']);
            exit;
        }

        // Cek apakah pelanggan memiliki riwayat pengiriman
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE customer_id = :id');
        $checkStmt->execute(['id' => $id]);
        $usedCount = (int)$checkStmt->fetchColumn();

        if ($usedCount > 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => "Pelanggan tidak dapat dihapus karena tercatat pada {$usedCount} data pengiriman.",
            ]);
            exit;
        }

        $delStmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        $delStmt->execute(['id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Data pelanggan berhasil dihapus.',
        ]);
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id       = (int)($input['id'] ?? 0);
        $code     = strtoupper(trim((string)($input['code'] ?? '')));
        $name     = trim((string)($input['name'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $picName  = trim((string)($input['pic_name'] ?? ''));
        $phone    = trim((string)($input['phone'] ?? ''));
        $address  = trim((string)($input['address'] ?? ''));
        $latRaw   = trim((string)($input['latitude'] ?? ''));
        $lngRaw   = trim((string)($input['longitude'] ?? ''));

        if ($name === '' || $category === '' || $picName === '' || $phone === '' || $address === '' || $latRaw === '' || $lngRaw === '') {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Seluruh field bertanda bintang (*) wajib diisi.']);
            exit;
        }

        if (!in_array($category, ['Toko', 'Minimarket', 'Restoran'], true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Kategori pelanggan harus Toko, Minimarket, atau Restoran.']);
            exit;
        }

        if (!preg_match('/^[0-9]{8,20}$/', $phone)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Nomor telepon wajib berupa angka saja (8 s.d. 20 digit).']);
            exit;
        }

        if (!validate_coordinates($latRaw, $lngRaw)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Koordinat Latitude (-90 s.d. 90) atau Longitude (-180 s.d. 180) tidak valid.']);
            exit;
        }

        $latitude  = round((float)$latRaw, 8);
        $longitude = round((float)$lngRaw, 8);

        if ($action === 'create') {
            if ($code === '') {
                $code = generateNextCustomerCode($pdo);
            }

            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE code = :code');
            $dupStmt->execute(['code' => $code]);
            if ((int)$dupStmt->fetchColumn() > 0) {
                $code = generateNextCustomerCode($pdo);
            }

            $stmt = $pdo->prepare('
                INSERT INTO customers (code, name, category, pic_name, phone, address, latitude, longitude)
                VALUES (:code, :name, :category, :pic_name, :phone, :address, :latitude, :longitude)
            ');
            $stmt->execute([
                'code'      => $code,
                'name'      => $name,
                'category'  => $category,
                'pic_name'  => $picName,
                'phone'     => $phone,
                'address'   => $address,
                'latitude'  => $latitude,
                'longitude' => $longitude,
            ]);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Pelanggan baru (' . $code . ' - ' . $name . ') berhasil ditambahkan.',
            ]);
            exit;
        }

        // Update
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ID pelanggan tidak valid untuk diperbarui.']);
            exit;
        }

        if ($code === '') {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Kode pelanggan tidak boleh kosong.']);
            exit;
        }

        $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE code = :code AND id != :id');
        $dupStmt->execute(['code' => $code, 'id' => $id]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Kode pelanggan sudah digunakan oleh pelanggan lain.']);
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE customers
            SET code = :code,
                name = :name,
                category = :category,
                pic_name = :pic_name,
                phone = :phone,
                address = :address,
                latitude = :latitude,
                longitude = :longitude
            WHERE id = :id
        ');
        $stmt->execute([
            'code'      => $code,
            'name'      => $name,
            'category'  => $category,
            'pic_name'  => $picName,
            'phone'     => $phone,
            'address'   => $address,
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'id'        => $id,
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Data pelanggan (' . $code . ') berhasil diperbarui.',
        ]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Metode atau aksi tidak dikenali.']);
