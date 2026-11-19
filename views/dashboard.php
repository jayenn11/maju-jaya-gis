<?php
/**
 * Halaman Dashboard Monitoring Distribusi
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

// 1. Statistik Pelanggan
$custTotal = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$custByCatStmt = $pdo->query('SELECT category, COUNT(*) AS total FROM customers GROUP BY category');
$custByCat = ['Toko' => 0, 'Minimarket' => 0, 'Restoran' => 0];
foreach ($custByCatStmt->fetchAll() as $row) {
    $custByCat[$row['category']] = (int)$row['total'];
}

// 2. Statistik Barang & Stok
$prodStats = $pdo->query('SELECT COUNT(*) AS total_items, COALESCE(SUM(stock), 0) AS total_stock FROM products')->fetch();
$totalProducts = (int)($prodStats['total_items'] ?? 0);
$totalStock    = (int)($prodStats['total_stock'] ?? 0);

// 3. Statistik Pengiriman
$shipStatsStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM shipments GROUP BY status');
$shipStats = ['Menunggu' => 0, 'Dalam pengiriman' => 0, 'Selesai dikirim' => 0];
foreach ($shipStatsStmt->fetchAll() as $row) {
    $shipStats[$row['status']] = (int)$row['total'];
}
$activeShipments    = $shipStats['Menunggu'] + $shipStats['Dalam pengiriman'];
$completedShipments = $shipStats['Selesai dikirim'];

// 4. Data Titik Pelanggan untuk Mini Peta Sebaran Wilayah Bandung
$allCustomers = $pdo->query('SELECT id, code, name, category, pic_name, phone, address, latitude, longitude FROM customers ORDER BY id ASC')->fetchAll();

// 5. Data 5 Pengiriman Terbaru
$latestShipmentsStmt = $pdo->query('
    SELECT s.*,
           c.code AS customer_code,
           c.name AS customer_name,
           c.category AS customer_category,
           p.code AS product_code,
           p.name AS product_name,
           p.unit AS product_unit
    FROM shipments s
    INNER JOIN customers c ON s.customer_id = c.id
    INNER JOIN products p ON s.product_id = p.id
    ORDER BY s.shipment_date DESC, s.id DESC
    LIMIT 5
');
$latestShipments = $latestShipmentsStmt->fetchAll();
?>

<!-- Baris 1: 4 Kartu KPI Utama -->
<div class="row g-3 mb-4">
    <!-- KPI 1: Total Titik Pelanggan -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Total Titik Pelanggan</div>
                    <div class="h3 fw-bold text-dark mb-0"><?= number_format($custTotal, 0, ',', '.') ?></div>
                </div>
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1 pt-2 border-top small">
                <span class="badge badge-soft-primary"><?= (int)$custByCat['Toko'] ?> Toko</span>
                <span class="badge badge-soft-info"><?= (int)$custByCat['Minimarket'] ?> Minimarket</span>
                <span class="badge badge-soft-purple"><?= (int)$custByCat['Restoran'] ?> Restoran</span>
            </div>
        </div>
    </div>

    <!-- KPI 2: Total Katalog Barang & Stok -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Katalog Barang &amp; Stok</div>
                    <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalProducts, 0, ',', '.') ?> <span class="fs-6 fw-medium text-secondary">Item</span></div>
                </div>
                <div class="kpi-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-boxes"></i>
                </div>
            </div>
            <div class="pt-2 border-top small text-secondary d-flex align-items-center justify-content-between">
                <span>Total Stok Tersedia:</span>
                <strong class="text-dark"><?= number_format($totalStock, 0, ',', '.') ?> Unit</strong>
            </div>
        </div>
    </div>

    <!-- KPI 3: Pengiriman Aktif -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Pengiriman Aktif</div>
                    <div class="h3 fw-bold text-dark mb-0"><?= number_format($activeShipments, 0, ',', '.') ?></div>
                </div>
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-truck"></i>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1 pt-2 border-top small">
                <span class="badge badge-soft-warning"><?= (int)$shipStats['Menunggu'] ?> Menunggu</span>
                <span class="badge badge-soft-primary"><?= (int)$shipStats['Dalam pengiriman'] ?> Dalam Pengiriman</span>
            </div>
        </div>
    </div>

    <!-- KPI 4: Pengiriman Selesai -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Pengiriman Selesai</div>
                    <div class="h3 fw-bold text-dark mb-0"><?= number_format($completedShipments, 0, ',', '.') ?></div>
                </div>
                <div class="kpi-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
            <div class="pt-2 border-top small text-secondary d-flex align-items-center justify-content-between">
                <span>Status Distribusi:</span>
                <span class="badge badge-soft-success">Selesai Dikirim</span>
            </div>
        </div>
    </div>
</div>

<!-- Baris 2: Mini Peta Sebaran Titik Wilayah Bandung -->
<div class="content-card p-3 p-lg-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h6 fw-bold text-dark mb-1">
                <i class="bi bi-map-fill text-primary me-1"></i> Peta Sebaran Gudang &amp; Titik Pelanggan Wilayah Bandung
            </h2>
            <p class="text-secondary small mb-0">
                Menampilkan lokasi <strong><?= e($activeWarehouse['name']) ?></strong> dan <?= (int)$custTotal ?> titik tujuan distribusi. Klik marker untuk detail koordinat &amp; alamat.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 small">
            <span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-building-fill me-1"></i>Gudang Utama</span>
            <span class="badge badge-soft-primary px-2 py-1"><i class="bi bi-shop me-1"></i>Toko</span>
            <span class="badge badge-soft-info px-2 py-1"><i class="bi bi-cart-check-fill me-1"></i>Minimarket</span>
            <span class="badge badge-soft-purple px-2 py-1"><i class="bi bi-cup-hot-fill me-1"></i>Restoran</span>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="resetDashboardMapBtn">
                <i class="bi bi-arrows-fullscreen me-1"></i>Reset Zoom
            </button>
        </div>
    </div>

    <div id="dashboardMap" class="map-dashboard-box"></div>
</div>

<!-- Baris 3: Tabel 5 Pengiriman Terbaru -->
<div class="content-card overflow-hidden">
    <div class="p-3 p-lg-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="h6 fw-bold text-dark mb-1">
                <i class="bi bi-clock-history text-primary me-1"></i> 5 Aktivitas Pengiriman Terbaru
            </h2>
            <p class="text-secondary small mb-0">Rekaman pengiriman terakhir dari Gudang Utama Soekarno-Hatta ke mitra pelanggan Bandung.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(base_url('index.php?page=pengiriman')) ?>" class="btn btn-sm btn-primary fw-semibold">
                <i class="bi bi-truck me-1"></i>Kelola Pengiriman
            </a>
            <a href="<?= e(base_url('index.php?page=peta')) ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                <i class="bi bi-sign-turn-right-fill me-1"></i>Simulasi Rute OSRM
            </a>
            <a href="<?= e(base_url('index.php?page=laporan')) ?>" class="btn btn-sm btn-outline-secondary fw-semibold">
                <i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan
            </a>
        </div>
    </div>

    <div class="table-scroll-container" tabindex="0" role="region" aria-label="Tabel 5 Pengiriman Terbaru">
        <table class="table table-gis">
            <thead>
                <tr>
                    <th scope="col">Kode</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Tujuan Pelanggan</th>
                    <th scope="col">Barang &amp; Kuantitas</th>
                    <th scope="col">Armada &amp; Sopir</th>
                    <th scope="col">Jarak (OSRM)</th>
                    <th scope="col">Estimasi Waktu</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($latestShipments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-secondary">Belum ada data pengiriman tercatat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($latestShipments as $ship): ?>
                        <?php
                        $statusBadge = 'badge-soft-warning';
                        $statusIcon  = 'bi-hourglass-split';
                        if ($ship['status'] === 'Dalam pengiriman') {
                            $statusBadge = 'badge-soft-primary';
                            $statusIcon  = 'bi-truck';
                        } elseif ($ship['status'] === 'Selesai dikirim') {
                            $statusBadge = 'badge-soft-success';
                            $statusIcon  = 'bi-check-circle-fill';
                        }
                        ?>
                        <tr>
                            <th scope="row" class="fw-bold text-primary text-nowrap"><?= e($ship['shipment_code']) ?></th>
                            <td class="text-nowrap small text-secondary"><?= e(date('d/m/Y', strtotime($ship['shipment_date']))) ?></td>
                            <td class="col-name-prop">
                                <div class="fw-semibold text-dark"><?= e($ship['customer_name']) ?></div>
                                <div class="small text-secondary"><?= e($ship['customer_code']) ?> &bull; <?= e($ship['customer_category']) ?></div>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><?= e($ship['product_name']) ?></div>
                                <div class="small text-secondary">
                                    <?= (int)$ship['quantity'] ?> <?= e($ship['product_unit']) ?> (<?= number_format((float)$ship['total_weight'], 1, ',', '.') ?> kg)
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <div class="fw-medium text-dark"><?= e($ship['vehicle_type']) ?></div>
                                <div class="small text-secondary"><i class="bi bi-person-badge me-1"></i><?= e($ship['driver_name']) ?></div>
                            </td>
                            <td class="text-nowrap fw-semibold text-dark">
                                <i class="bi bi-signpost-split text-primary me-1"></i><?= number_format((float)$ship['distance_km'], 2, ',', '.') ?> km
                            </td>
                            <td class="text-nowrap fw-semibold text-dark">
                                <i class="bi bi-stopwatch text-info me-1"></i><?= (int)$ship['duration_minutes'] ?> menit
                            </td>
                            <td class="text-nowrap">
                                <span class="badge <?= $statusBadge ?> px-2 py-1">
                                    <i class="bi <?= $statusIcon ?> me-1"></i><?= e($ship['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapContainer = document.getElementById('dashboardMap');
    if (!mapContainer || typeof L === 'undefined') return;

    const warehouseData = <?= json_encode($activeWarehouse, JSON_UNESCAPED_UNICODE) ?>;
    const customersData = <?= json_encode($allCustomers, JSON_UNESCAPED_UNICODE) ?>;

    const whLat = parseFloat(warehouseData.latitude) || -6.943097;
    const whLng = parseFloat(warehouseData.longitude) || 107.633545;

    const map = L.map('dashboardMap').setView([whLat, whLng], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const bounds = L.latLngBounds();

    // Marker Gudang Utama (Merah)
    const warehouseIcon = window.createMapPinIcon('#DC2626', 'bi-building-fill');
    const whMarker = L.marker([whLat, whLng], { icon: warehouseIcon }).addTo(map);
    whMarker.bindPopup(`
        <div style="min-width: 210px;">
            <span class="badge bg-danger mb-1">Titik Awal Distribusi (Gudang)</span>
            <div class="fw-bold text-dark">${window.escapeHtml(warehouseData.name)}</div>
            <div class="small text-secondary mt-1">${window.escapeHtml(warehouseData.address)}</div>
            <div class="small text-muted mt-1 font-monospace">${whLat.toFixed(6)}, ${whLng.toFixed(6)}</div>
        </div>
    `);
    bounds.extend([whLat, whLng]);

    // Warna marker per kategori pelanggan
    const categoryStyle = {
        'Toko':       { color: '#2563EB', icon: 'bi-shop' },
        'Minimarket': { color: '#0284C7', icon: 'bi-cart-check-fill' },
        'Restoran':   { color: '#7C3AED', icon: 'bi-cup-hot-fill' }
    };

    customersData.forEach(c => {
        const lat = parseFloat(c.latitude);
        const lng = parseFloat(c.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        const style = categoryStyle[c.category] || categoryStyle['Toko'];
        const pinIcon = window.createMapPinIcon(style.color, style.icon);

        const marker = L.marker([lat, lng], { icon: pinIcon }).addTo(map);
        marker.bindPopup(`
            <div style="min-width: 215px;">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 mb-1">
                    ${window.escapeHtml(c.code)} &bull; ${window.escapeHtml(c.category)}
                </span>
                <div class="fw-bold text-dark">${window.escapeHtml(c.name)}</div>
                <div class="small text-secondary mt-1"><i class="bi bi-geo-alt me-1"></i>${window.escapeHtml(c.address)}</div>
                <div class="small text-secondary mt-1"><i class="bi bi-person me-1"></i>PIC: ${window.escapeHtml(c.pic_name)} (${window.escapeHtml(c.phone)})</div>
                <div class="small text-muted mt-1 font-monospace">${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
            </div>
        `);
        bounds.extend([lat, lng]);
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [35, 35] });
    }

    const resetBtn = document.getElementById('resetDashboardMapBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [35, 35] });
            } else {
                map.setView([whLat, whLng], 12);
            }
        });
    }
});
</script>
