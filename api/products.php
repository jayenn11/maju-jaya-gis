<?php
/**
 * Endpoint API CRUD Master Data Barang (Katalog Makanan Distribusi)
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
 * Menghasilkan kode barang otomatis berikutnya (BRG-XXX)
 */
function generateNextProductCode(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT code FROM products WHERE code LIKE 'BRG-%' ORDER BY id DESC LIMIT 50");
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 0;
    foreach ($codes as $c) {
        if (preg_match('/^BRG-(\d+)$/i', $c, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }
    if ($maxNum === 0) {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM products');
        $maxNum = (int)$countStmt->fetchColumn();
    }
    return sprintf('BRG-%03d', $maxNum + 1);
}

$validCategories = ['Makanan Kemasan', 'Bahan Pokok', 'Minuman', 'Frozen Food'];
$validUnits      = ['Kardus', 'Bal', 'Pack', 'Box'];

if ($method === 'GET') {
    if ($action === 'next_code') {
        echo json_encode([
            'status' => 'success',
            'code'   => generateNextProductCode($pdo),
        ]);
        exit;
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Data barang tidak ditemukan.']);
            exit;
        }

        echo json_encode(['status' => 'success', 'data' => $row]);
        exit;
    }

    // List barang + live search + filter kategori + pagination
    $search   = trim((string)($_GET['search'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = max(1, min(100, (int)($_GET['per_page'] ?? 10)));
    $offset   = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.code LIKE :s1 OR p.name LIKE :s2 OR p.category LIKE :s3 OR p.unit LIKE :s4)';
        $like = '%' . $search . '%';
        $params['s1'] = $like;
        $params['s2'] = $like;
        $params['s3'] = $like;
        $params['s4'] = $like;
    }

    if ($category !== '' && $category !== 'Semua' && in_array($category, $validCategories, true)) {
        $where[] = 'p.category = :category';
        $params['category'] = $category;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $sql = "
        SELECT p.*,
               (SELECT COUNT(*) FROM shipments s WHERE s.product_id = p.id) AS shipment_count
        FROM products p
        $whereSql
        ORDER BY p.id DESC
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
        'next_code'  => generateNextProductCode($pdo),
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
            echo json_encode(['status' => 'error', 'message' => 'ID barang tidak valid.']);
            exit;
        }

        // Proteksi hapus jika barang sudah digunakan pada pengiriman
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM shipments WHERE product_id = :id');
        $checkStmt->execute(['id' => $id]);
        $usedCount = (int)$checkStmt->fetchColumn();

        if ($usedCount > 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => "Barang tidak dapat dihapus karena sudah digunakan pada {$usedCount} transaksi pengiriman.",
            ]);
            exit;
        }

        $delStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $delStmt->execute(['id' => $id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Data barang berhasil dihapus dari katalog.',
        ]);
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id        = (int)($input['id'] ?? 0);
        $code      = strtoupper(trim((string)($input['code'] ?? '')));
        $name      = trim((string)($input['name'] ?? ''));
        $category  = trim((string)($input['category'] ?? ''));
        $unit      = trim((string)($input['unit'] ?? ''));
        $weightRaw = trim((string)($input['weight_per_unit'] ?? ''));
        $stockRaw  = trim((string)($input['stock'] ?? ''));

        if ($name === '' || $category === '' || $unit === '' || $weightRaw === '' || $stockRaw === '') {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Seluruh field bertanda bintang (*) wajib diisi.']);
            exit;
        }

        if (!in_array($category, $validCategories, true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Kategori barang tidak valid.']);
            exit;
        }

        if (!in_array($unit, $validUnits, true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Satuan barang harus Kardus, Bal, Pack, atau Box.']);
            exit;
        }

        if (!is_numeric($weightRaw) || (float)$weightRaw <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Berat per satuan (kg) harus berupa angka lebih dari 0.']);
            exit;
        }

        if (!preg_match('/^\d+$/', $stockRaw) || (int)$stockRaw < 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Stok barang harus berupa bilangan bulat positif atau 0.']);
            exit;
        }

        $weight = round((float)$weightRaw, 2);
        $stock  = (int)$stockRaw;

        if ($action === 'create') {
            if ($code === '') {
                $code = generateNextProductCode($pdo);
            }

            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE code = :code');
            $dupStmt->execute(['code' => $code]);
            if ((int)$dupStmt->fetchColumn() > 0) {
                $code = generateNextProductCode($pdo);
            }

            $stmt = $pdo->prepare('
                INSERT INTO products (code, name, category, unit, weight_per_unit, stock)
                VALUES (:code, :name, :category, :unit, :weight_per_unit, :stock)
            ');
            $stmt->execute([
                'code'            => $code,
                'name'            => $name,
                'category'        => $category,
                'unit'            => $unit,
                'weight_per_unit' => $weight,
                'stock'           => $stock,
            ]);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Barang baru (' . $code . ' - ' . $name . ') berhasil ditambahkan.',
            ]);
            exit;
        }

        // Update
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ID barang tidak valid untuk diperbarui.']);
            exit;
        }

        $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE code = :code AND id != :id');
        $dupStmt->execute(['code' => $code, 'id' => $id]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Kode barang sudah digunakan oleh produk lain.']);
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE products
            SET code = :code,
                name = :name,
                category = :category,
                unit = :unit,
                weight_per_unit = :weight_per_unit,
                stock = :stock
            WHERE id = :id
        ');
        $stmt->execute([
            'code'            => $code,
            'name'            => $name,
            'category'        => $category,
            'unit'            => $unit,
            'weight_per_unit' => $weight,
            'stock'           => $stock,
            'id'              => $id,
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Data barang (' . $code . ') berhasil diperbarui.',
        ]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Metode atau aksi tidak dikenali.']);
