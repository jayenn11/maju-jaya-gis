<?php
/**
 * Endpoint API Rekapitulasi Laporan Pengiriman & Ekspor CSV
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir. Silakan login kembali.']);
    exit;
}

$action     = trim((string)($_GET['action'] ?? 'list'));
$startDate  = trim((string)($_GET['start_date'] ?? ''));
$endDate    = trim((string)($_GET['end_date'] ?? ''));
$status     = trim((string)($_GET['status'] ?? 'Semua'));
$customerId = (int)($_GET['customer_id'] ?? 0);
$search     = trim((string)($_GET['search'] ?? ''));

$validStatuses = ['Menunggu', 'Dalam pengiriman', 'Selesai dikirim'];

$where  = [];
$params = [];

if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $where[] = 's.shipment_date >= :start_date';
    $params['start_date'] = $startDate;
}

if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $where[] = 's.shipment_date <= :end_date';
    $params['end_date'] = $endDate;
}

if ($status !== '' && $status !== 'Semua' && in_array($status, $validStatuses, true)) {
    $where[] = 's.status = :status';
    $params['status'] = $status;
}

if ($customerId > 0) {
    $where[] = 's.customer_id = :customer_id';
    $params['customer_id'] = $customerId;
}

if ($search !== '') {
    $where[] = '(s.shipment_code LIKE :s1 OR c.name LIKE :s2 OR p.name LIKE :s3 OR s.driver_name LIKE :s4 OR s.vehicle_type LIKE :s5 OR c.address LIKE :s6)';
    $like = '%' . $search . '%';
    $params['s1'] = $like;
    $params['s2'] = $like;
    $params['s3'] = $like;
    $params['s4'] = $like;
    $params['s5'] = $like;
    $params['s6'] = $like;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT s.*,
           w.name AS warehouse_name,
           c.code AS customer_code,
           c.name AS customer_name,
           c.category AS customer_category,
           c.address AS customer_address,
           p.code AS product_code,
           p.name AS product_name,
           p.category AS product_category,
           p.unit AS product_unit,
           p.weight_per_unit AS product_weight_per_unit
    FROM shipments s
    INNER JOIN warehouses w ON s.warehouse_id = w.id
    INNER JOIN customers c ON s.customer_id = c.id
    INNER JOIN products p ON s.product_id = p.id
    $whereSql
    ORDER BY s.shipment_date DESC, s.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Hitung 4 Metrik Ringkasan Rekapitulasi
$totalShipments     = count($rows);
$completedShipments = 0;
$inProgressCount    = 0;
$waitingCount       = 0;
$totalDistanceKm    = 0.0;
$totalWeightKg      = 0.0;
$totalDurationMin   = 0;

foreach ($rows as $r) {
    if ($r['status'] === 'Selesai dikirim') {
        $completedShipments++;
    } elseif ($r['status'] === 'Dalam pengiriman') {
        $inProgressCount++;
    } else {
        $waitingCount++;
    }
    $totalDistanceKm  += (float)$r['distance_km'];
    $totalWeightKg    += (float)$r['total_weight'];
    $totalDurationMin += (int)$r['duration_minutes'];
}

$avgDurationMinutes = $totalShipments > 0 ? round($totalDurationMin / $totalShipments, 1) : 0.0;

// ============================================================================
// EKSPOR CSV UNTUK EXCEL / GOOGLE SHEETS
// ============================================================================
if ($action === 'export_csv') {
    $filename = 'Laporan_Pengiriman_PT_Maju_Jaya_Food_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Tambahkan UTF-8 BOM agar karakter langsung rapi di Microsoft Excel
    fwrite($out, "\xEF\xBB\xBF");

    // Baris Judul Laporan
    fputcsv($out, ['PT MAJU JAYA FOOD - LAPORAN DISTRIBUSI PENGIRIMAN BARANG (BANDUNG)']);
    $periodText = ($startDate ?: 'Awal') . ' s/d ' . ($endDate ?: 'Sekarang');
    fputcsv($out, ['Periode Filter', $periodText, 'Status Filter', $status ?: 'Semua', 'Tanggal Unduh', date('d/m/Y H:i')]);
    fputcsv($out, []);

    // Header Kolom Tabel
    fputcsv($out, [
        'No',
        'Kode Pengiriman',
        'Tanggal Pengiriman',
        'Kode Pelanggan',
        'Nama Pelanggan',
        'Kategori Pelanggan',
        'Alamat Tujuan',
        'Kode Barang',
        'Nama Barang',
        'Kuantitas',
        'Satuan',
        'Total Berat (kg)',
        'Jenis Kendaraan',
        'Nama Sopir',
        'Jarak Tempuh (km)',
        'Estimasi Waktu (menit)',
        'Status Pengiriman',
        'Catatan',
    ]);

    foreach ($rows as $idx => $r) {
        fputcsv($out, [
            $idx + 1,
            $r['shipment_code'],
            $r['shipment_date'],
            $r['customer_code'],
            $r['customer_name'],
            $r['customer_category'],
            $r['customer_address'],
            $r['product_code'],
            $r['product_name'],
            (int)$r['quantity'],
            $r['product_unit'],
            number_format((float)$r['total_weight'], 2, '.', ''),
            $r['vehicle_type'],
            $r['driver_name'],
            number_format((float)$r['distance_km'], 2, '.', ''),
            (int)$r['duration_minutes'],
            $r['status'],
            (string)($r['notes'] ?? ''),
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, [
        '',
        'TOTAL REKAPITULASI',
        "Total: {$totalShipments} Pengiriman ({$completedShipments} Selesai)",
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        'Total Berat (kg):',
        number_format($totalWeightKg, 2, '.', ''),
        '',
        'Total Jarak (km):',
        number_format($totalDistanceKm, 2, '.', ''),
        'Rata-rata Waktu (mnt): ' . $avgDurationMinutes,
        '',
        '',
    ]);

    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'  => 'success',
    'summary' => [
        'total_shipments'      => $totalShipments,
        'completed_shipments'  => $completedShipments,
        'in_progress_count'    => $inProgressCount,
        'waiting_count'        => $waitingCount,
        'total_distance_km'    => round($totalDistanceKm, 2),
        'total_weight_kg'      => round($totalWeightKg, 2),
        'avg_duration_minutes' => $avgDurationMinutes,
    ],
    'data'    => $rows,
]);
