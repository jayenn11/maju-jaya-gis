<?php
/**
 * Front Controller & Router Utama
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

require_once __DIR__ . '/config/database.php';

$page = strtolower(trim((string)($_GET['page'] ?? '')));

// Jika terdapat aksi logout langsung dari index.php
if ($page === 'logout' || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    require __DIR__ . '/api/auth.php';
    exit;
}

$isLoggedIn = !empty($_SESSION['admin_id']);

// Jika belum login, arahkan ke halaman login
if (!$isLoggedIn) {
    require __DIR__ . '/views/login.php';
    exit;
}

// Jika sudah login tetapi membuka halaman login, arahkan ke dashboard
if ($page === '' || $page === 'login') {
    $page = 'dashboard';
}

// Ambil informasi Gudang Utama aktif untuk ditampilkan pada Topbar & halaman
$warehouseStmt = $pdo->query('SELECT * FROM warehouses ORDER BY id ASC LIMIT 1');
$activeWarehouse = $warehouseStmt->fetch() ?: [
    'id'        => 1,
    'name'      => 'Gudang Utama PT Maju Jaya Food',
    'address'   => 'Jl. Soekarno-Hatta No. 450, Bandung',
    'latitude'  => -6.94309700,
    'longitude' => 107.63354500,
];

// Daftar halaman yang valid beserta metadata judul
$routes = [
    'dashboard' => [
        'title'    => 'Dashboard Monitoring Distribusi',
        'subtitle' => 'Ringkasan metrik logistik, sebaran titik pelanggan Bandung, dan aktivitas pengiriman terbaru',
        'view'     => __DIR__ . '/views/dashboard.php',
    ],
    'peta' => [
        'title'    => 'Peta Pengiriman & Rute (OSRM)',
        'subtitle' => 'Visualisasi spasial jalur pengiriman makanan dan estimasi rute jalan raya menggunakan OSRM API',
        'view'     => __DIR__ . '/views/peta.php',
    ],
    'pengiriman' => [
        'title'    => 'Manajemen Pengiriman',
        'subtitle' => 'Kelola penugasan armada, sopir, muatan barang, dan status pengiriman ke titik pelanggan',
        'view'     => __DIR__ . '/views/pengiriman.php',
    ],
    'pelanggan' => [
        'title'    => 'Data Pelanggan (Titik Tujuan)',
        'subtitle' => 'Kelola titik koordinat lokasi Toko, Minimarket, dan Restoran mitra di wilayah Bandung',
        'view'     => __DIR__ . '/views/pelanggan.php',
    ],
    'barang' => [
        'title'    => 'Data Barang (Katalog Produk)',
        'subtitle' => 'Kelola katalog produk makanan distribusi, satuan kemasan, bobot per unit, dan stok gudang',
        'view'     => __DIR__ . '/views/barang.php',
    ],
    'gudang' => [
        'title'    => 'Pengaturan Lokasi Gudang',
        'subtitle' => 'Konfigurasi titik koordinat awal keberangkatan distribusi PT Maju Jaya Food Bandung',
        'view'     => __DIR__ . '/views/gudang.php',
    ],
    'laporan' => [
        'title'    => 'Laporan Rekapitulasi Pengiriman',
        'subtitle' => 'Rekapitulasi data historis distribusi makanan, jarak tempuh armada, dan cetak laporan resmi',
        'view'     => __DIR__ . '/views/laporan.php',
    ],
];

if (!isset($routes[$page])) {
    $page = 'dashboard';
}

$currentRoute = $routes[$page];
$pageTitle    = $currentRoute['title'];
$pageSubtitle = $currentRoute['subtitle'];
$activePage   = $page;

require __DIR__ . '/views/layouts/header.php';

if (file_exists($currentRoute['view'])) {
    require $currentRoute['view'];
} else {
    ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5 text-center my-4 bg-white">
        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary" style="width: 68px; height: 68px;">
            <i class="bi bi-Compass fs-2"></i>
        </div>
        <h4 class="fw-bold text-slate-900 mb-2"><?= e($pageTitle) ?></h4>
        <p class="text-secondary mb-4 mx-auto" style="max-width: 540px;">
            <?= e($pageSubtitle) ?>. Modul ini telah disiapkan pada navigasi utama dan akan diaktifkan sepenuhnya pada tahap pengembangan berikutnya.
        </p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= e(base_url('index.php?page=dashboard')) ?>" class="btn btn-primary px-4">
                <i class="bi bi-speedometer2 me-1"></i> Kembali ke Dashboard
            </a>
            <a href="<?= e(base_url('index.php?page=pelanggan')) ?>" class="btn btn-outline-secondary px-4">
                <i class="bi bi-geo-alt me-1"></i> Lihat Data Pelanggan
            </a>
        </div>
    </div>
    <?php
}

require __DIR__ . '/views/layouts/footer.php';
