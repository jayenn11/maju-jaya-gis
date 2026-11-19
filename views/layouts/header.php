<?php
/**
 * Layout Header, Sidebar Kiri (#0F172A), dan Topbar
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

$adminName     = $_SESSION['admin_name'] ?? 'Administrator Logistik';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';
$warehouseName = $activeWarehouse['name'] ?? 'Gudang Utama PT Maju Jaya Food';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — SIG Distribusi Makanan PT Maju Jaya Food</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons 1.11 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Leaflet.js 1.9.4 CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <!-- Custom App Styles -->
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
    <!-- Leaflet.js 1.9.4 JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body>
<div class="app-wrapper">
    <!-- Backdrop Mobile Sidebar -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar Kiri (#0F172A) -->
    <aside class="app-sidebar" id="appSidebar">
        <!-- Brand Header (padding seragam p-4) -->
        <div class="sidebar-brand p-4">
            <a href="<?= e(base_url('index.php?page=dashboard')) ?>" class="d-flex align-items-center gap-3 text-decoration-none text-white">
                <div class="rounded-3 bg-primary d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 42px; height: 42px;">
                    <i class="bi bi-box-seam-fill fs-5 text-white"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold text-white text-truncate" style="font-size: 0.98rem;">PT Maju Jaya Food</div>
                    <div class="text-info small fw-medium" style="font-size: 0.74rem;">SIG Distribusi Bandung</div>
                </div>
            </a>

            <!-- Profil Singkat Admin Aktif -->
            <div class="sidebar-profile mt-3 d-flex align-items-center gap-2">
                <div class="rounded-circle bg-primary bg-opacity-25 text-info d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 36px; height: 36px; font-size: 0.85rem;">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-white fw-semibold text-truncate" style="font-size: 0.82rem;"><?= e($adminName) ?></div>
                    <div class="text-secondary d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                        <span class="d-inline-block rounded-circle bg-success" style="width: 7px; height: 7px;"></span>
                        <span class="text-light text-opacity-75">@<?= e($adminUsername) ?> &bull; Aktif</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Navigasi Utama -->
        <nav class="sidebar-nav" aria-label="Navigasi Utama">
            <div class="sidebar-section-label">Utama &amp; Spasial</div>
            <a href="<?= e(base_url('index.php?page=dashboard')) ?>" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= e(base_url('index.php?page=peta')) ?>" class="sidebar-link <?= ($activePage ?? '') === 'peta' ? 'active' : '' ?>">
                <i class="bi bi-map-fill"></i>
                <span>Peta Pengiriman &amp; Rute</span>
            </a>
            <a href="<?= e(base_url('index.php?page=pengiriman')) ?>" class="sidebar-link <?= ($activePage ?? '') === 'pengiriman' ? 'active' : '' ?>">
                <i class="bi bi-truck"></i>
                <span>Manajemen Pengiriman</span>
            </a>

            <div class="sidebar-section-label mt-2">Master Data &amp; Lokasi</div>
            <a href="<?= e(base_url('index.php?page=pelanggan')) ?>" class="sidebar-link <?= ($activePage ?? '') === 'pelanggan' ? 'active' : '' ?>">
                <i class="bi bi-shop"></i>
                <span>Data Pelanggan</span>
            </a>
            <a href="<?= e(base_url('index.php?page=barang')) ?>" class="sidebar-link <?= ($activePage ?? '') === 'barang' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                <span>Data Barang</span>
            </a>
            <a href="<?= e(base_url('index.php?page=gudang')) ?>" class="sidebar-link <?= ($activePage ?? '') === 'gudang' ? 'active' : '' ?>">
                <i class="bi bi-building-gear"></i>
                <span>Pengaturan Gudang</span>
            </a>

            <div class="sidebar-section-label mt-2">Evaluasi &amp; Arsip</div>
            <a href="<?= e(base_url('index.php?page=laporan')) ?>" class="sidebar-link <?= ($activePage ?? '') === 'laporan' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span>Laporan Pengiriman</span>
            </a>
        </nav>

        <!-- Footer Sidebar -->
        <div class="p-3 border-top border-secondary border-opacity-25">
            <a href="<?= e(base_url('api/auth.php?action=logout')) ?>" data-logout-trigger class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2 py-2 rounded-3">
                <i class="bi bi-box-arrow-right"></i>
                <span>Keluar Sesi Admin</span>
            </a>
        </div>
    </aside>

    <!-- Area Konten Kanan -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="app-topbar d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-light border d-lg-none" id="sidebarToggleBtn" aria-label="Buka Menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="h5 fw-bold text-dark mb-0"><?= e($pageTitle ?? 'Dashboard') ?></h1>
                    <p class="text-secondary small mb-0 d-none d-md-block"><?= e($pageSubtitle ?? '') ?></p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 gap-md-3">
                <!-- Indikator Lokasi Gudang Aktif -->
                <a href="<?= e(base_url('index.php?page=gudang')) ?>" class="text-decoration-none d-none d-sm-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small fw-semibold" title="Titik Awal Pengiriman Aktif">
                    <i class="bi bi-geo-alt-fill text-danger"></i>
                    <span class="text-truncate" style="max-width: 220px;" id="topbarWarehouseBadge"><?= e($warehouseName) ?> (Bandung)</span>
                </a>

                <!-- Tombol Logout Topbar -->
                <a href="<?= e(base_url('api/auth.php?action=logout')) ?>" data-logout-trigger class="btn btn-light border btn-sm px-3 py-2 rounded-3 text-danger fw-semibold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-power"></i>
                    <span class="d-none d-sm-inline">Logout</span>
                </a>
            </div>
        </header>

        <!-- Konten Halaman -->
        <main class="app-content">
