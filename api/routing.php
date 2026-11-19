<?php
/**
 * Backend OSRM Routing Service & API Endpoint
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 *
 * Fitur:
 * - Menghitung rute jalan raya dari Gudang Utama ke 1 atau lebih titik pelanggan (Single & Multi-Stop)
 * - Integrasi OSRM Public Routing API (https://router.project-osrm.org/route/v1/driving/)
 * - Fallback otomatis berbasis rumus Haversine (x 1.25 faktor kelokan jalan, kecepatan kota 30 km/jam)
 *   apabila layanan OSRM mengalami gangguan jaringan atau timeout.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir. Silakan login kembali.']);
    exit;
}

/**
 * Menghitung jarak garis lurus permukaan bumi (Haversine) dalam satuan kilometer (km)
 */
function calculateHaversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadiusKm = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);

    $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
    return $earthRadiusKm * $c;
}

// Ambil input dari POST (JSON/Form) maupun GET
$input = array_merge($_GET, $_POST);
$rawBody = file_get_contents('php://input');
if ($rawBody !== false && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$rawCustomerIds = $input['customer_ids'] ?? ($input['customer_id'] ?? []);
if (is_string($rawCustomerIds)) {
    $rawCustomerIds = array_filter(array_map('trim', explode(',', $rawCustomerIds)), fn($v) => $v !== '');
} elseif (is_numeric($rawCustomerIds)) {
    $rawCustomerIds = [(int)$rawCustomerIds];
}

if (!is_array($rawCustomerIds) || empty($rawCustomerIds)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Pilih minimal satu titik pelanggan tujuan untuk menghitung rute.',
    ]);
    exit;
}

// Bersihkan dan pertahankan urutan unik customer_ids
$orderedCustomerIds = [];
foreach ($rawCustomerIds as $cid) {
    $intId = (int)$cid;
    if ($intId > 0 && !in_array($intId, $orderedCustomerIds, true)) {
        $orderedCustomerIds[] = $intId;
    }
}

if (empty($orderedCustomerIds)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Daftar ID pelanggan tujuan tidak valid.',
    ]);
    exit;
}

// 1. Ambil data Gudang Utama dari tabel warehouses
$warehouseId = (int)($input['warehouse_id'] ?? 0);
if ($warehouseId > 0) {
    $whStmt = $pdo->prepare('SELECT * FROM warehouses WHERE id = :id LIMIT 1');
    $whStmt->execute(['id' => $warehouseId]);
    $warehouse = $whStmt->fetch();
} else {
    $whStmt = $pdo->query('SELECT * FROM warehouses ORDER BY id ASC LIMIT 1');
    $warehouse = $whStmt->fetch();
}

if (!$warehouse) {
    http_response_code(404);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Titik koordinat Gudang Utama belum dikonfigurasi.',
    ]);
    exit;
}

if (!validate_coordinates($warehouse['latitude'], $warehouse['longitude'])) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Koordinat Gudang Utama tidak valid.',
    ]);
    exit;
}

// 2. Ambil data pelanggan sesuai daftar ID menggunakan PDO Prepared Statement
$placeholders = implode(',', array_fill(0, count($orderedCustomerIds), '?'));
$custStmt = $pdo->prepare("SELECT id, code, name, category, pic_name, phone, address, latitude, longitude FROM customers WHERE id IN ($placeholders)");
$custStmt->execute($orderedCustomerIds);
$fetchedCustomers = $custStmt->fetchAll();

$customerMap = [];
foreach ($fetchedCustomers as $row) {
    $customerMap[(int)$row['id']] = $row;
}

$stops = [];
$stopIndex = 1;
foreach ($orderedCustomerIds as $cid) {
    if (!isset($customerMap[$cid])) {
        http_response_code(404);
        echo json_encode([
            'status'  => 'error',
            'message' => "Pelanggan dengan ID #{$cid} tidak ditemukan di database.",
        ]);
        exit;
    }
    $cust = $customerMap[$cid];
    if (!validate_coordinates($cust['latitude'], $cust['longitude'])) {
        http_response_code(422);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Koordinat pelanggan ' . $cust['name'] . ' tidak valid.',
        ]);
        exit;
    }

    $stops[] = [
        'stop_number' => $stopIndex++,
        'id'          => (int)$cust['id'],
        'code'        => $cust['code'],
        'name'        => $cust['name'],
        'category'    => $cust['category'],
        'pic_name'    => $cust['pic_name'],
        'phone'       => $cust['phone'],
        'address'     => $cust['address'],
        'latitude'    => (float)$cust['latitude'],
        'longitude'   => (float)$cust['longitude'],
    ];
}

// 3. Susun titik koordinat berurutan: Gudang -> Stop 1 -> Stop 2 -> ...
$waypoints = [
    [
        'name'      => $warehouse['name'],
        'latitude'  => (float)$warehouse['latitude'],
        'longitude' => (float)$warehouse['longitude'],
    ],
];
foreach ($stops as $stop) {
    $waypoints[] = [
        'name'          => $stop['name'],
        'customer_id'   => $stop['id'],
        'customer_code' => $stop['code'],
        'category'      => $stop['category'],
        'latitude'      => $stop['latitude'],
        'longitude'     => $stop['longitude'],
    ];
}

$coordPairs = array_map(
    fn($pt) => number_format($pt['longitude'], 6, '.', '') . ',' . number_format($pt['latitude'], 6, '.', ''),
    $waypoints
);
$osrmUrl = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coordPairs) . '?overview=full&geometries=geojson&steps=true';

$forceFallback = !empty($input['force_fallback']);
$osrmSuccess   = false;
$totalDistanceKm = 0.0;
$totalDurationMinutes = 0;
$legs = [];
$geometry = null;

if (!$forceFallback && function_exists('curl_init')) {
    $ch = curl_init($osrmUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'SIG-MajuJayaFood-Bandung/1.0 (Logistics Routing System)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr     = curl_error($ch);

    if ($rawResponse !== false && $httpCode === 200 && $curlErr === '') {
        $osrmData = json_decode($rawResponse, true);
        if (
            is_array($osrmData) &&
            ($osrmData['code'] ?? '') === 'Ok' &&
            !empty($osrmData['routes'][0])
        ) {
            $route = $osrmData['routes'][0];
            $totalDistanceKm = round(((float)($route['distance'] ?? 0)) / 1000.0, 2);
            $totalDurationMinutes = max(1, (int)ceil(((float)($route['duration'] ?? 0)) / 60.0));
            $geometry = $route['geometry'] ?? null;

            $routeLegs = $route['legs'] ?? [];
            foreach ($stops as $idx => $stop) {
                $legData = $routeLegs[$idx] ?? null;
                $legDistKm = $legData ? round(((float)($legData['distance'] ?? 0)) / 1000.0, 2) : 0.0;
                $legDurMin = $legData ? max(1, (int)ceil(((float)($legData['duration'] ?? 0)) / 60.0)) : 1;

                // Ekstrak ringkasan jalan utama yang dilalui pada segmen ini
                $streetNames = [];
                if (!empty($legData['steps']) && is_array($legData['steps'])) {
                    foreach ($legData['steps'] as $step) {
                        $roadName = trim((string)($step['name'] ?? ''));
                        if ($roadName !== '' && !in_array($roadName, $streetNames, true) && count($streetNames) < 4) {
                            $streetNames[] = $roadName;
                        }
                    }
                }

                $fromPoint = $waypoints[$idx];
                $legs[] = [
                    'leg_number'       => $idx + 1,
                    'from_name'        => $fromPoint['name'],
                    'from_lat'         => $fromPoint['latitude'],
                    'from_lng'         => $fromPoint['longitude'],
                    'to_customer_id'   => $stop['id'],
                    'to_customer_code' => $stop['code'],
                    'to_name'          => $stop['name'],
                    'to_category'      => $stop['category'],
                    'to_address'       => $stop['address'],
                    'to_lat'           => $stop['latitude'],
                    'to_lng'           => $stop['longitude'],
                    'distance_km'      => $legDistKm,
                    'duration_minutes' => $legDurMin,
                    'via_roads'        => !empty($streetNames) ? implode(' → ', $streetNames) : 'Jalur Jalan Raya Kota Bandung',
                ];
            }

            if (is_array($geometry) && !empty($geometry['coordinates'])) {
                $osrmSuccess = true;
            }
        }
    }
}

// 4. Mekanisme Fallback Haversine (Sesuai Standar AGENTS.md)
if (!$osrmSuccess) {
    $windingFactor = 1.25; // Faktor kelokan jalan perkotaan
    $urbanSpeedKmh = 30.0; // Kecepatan rata-rata armada dalam kota Bandung (30 km/jam)

    $totalDistanceKm = 0.0;
    $totalDurationMinutes = 0;
    $legs = [];
    $lineCoords = [
        [(float)$warehouse['longitude'], (float)$warehouse['latitude']],
    ];

    foreach ($stops as $idx => $stop) {
        $fromPoint = $waypoints[$idx];
        $straightKm = calculateHaversineKm(
            $fromPoint['latitude'],
            $fromPoint['longitude'],
            $stop['latitude'],
            $stop['longitude']
        );
        $estimatedRoadKm = max(0.10, round($straightKm * $windingFactor, 2));
        $estimatedMinutes = max(1, (int)ceil(($estimatedRoadKm / $urbanSpeedKmh) * 60.0));

        $totalDistanceKm += $estimatedRoadKm;
        $totalDurationMinutes += $estimatedMinutes;
        $lineCoords[] = [$stop['longitude'], $stop['latitude']];

        $legs[] = [
            'leg_number'       => $idx + 1,
            'from_name'        => $fromPoint['name'],
            'from_lat'         => $fromPoint['latitude'],
            'from_lng'         => $fromPoint['longitude'],
            'to_customer_id'   => $stop['id'],
            'to_customer_code' => $stop['code'],
            'to_name'          => $stop['name'],
            'to_category'      => $stop['category'],
            'to_address'       => $stop['address'],
            'to_lat'           => $stop['latitude'],
            'to_lng'           => $stop['longitude'],
            'distance_km'      => $estimatedRoadKm,
            'duration_minutes' => $estimatedMinutes,
            'via_roads'        => 'Estimasi Haversine × 1.25 (Kecepatan 30 km/jam)',
        ];
    }

    $totalDistanceKm = round($totalDistanceKm, 2);
    $geometry = [
        'type'        => 'LineString',
        'coordinates' => $lineCoords,
    ];
}

echo json_encode([
    'status'                 => 'success',
    'source'                 => $osrmSuccess ? 'osrm' : 'fallback',
    'message'                => $osrmSuccess
        ? 'Rute jalan raya berhasil dihitung melalui OSRM Routing API.'
        : 'Layanan OSRM sedang tidak terjangkau. Menampilkan estimasi cadangan berbasis Haversine (×1.25 faktor jalan, 30 km/jam).',
    'warehouse'              => [
        'id'        => (int)$warehouse['id'],
        'name'      => $warehouse['name'],
        'address'   => $warehouse['address'],
        'latitude'  => (float)$warehouse['latitude'],
        'longitude' => (float)$warehouse['longitude'],
    ],
    'stops'                  => $stops,
    'total_distance_km'      => $totalDistanceKm,
    'total_duration_minutes' => $totalDurationMinutes,
    'legs'                   => $legs,
    'geometry'               => $geometry,
]);
