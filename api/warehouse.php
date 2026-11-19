<?php
/**
 * Endpoint API Pengaturan Lokasi Gudang Utama (Titik Awal Distribusi)
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

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM warehouses ORDER BY id ASC LIMIT 1');
    $warehouse = $stmt->fetch();

    if (!$warehouse) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Data gudang belum tersedia.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data'   => $warehouse,
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

    $id      = (int)($input['id'] ?? 1);
    $name    = trim((string)($input['name'] ?? ''));
    $address = trim((string)($input['address'] ?? ''));
    $latRaw  = trim((string)($input['latitude'] ?? ''));
    $lngRaw  = trim((string)($input['longitude'] ?? ''));

    if ($name === '' || $address === '' || $latRaw === '' || $lngRaw === '') {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Nama gudang, alamat lengkap, dan titik koordinat wajib diisi.']);
        exit;
    }

    if (!validate_coordinates($latRaw, $lngRaw)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Koordinat Latitude (-90 s.d. 90) atau Longitude (-180 s.d. 180) tidak valid.']);
        exit;
    }

    $latitude  = round((float)$latRaw, 8);
    $longitude = round((float)$lngRaw, 8);

    // Cek apakah baris gudang sudah ada
    $checkStmt = $pdo->prepare('SELECT id FROM warehouses WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $id]);
    $exists = $checkStmt->fetch();

    if ($exists) {
        $stmt = $pdo->prepare('
            UPDATE warehouses
            SET name = :name,
                address = :address,
                latitude = :latitude,
                longitude = :longitude,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'name'      => $name,
            'address'   => $address,
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'id'        => $id,
        ]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO warehouses (id, name, address, latitude, longitude)
            VALUES (:id, :name, :address, :latitude, :longitude)
        ');
        $stmt->execute([
            'id'        => $id ?: 1,
            'name'      => $name,
            'address'   => $address,
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ]);
    }

    $updatedStmt = $pdo->prepare('SELECT * FROM warehouses WHERE id = :id LIMIT 1');
    $updatedStmt->execute(['id' => $id ?: 1]);
    $updatedWarehouse = $updatedStmt->fetch();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Pengaturan lokasi Gudang Utama berhasil diperbarui.',
        'data'    => $updatedWarehouse,
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Metode request tidak didukung.']);
