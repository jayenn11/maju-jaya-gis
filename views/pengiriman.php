<?php
/**
 * Modul Manajemen & Monitoring Pengiriman
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 *
 * Mematuhi standar AGENTS.md:
 * - Custom Searchable Dropdown (Tanpa elemen select bawaan browser)
 * - Modal-First CRUD + overflow: visible pada .modal-content
 * - Integrasi OSRM Otomatis saat memilih pelanggan tujuan
 * - Kalkulasi Berat & Validasi Stok Barang secara live
 * - Modal Detail dengan Mini Peta Leaflet.js & Alur Perubahan Status via Custom Confirmation Modal
 */

// Ambil daftar pelanggan untuk Custom Searchable Dropdown
$custStmt = $pdo->query('SELECT id, code, name, category, address, latitude, longitude FROM customers ORDER BY code ASC');
$customersList = $custStmt->fetchAll();

// Ambil daftar produk untuk Custom Searchable Dropdown & kalkulasi berat/stok
$prodStmt = $pdo->query('SELECT id, code, name, category, unit, weight_per_unit, stock FROM products ORDER BY code ASC');
$productsList = $prodStmt->fetchAll();

// Susun opsi JSON untuk Custom Searchable Dropdown Pelanggan
$customerDropdownOptions = [];
foreach ($customersList as $c) {
    $customerDropdownOptions[] = [
        'value' => (string)$c['id'],
        'label' => $c['code'] . ' — ' . $c['name'],
        'sub'   => $c['address'] . ' ' . $c['category'],
        'badge' => $c['category'],
    ];
}

// Susun opsi JSON untuk Custom Searchable Dropdown Barang
$productDropdownOptions = [];
foreach ($productsList as $p) {
    $productDropdownOptions[] = [
        'value' => (string)$p['id'],
        'label' => $p['code'] . ' — ' . $p['name'] . ' (' . number_format((float)$p['weight_per_unit'], 1, '.', '') . ' kg/' . $p['unit'] . ')',
        'sub'   => $p['category'] . ' Stok: ' . $p['stock'],
        'badge' => 'Stok: ' . (int)$p['stock'] . ' ' . $p['unit'],
    ];
}

// Parameter URL dari halaman Peta Rute OSRM (?create=1&customer_id=X&distance_km=Y&duration_minutes=Z)
$autoOpenCreate = !empty($_GET['create']) || (($_GET['action'] ?? '') === 'new');
$prefillCustId  = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$prefillDistKm  = isset($_GET['distance_km']) && is_numeric($_GET['distance_km']) ? round((float)$_GET['distance_km'], 2) : '';
$prefillDurMin  = isset($_GET['duration_minutes']) && is_numeric($_GET['duration_minutes']) ? (int)$_GET['duration_minutes'] : '';
?>

<div class="content-card">
    <!-- Header Bar: Live Search, Auto-Filter Status, & Tombol Buat Pengiriman -->
    <div class="p-3 p-lg-4 border-bottom">
        <div class="row g-3 align-items-center justify-content-between">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-secondary">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="shipmentSearchInput" class="form-control border-start-0 ps-0" placeholder="Ketik kode KRM, pelanggan, barang, atau nama sopir (Live Search)...">
                </div>
            </div>

            <div class="col-md-7 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                <!-- Filter Status Pengiriman (Custom Searchable Dropdown) -->
                <div style="width: 235px;">
                    <div class="custom-dropdown" id="filterShipmentStatusDropdown" data-custom-dropdown data-name="filter_status" data-placeholder="Semua Status Pengiriman" data-search-placeholder="Cari status...">
                        <input type="hidden" id="filterShipmentStatusInput" name="filter_status" value="Semua">
                        <script type="application/json" class="dropdown-options">
                            [
                                {"value": "Semua", "label": "Semua Status Pengiriman"},
                                {"value": "Menunggu", "label": "Menunggu"},
                                {"value": "Dalam pengiriman", "label": "Dalam pengiriman"},
                                {"value": "Selesai dikirim", "label": "Selesai dikirim"}
                            ]
                        </script>
                    </div>
                </div>

                <a href="<?= e(base_url('index.php?page=peta')) ?>" class="btn btn-outline-primary fw-semibold px-3 d-inline-flex align-items-center gap-1 text-nowrap">
                    <i class="bi bi-map"></i>
                    <span>Simulasi Peta</span>
                </a>

                <button type="button" class="btn btn-primary fw-semibold px-3 d-inline-flex align-items-center gap-2 text-nowrap" id="btnAddShipment">
                    <i class="bi bi-plus-lg"></i>
                    <span>Tambah Pengiriman</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Pengiriman -->
    <div class="table-scroll-container" tabindex="0" role="region" aria-label="Tabel Daftar Pengiriman">
        <table class="table table-gis">
            <thead>
                <tr>
                    <th scope="col">Kode</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Barang &amp; Muatan</th>
                    <th scope="col">Kendaraan &amp; Sopir</th>
                    <th scope="col">Jarak &amp; Waktu</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody id="shipmentTableBody">
                <tr>
                    <td colspan="8" class="text-center py-5 text-secondary">
                        <span class="spinner-border spinner-border-sm text-primary me-2"></span>Memuat data pengiriman...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Footer Pagination Terang -->
    <div class="p-3 p-lg-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 bg-light bg-opacity-50">
        <div class="small text-secondary" id="shipmentPaginationInfo">Menampilkan 0 jadwal pengiriman</div>
        <nav aria-label="Navigasi Halaman Pengiriman">
            <ul class="pagination pagination-sm pagination-light-theme mb-0" id="shipmentPaginationList"></ul>
        </nav>
    </div>
</div>

<!-- =========================================================================
     MODAL TAMBAH & EDIT PENGIRIMAN (overflow: visible)
     ========================================================================= -->
<div class="modal fade" id="shipmentModal" tabindex="-1" aria-labelledby="shipmentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0" id="shipmentModalTitle">Buat Jadwal Pengiriman Baru</h5>
                    <small class="text-secondary">Pilih pelanggan untuk menghitung jarak OSRM otomatis dan pilih barang untuk kalkulasi muatan.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <form id="shipmentForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="shipmentId" name="id" value="">
                    <input type="hidden" id="shipmentFormAction" name="action" value="create">
                    <input type="hidden" id="shipmentRouteGeometry" name="route_geometry" value="">

                    <div class="row g-3">
                        <!-- Baris 1: Kode, Tanggal, Status -->
                        <div class="col-md-4">
                            <label for="shipmentCode" class="form-label small fw-semibold text-secondary mb-1">Kode Pengiriman <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light fw-bold font-monospace" id="shipmentCode" name="shipment_code" readonly required>
                        </div>

                        <div class="col-md-4">
                            <label for="shipmentDate" class="form-label small fw-semibold text-secondary mb-1">Tanggal Pengiriman <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="shipmentDate" name="shipment_date" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary mb-1">Status Pengiriman <span class="text-danger">*</span></label>
                            <div class="custom-dropdown" id="modalShipmentStatusDropdown" data-custom-dropdown data-name="status" data-placeholder="Pilih Status..." data-search-placeholder="Cari status...">
                                <input type="hidden" id="shipmentStatus" name="status" value="Menunggu">
                                <script type="application/json" class="dropdown-options">
                                    [
                                        {"value": "Menunggu", "label": "Menunggu", "badge": "Antrean"},
                                        {"value": "Dalam pengiriman", "label": "Dalam pengiriman", "badge": "Aktif"},
                                        {"value": "Selesai dikirim", "label": "Selesai dikirim", "badge": "Tuntas"}
                                    ]
                                </script>
                            </div>
                        </div>

                        <!-- Baris 2: Pelanggan Tujuan + Kalkulasi OSRM Otomatis -->
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                <label class="form-label small fw-semibold text-secondary mb-0">
                                    Pelanggan Tujuan (Toko / Minimarket / Restoran Bandung) <span class="text-danger">*</span>
                                </label>
                                <span id="osrmCalcLoadingBadge" class="badge bg-primary bg-opacity-10 text-primary d-none">
                                    <span class="spinner-border spinner-border-sm me-1" style="width: 0.7rem; height: 0.7rem;"></span>Menghitung rute OSRM...
                                </span>
                            </div>
                            <div class="custom-dropdown" id="modalShipmentCustomerDropdown" data-custom-dropdown data-name="customer_id" data-placeholder="Pilih Pelanggan Tujuan..." data-search-placeholder="Ketik kode, nama pelanggan, atau alamat...">
                                <input type="hidden" id="shipmentCustomerId" name="customer_id" value="">
                                <script type="application/json" class="dropdown-options"><?= json_encode($customerDropdownOptions, JSON_UNESCAPED_UNICODE) ?></script>
                            </div>
                        </div>

                        <!-- Baris 3: Barang Distribusi, Kuantitas, & Total Berat Otomatis -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label small fw-semibold text-secondary mb-0">Barang Makanan Distribusi <span class="text-danger">*</span></label>
                                <small class="fw-semibold text-primary" id="selectedProductStockInfo">Pilih barang</small>
                            </div>
                            <div class="custom-dropdown" id="modalShipmentProductDropdown" data-custom-dropdown data-name="product_id" data-placeholder="Pilih Produk Makanan..." data-search-placeholder="Ketik kode atau nama barang...">
                                <input type="hidden" id="shipmentProductId" name="product_id" value="">
                                <script type="application/json" class="dropdown-options"><?= json_encode($productDropdownOptions, JSON_UNESCAPED_UNICODE) ?></script>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="shipmentQuantity" class="form-label small fw-semibold text-secondary mb-1">Jumlah Muatan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="1" min="1" class="form-control" id="shipmentQuantity" name="quantity" data-numeric-only inputmode="numeric" value="1" required>
                                <span class="input-group-text bg-light text-secondary small" id="shipmentUnitAddon">Unit</span>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="shipmentTotalWeight" class="form-label small fw-semibold text-secondary mb-1">Total Berat (Otomatis)</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light fw-bold text-dark" id="shipmentTotalWeight" readonly value="0.00">
                                <span class="input-group-text bg-light text-secondary small">kg</span>
                            </div>
                        </div>

                        <!-- Baris 4: Jenis Kendaraan & Nama Sopir -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary mb-1">Armada Kendaraan Pengangkut <span class="text-danger">*</span></label>
                            <div class="custom-dropdown" id="modalShipmentVehicleDropdown" data-custom-dropdown data-name="vehicle_type" data-placeholder="Pilih Jenis Kendaraan..." data-search-placeholder="Cari kendaraan...">
                                <input type="hidden" id="shipmentVehicleType" name="vehicle_type" value="Mobil Boks">
                                <script type="application/json" class="dropdown-options">
                                    [
                                        {"value": "Mobil Boks", "label": "Mobil Boks", "badge": "Kapasitas Besar"},
                                        {"value": "Pickup Boks", "label": "Pickup Boks", "badge": "Menengah"},
                                        {"value": "Van Pendingin", "label": "Van Pendingin", "badge": "Frozen Food"},
                                        {"value": "Motor Box", "label": "Motor Box", "badge": "Cepat / Kecil"}
                                    ]
                                </script>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="shipmentDriverName" class="form-label small fw-semibold text-secondary mb-1">Nama Sopir Bertugas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="shipmentDriverName" name="driver_name" placeholder="Contoh: Andi / Budi / Dedi / Rudi" required>
                        </div>

                        <!-- Baris 5: Jarak (km) & Estimasi Waktu (menit) Hasil Kalkulasi OSRM -->
                        <div class="col-md-6">
                            <label for="shipmentDistanceKm" class="form-label small fw-semibold text-secondary mb-1">
                                <i class="bi bi-signpost-split text-primary me-1"></i>Jarak Tempuh Rute (OSRM) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.05" class="form-control fw-semibold" id="shipmentDistanceKm" name="distance_km" placeholder="Otomatis saat pilih pelanggan" required>
                                <span class="input-group-text bg-light text-secondary">km</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="shipmentDurationMin" class="form-label small fw-semibold text-secondary mb-1">
                                <i class="bi bi-stopwatch text-info me-1"></i>Estimasi Waktu Tempuh <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="1" min="1" class="form-control fw-semibold" id="shipmentDurationMin" name="duration_minutes" placeholder="Otomatis saat pilih pelanggan" required>
                                <span class="input-group-text bg-light text-secondary">menit</span>
                            </div>
                        </div>

                        <!-- Baris 6: Catatan Pengiriman -->
                        <div class="col-12">
                            <label for="shipmentNotes" class="form-label small fw-semibold text-secondary mb-1">Catatan Pengiriman (Opsional)</label>
                            <textarea class="form-control" id="shipmentNotes" name="notes" rows="2" placeholder="Tambahkan instruksi khusus pengiriman atau penerima..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="shipmentSaveBtn">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Jadwal Pengiriman
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL DETAIL & MONITORING STATUS PENGIRIMAN (Mini Map Leaflet.js)
     ========================================================================= -->
<div class="modal fade" id="shipmentDetailModal" tabindex="-1" aria-labelledby="shipmentDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="modal-title fw-bold text-dark mb-0" id="shipmentDetailTitle">Detail Pengiriman</h5>
                    <span id="detailStatusBadge"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <!-- Mini Peta Leaflet Rute Pengiriman -->
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="small fw-bold text-dark">
                            <i class="bi bi-map-fill text-primary me-1"></i>Visualisasi Jalur Rute Distribusi (Gudang <i class="bi bi-arrow-right mx-1"></i> Pelanggan)
                        </span>
                        <span class="small text-secondary font-monospace" id="detailMetricsPill"></span>
                    </div>
                    <div id="shipmentDetailMap" class="map-shipment-detail"></div>
                </div>

                <div class="row g-3 small">
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 bg-light border h-100">
                            <div class="fw-bold text-secondary text-uppercase mb-2" style="font-size: 0.72rem;">Titik Tujuan Pelanggan</div>
                            <div class="fw-bold text-dark fs-6" id="detailCustomerName">-</div>
                            <div class="text-primary fw-semibold mb-1" id="detailCustomerMeta">-</div>
                            <div class="text-secondary mb-1"><i class="bi bi-geo-alt me-1"></i><span id="detailCustomerAddress">-</span></div>
                            <div class="text-secondary"><i class="bi bi-person-lines-fill me-1"></i>PIC: <span id="detailCustomerPic">-</span></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-3 bg-light border h-100">
                            <div class="fw-bold text-secondary text-uppercase mb-2" style="font-size: 0.72rem;">Rincian Muatan &amp; Armada</div>
                            <div class="fw-bold text-dark fs-6" id="detailProductName">-</div>
                            <div class="text-secondary mb-1">Muatan: <strong class="text-dark" id="detailQuantityWeight">-</strong></div>
                            <div class="text-secondary mb-1">Armada: <strong class="text-dark" id="detailVehicleDriver">-</strong></div>
                            <div class="text-secondary">Tanggal Jadwal: <strong class="text-dark" id="detailShipmentDate">-</strong></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="p-3 rounded-3 bg-light border">
                            <div class="fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Catatan Pengiriman</div>
                            <div class="text-dark" id="detailNotes">-</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div id="detailStatusActionContainer"></div>
                <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiUrl = '<?= e(base_url('api/shipments.php')) ?>';
    const routingApiUrl = '<?= e(base_url('api/routing.php')) ?>';
    const warehouseData = <?= json_encode($activeWarehouse, JSON_UNESCAPED_UNICODE) ?>;
    let productsCatalog = <?= json_encode($productsList, JSON_UNESCAPED_UNICODE) ?>;

    const autoOpenCreate = <?= $autoOpenCreate ? 'true' : 'false' ?>;
    const prefillCustId = <?= (int)$prefillCustId ?>;
    const prefillDistKm = <?= json_encode($prefillDistKm) ?>;
    const prefillDurMin = <?= json_encode($prefillDurMin) ?>;

    let currentPage = 1;
    let currentSearch = '';
    let currentStatus = 'Semua';
    let nextGeneratedCode = 'KRM007';
    let editingOriginalProductId = 0;
    let editingOriginalQuantity = 0;
    let suppressNextCustomerRouteFetch = false;

    const tableBody = document.getElementById('shipmentTableBody');
    const paginationInfo = document.getElementById('shipmentPaginationInfo');
    const paginationList = document.getElementById('shipmentPaginationList');
    const searchInput = document.getElementById('shipmentSearchInput');
    const filterStatusInput = document.getElementById('filterShipmentStatusInput');

    const modalEl = document.getElementById('shipmentModal');
    const bsModal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('shipmentForm');
    const modalTitle = document.getElementById('shipmentModalTitle');
    const saveBtn = document.getElementById('shipmentSaveBtn');

    const customerInput = document.getElementById('shipmentCustomerId');
    const productInput = document.getElementById('shipmentProductId');
    const quantityInput = document.getElementById('shipmentQuantity');
    const totalWeightInput = document.getElementById('shipmentTotalWeight');
    const unitAddon = document.getElementById('shipmentUnitAddon');
    const stockInfoEl = document.getElementById('selectedProductStockInfo');
    const distInput = document.getElementById('shipmentDistanceKm');
    const durInput = document.getElementById('shipmentDurationMin');
    const geomInput = document.getElementById('shipmentRouteGeometry');
    const osrmLoadingBadge = document.getElementById('osrmCalcLoadingBadge');

    // Detail Modal & Mini Map
    const detailModalEl = document.getElementById('shipmentDetailModal');
    const bsDetailModal = new bootstrap.Modal(detailModalEl);
    let detailMap = null;
    let detailLayerGroup = null;
    let currentDetailRow = null;

    function getStatusBadgeHtml(status) {
        if (status === 'Selesai dikirim') {
            return `<span class="badge badge-soft-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Selesai dikirim</span>`;
        }
        if (status === 'Dalam pengiriman') {
            return `<span class="badge badge-soft-primary px-2 py-1"><i class="bi bi-truck me-1"></i>Dalam pengiriman</span>`;
        }
        return `<span class="badge badge-soft-warning px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Menunggu</span>`;
    }

    function syncProductDropdownOptions(selectedValue = '') {
        const opts = productsCatalog.map(p => ({
            value: String(p.id),
            label: `${p.code} — ${p.name} (${Number(p.weight_per_unit).toFixed(1)} kg/${p.unit})`,
            sub: `${p.category} Stok: ${p.stock}`,
            badge: `Stok: ${p.stock} ${p.unit}`
        }));
        window.updateCustomDropdownOptions('modalShipmentProductDropdown', opts, String(selectedValue || ''));
    }

    // =========================================================================
    // 1. KALKULASI BERAT OTOMATIS & VALIDASI STOK BARANG LIVE
    // =========================================================================
    function recalculateWeightAndValidateStock() {
        const pid = parseInt(productInput.value, 10);
        const qty = parseInt(quantityInput.value, 10) || 0;
        const prod = productsCatalog.find(p => Number(p.id) === pid);

        if (!prod) {
            totalWeightInput.value = '0.00';
            unitAddon.textContent = 'Unit';
            stockInfoEl.textContent = 'Pilih barang';
            stockInfoEl.className = 'fw-semibold text-secondary';
            return true;
        }

        unitAddon.textContent = prod.unit;
        let maxAvailable = Number(prod.stock);
        if (document.getElementById('shipmentFormAction').value === 'update' && Number(prod.id) === editingOriginalProductId) {
            maxAvailable += editingOriginalQuantity;
        }

        const totalWeight = (qty * Number(prod.weight_per_unit)).toFixed(2);
        totalWeightInput.value = totalWeight;

        if (qty > maxAvailable) {
            stockInfoEl.textContent = `Stok tidak cukup! Maks: ${maxAvailable} ${prod.unit}`;
            stockInfoEl.className = 'fw-bold text-danger';
            return false;
        } else {
            stockInfoEl.textContent = `Stok tersedia: ${maxAvailable} ${prod.unit}`;
            stockInfoEl.className = 'fw-semibold text-success';
            return true;
        }
    }

    productInput.addEventListener('change', recalculateWeightAndValidateStock);
    quantityInput.addEventListener('input', recalculateWeightAndValidateStock);

    // =========================================================================
    // 2. INTEGRASI OSRM OTOMATIS SAAT PELANGGAN DIPILIH DI MODAL
    // =========================================================================
    customerInput.addEventListener('change', async function () {
        if (suppressNextCustomerRouteFetch) {
            suppressNextCustomerRouteFetch = false;
            return;
        }
        const cid = parseInt(this.value, 10);
        if (!cid || isNaN(cid)) return;

        osrmLoadingBadge.classList.remove('d-none');
        try {
            const res = await fetch(routingApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    warehouse_id: warehouseData.id,
                    customer_ids: [cid]
                })
            });
            const json = await res.json();
            if (res.ok && json.status === 'success') {
                distInput.value = Number(json.total_distance_km).toFixed(2);
                durInput.value = json.total_duration_minutes;
                if (json.geometry) {
                    geomInput.value = JSON.stringify(json.geometry);
                }
            }
        } catch (err) {
            // Biarkan admin mengisi manual jika gagal
        } finally {
            osrmLoadingBadge.classList.add('d-none');
        }
    });

    // =========================================================================
    // 3. LOAD TABEL PENGIRIMAN, LIVE SEARCH, FILTER STATUS, & PAGINATION
    // =========================================================================
    async function loadShipments(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({
            page: currentPage,
            per_page: 10,
            search: currentSearch,
            status: currentStatus
        });

        try {
            const res = await fetch(`${apiUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();

            if (!res.ok || json.status !== 'success') {
                throw new Error(json.message || 'Gagal memuat data pengiriman.');
            }

            if (json.next_code) {
                nextGeneratedCode = json.next_code;
            }
            if (Array.isArray(json.products)) {
                productsCatalog = json.products;
            }

            renderTable(json.data || []);
            renderPagination(json.pagination || { total: 0, page: 1, per_page: 10, total_pages: 1 });
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${window.escapeHtml(err.message)}</td></tr>`;
        }
    }

    function renderTable(rows) {
        if (!rows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-secondary">
                        <i class="bi bi-truck fs-3 d-block mb-2 text-muted"></i>
                        Tidak ada jadwal pengiriman yang sesuai dengan pencarian/filter.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = rows.map(r => {
            const dateFormatted = r.shipment_date ? r.shipment_date.split('-').reverse().join('/') : '-';
            let quickStatusBtn = '';
            if (r.status === 'Menunggu') {
                quickStatusBtn = `
                    <button type="button" class="btn btn-sm btn-outline-primary btn-quick-status" data-id="${r.id}" data-code="${window.escapeHtml(r.shipment_code)}" data-next-status="Dalam pengiriman" title="Mulai Pengiriman">
                        <i class="bi bi-play-fill"></i>Mulai
                    </button>
                `;
            } else if (r.status === 'Dalam pengiriman') {
                quickStatusBtn = `
                    <button type="button" class="btn btn-sm btn-outline-success btn-quick-status" data-id="${r.id}" data-code="${window.escapeHtml(r.shipment_code)}" data-next-status="Selesai dikirim" title="Selesaikan Pengiriman">
                        <i class="bi bi-check2"></i>Selesai
                    </button>
                `;
            }

            return `
                <tr>
                    <th scope="row" class="fw-bold text-primary font-monospace text-nowrap">${window.escapeHtml(r.shipment_code)}</th>
                    <td class="text-nowrap small text-secondary">${window.escapeHtml(dateFormatted)}</td>
                    <td class="col-name-prop">
                        <div class="fw-bold text-dark">${window.escapeHtml(r.customer_name)}</div>
                        <div class="small text-secondary">${window.escapeHtml(r.customer_code)} &bull; ${window.escapeHtml(r.customer_category)}</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${window.escapeHtml(r.product_name)}</div>
                        <div class="small text-secondary text-nowrap">
                            ${Number(r.quantity)} ${window.escapeHtml(r.product_unit)} &bull; <strong>${Number(r.total_weight).toFixed(2)} kg</strong>
                        </div>
                    </td>
                    <td class="text-nowrap">
                        <div class="fw-semibold text-dark">${window.escapeHtml(r.vehicle_type)}</div>
                        <div class="small text-secondary"><i class="bi bi-person-badge me-1"></i>${window.escapeHtml(r.driver_name)}</div>
                    </td>
                    <td class="text-nowrap">
                        <div class="fw-bold text-dark"><i class="bi bi-signpost-split text-primary me-1"></i>${Number(r.distance_km).toFixed(2)} km</div>
                        <div class="small text-secondary"><i class="bi bi-stopwatch text-info me-1"></i>${Number(r.duration_minutes)} menit</div>
                    </td>
                    <td class="text-nowrap">${getStatusBadgeHtml(r.status)}</td>
                    <td class="text-end text-nowrap">
                        <div class="d-inline-flex align-items-center gap-1">
                            ${quickStatusBtn}
                            <button type="button" class="btn btn-sm btn-light border text-dark btn-detail-shipment" data-id="${r.id}" title="Detail & Peta Rute">
                                <i class="bi bi-eye me-1"></i>Detail
                            </button>
                            <button type="button" class="btn btn-sm btn-light border text-primary btn-edit-shipment" data-id="${r.id}" title="Edit Pengiriman">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-shipment" data-id="${r.id}" data-code="${window.escapeHtml(r.shipment_code)}" data-status="${window.escapeHtml(r.status)}" title="Hapus Pengiriman">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(meta) {
        const total = Number(meta.total || 0);
        const page = Number(meta.page || 1);
        const perPage = Number(meta.per_page || 10);
        const totalPages = Number(meta.total_pages || 1);

        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(total, page * perPage);
        paginationInfo.textContent = `Menampilkan ${start}–${end} dari total ${total} data pengiriman`;

        let html = `
            <li class="page-item ${page <= 1 ? 'disabled' : ''}">
                <button type="button" class="page-link" data-page="${page - 1}">Sebelumnya</button>
            </li>
        `;
        for (let i = 1; i <= totalPages; i++) {
            html += `
                <li class="page-item ${i === page ? 'active' : ''}">
                    <button type="button" class="page-link" data-page="${i}">${i}</button>
                </li>
            `;
        }
        html += `
            <li class="page-item ${page >= totalPages ? 'disabled' : ''}">
                <button type="button" class="page-link" data-page="${page + 1}">Berikutnya</button>
            </li>
        `;
        paginationList.innerHTML = html;
    }

    paginationList.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-page]');
        if (!btn || btn.parentElement.classList.contains('disabled')) return;
        const targetPage = parseInt(btn.dataset.page, 10);
        if (!isNaN(targetPage)) {
            loadShipments(targetPage);
        }
    });

    // Live Search Debounce 300ms
    searchInput.addEventListener('input', window.debounce(function () {
        currentSearch = this.value.trim();
        loadShipments(1);
    }, 300));

    // Auto-Filter Status
    filterStatusInput.addEventListener('change', function () {
        currentStatus = this.value || 'Semua';
        loadShipments(1);
    });

    // =========================================================================
    // 4. BUKA MODAL TAMBAH PENGIRIMAN (TERMASUK AUTO-PREFILL DARI PETA OSRM)
    // =========================================================================
    function openCreateShipmentModal(prefill = {}) {
        form.reset();
        editingOriginalProductId = 0;
        editingOriginalQuantity = 0;

        document.getElementById('shipmentId').value = '';
        document.getElementById('shipmentFormAction').value = 'create';
        document.getElementById('shipmentCode').value = nextGeneratedCode;
        document.getElementById('shipmentDate').value = '<?= date('Y-m-d') ?>';
        geomInput.value = prefill.geometry ? JSON.stringify(prefill.geometry) : '';

        syncProductDropdownOptions(productsCatalog[0] ? productsCatalog[0].id : '');
        window.setCustomDropdownValue('modalShipmentStatusDropdown', 'Menunggu', false);
        window.setCustomDropdownValue('modalShipmentVehicleDropdown', 'Mobil Boks', false);

        if (prefill.customer_id) {
            const hasPrefilledRoute = prefill.distance_km && prefill.duration_minutes;
            if (hasPrefilledRoute) {
                suppressNextCustomerRouteFetch = true;
                distInput.value = Number(prefill.distance_km).toFixed(2);
                durInput.value = prefill.duration_minutes;
            }
            window.setCustomDropdownValue('modalShipmentCustomerDropdown', String(prefill.customer_id), !hasPrefilledRoute);
        } else {
            window.setCustomDropdownValue('modalShipmentCustomerDropdown', '', false);
        }

        if (productsCatalog.length > 0) {
            window.setCustomDropdownValue('modalShipmentProductDropdown', String(productsCatalog[0].id), false);
        }
        quantityInput.value = '10';
        recalculateWeightAndValidateStock();

        modalTitle.textContent = 'Buat Jadwal Pengiriman Baru';
        bsModal.show();
    }

    document.getElementById('btnAddShipment').addEventListener('click', function () {
        openCreateShipmentModal();
    });

    // =========================================================================
    // 5. MODAL DETAIL & MONITORING STATUS + MINI PETA LEAFLET.JS
    // =========================================================================
    function promptStatusUpdate(id, code, nextStatus, onDone) {
        const isStarting = nextStatus === 'Dalam pengiriman';
        window.showConfirmModal({
            title: isStarting ? 'Mulai Pengiriman Armada?' : 'Selesaikan Pengiriman?',
            message: isStarting
                ? `Ubah status jadwal pengiriman ${code} menjadi "Dalam pengiriman"? Armada akan tercatat sedang bergerak menuju lokasi pelanggan.`
                : `Konfirmasi bahwa pengiriman ${code} telah tiba di lokasi pelanggan dan selesai diterima ("Selesai dikirim")?`,
            confirmText: isStarting ? 'Ya, Mulai Pengiriman' : 'Ya, Selesaikan',
            cancelText: 'Batal',
            variant: isStarting ? 'primary' : 'success',
            onConfirm: async () => {
                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'update_status',
                            id: id,
                            status: nextStatus
                        })
                    });
                    const json = await res.json();
                    if (!res.ok || json.status !== 'success') {
                        throw new Error(json.message || 'Gagal memperbarui status pengiriman.');
                    }
                    window.showToast({ title: 'Status Diperbarui', message: json.message, type: 'success' });
                    if (typeof onDone === 'function') onDone();
                    loadShipments(currentPage);
                } catch (err) {
                    window.showToast({ title: 'Gagal', message: err.message, type: 'danger' });
                }
            }
        });
    }

    async function openShipmentDetailModal(id) {
        try {
            const res = await fetch(`${apiUrl}?action=detail&id=${encodeURIComponent(id)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (!res.ok || json.status !== 'success') {
                throw new Error(json.message || 'Gagal memuat detail pengiriman.');
            }

            const d = json.data;
            currentDetailRow = d;

            document.getElementById('shipmentDetailTitle').textContent = `Detail Pengiriman ${d.shipment_code}`;
            document.getElementById('detailStatusBadge').innerHTML = getStatusBadgeHtml(d.status);
            document.getElementById('detailMetricsPill').textContent = `Jarak: ${Number(d.distance_km).toFixed(2)} km • Estimasi: ${d.duration_minutes} menit`;

            document.getElementById('detailCustomerName').textContent = d.customer_name;
            document.getElementById('detailCustomerMeta').textContent = `${d.customer_code} • Kategori: ${d.customer_category}`;
            document.getElementById('detailCustomerAddress').textContent = d.customer_address;
            document.getElementById('detailCustomerPic').textContent = `${d.customer_pic} (${d.customer_phone})`;

            document.getElementById('detailProductName').textContent = `${d.product_code} — ${d.product_name}`;
            document.getElementById('detailQuantityWeight').textContent = `${d.quantity} ${d.product_unit} (Total: ${Number(d.total_weight).toFixed(2)} kg)`;
            document.getElementById('detailVehicleDriver').textContent = `${d.vehicle_type} • Sopir: ${d.driver_name}`;
            document.getElementById('detailShipmentDate').textContent = d.shipment_date ? d.shipment_date.split('-').reverse().join('/') : '-';
            document.getElementById('detailNotes').textContent = d.notes || 'Tidak ada catatan khusus.';

            const actionWrap = document.getElementById('detailStatusActionContainer');
            if (d.status === 'Menunggu') {
                actionWrap.innerHTML = `
                    <button type="button" class="btn btn-primary fw-semibold px-4" id="btnDetailAdvanceStatus">
                        <i class="bi bi-truck me-1"></i> Mulai Pengiriman
                    </button>
                `;
                document.getElementById('btnDetailAdvanceStatus').addEventListener('click', () => {
                    promptStatusUpdate(d.id, d.shipment_code, 'Dalam pengiriman', () => bsDetailModal.hide());
                });
            } else if (d.status === 'Dalam pengiriman') {
                actionWrap.innerHTML = `
                    <button type="button" class="btn btn-success fw-semibold px-4" id="btnDetailAdvanceStatus">
                        <i class="bi bi-check2-circle me-1"></i> Selesaikan Pengiriman
                    </button>
                `;
                document.getElementById('btnDetailAdvanceStatus').addEventListener('click', () => {
                    promptStatusUpdate(d.id, d.shipment_code, 'Selesai dikirim', () => bsDetailModal.hide());
                });
            } else {
                actionWrap.innerHTML = `<span class="small text-success fw-semibold"><i class="bi bi-check-all me-1"></i>Pengiriman ini telah selesai dikirim</span>`;
            }

            bsDetailModal.show();
        } catch (err) {
            window.showToast({ title: 'Gagal', message: err.message, type: 'danger' });
        }
    }

    detailModalEl.addEventListener('shown.bs.modal', function () {
        if (!currentDetailRow || typeof L === 'undefined') return;
        const d = currentDetailRow;
        const whLat = parseFloat(d.warehouse_lat) || -6.943097;
        const whLng = parseFloat(d.warehouse_lng) || 107.633545;
        const cLat = parseFloat(d.customer_lat) || -6.917464;
        const cLng = parseFloat(d.customer_lng) || 107.619123;

        if (!detailMap) {
            detailMap = L.map('shipmentDetailMap').setView([whLat, whLng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(detailMap);
            detailLayerGroup = L.layerGroup().addTo(detailMap);
        }

        detailMap.invalidateSize();
        detailLayerGroup.clearLayers();

        const whIcon = window.createMapPinIcon('#DC2626', 'bi-building-fill');
        const custIcon = window.createMapPinIcon('#2563EB', 'bi-geo-alt-fill');

        L.marker([whLat, whLng], { icon: whIcon })
            .bindPopup(`<strong>${window.escapeHtml(d.warehouse_name)}</strong><br><small>Titik Awal Gudang</small>`)
            .addTo(detailLayerGroup);

        L.marker([cLat, cLng], { icon: custIcon })
            .bindPopup(`<strong>${window.escapeHtml(d.customer_name)}</strong><br><small>${window.escapeHtml(d.customer_address)}</small>`)
            .addTo(detailLayerGroup);

        let geojsonObj = null;
        if (d.route_geometry) {
            try {
                geojsonObj = JSON.parse(d.route_geometry);
            } catch (e) {
                geojsonObj = null;
            }
        }
        if (!geojsonObj) {
            geojsonObj = {
                type: 'LineString',
                coordinates: [[whLng, whLat], [cLng, cLat]]
            };
        }

        const routeLayer = L.geoJSON(geojsonObj, {
            style: { color: '#2563EB', weight: 5, opacity: 0.9 }
        }).addTo(detailLayerGroup);

        const bounds = routeLayer.getBounds();
        bounds.extend([whLat, whLng]);
        bounds.extend([cLat, cLng]);
        if (bounds.isValid()) {
            detailMap.fitBounds(bounds, { padding: [30, 30] });
        }
    });

    // Delegasi Aksi Baris Tabel (Detail, Quick Status, Edit, Hapus)
    tableBody.addEventListener('click', async function (e) {
        const quickBtn = e.target.closest('.btn-quick-status');
        if (quickBtn) {
            promptStatusUpdate(quickBtn.dataset.id, quickBtn.dataset.code, quickBtn.dataset.nextStatus);
            return;
        }

        const detailBtn = e.target.closest('.btn-detail-shipment');
        if (detailBtn) {
            openShipmentDetailModal(detailBtn.dataset.id);
            return;
        }

        const editBtn = e.target.closest('.btn-edit-shipment');
        if (editBtn) {
            const id = editBtn.dataset.id;
            try {
                const res = await fetch(`${apiUrl}?action=detail&id=${encodeURIComponent(id)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal mengambil data pengiriman.');
                }
                const d = json.data;
                editingOriginalProductId = Number(d.product_id);
                editingOriginalQuantity = Number(d.quantity);

                document.getElementById('shipmentId').value = d.id;
                document.getElementById('shipmentFormAction').value = 'update';
                document.getElementById('shipmentCode').value = d.shipment_code;
                document.getElementById('shipmentDate').value = d.shipment_date;
                document.getElementById('shipmentDriverName').value = d.driver_name;
                distInput.value = Number(d.distance_km).toFixed(2);
                durInput.value = d.duration_minutes;
                geomInput.value = d.route_geometry || '';
                document.getElementById('shipmentNotes').value = d.notes || '';

                suppressNextCustomerRouteFetch = true;
                window.setCustomDropdownValue('modalShipmentCustomerDropdown', String(d.customer_id), false);
                syncProductDropdownOptions(d.product_id);
                window.setCustomDropdownValue('modalShipmentProductDropdown', String(d.product_id), false);
                window.setCustomDropdownValue('modalShipmentVehicleDropdown', d.vehicle_type, false);
                window.setCustomDropdownValue('modalShipmentStatusDropdown', d.status, false);

                quantityInput.value = d.quantity;
                recalculateWeightAndValidateStock();

                modalTitle.textContent = `Edit Pengiriman (${d.shipment_code})`;
                bsModal.show();
            } catch (err) {
                window.showToast({ title: 'Gagal', message: err.message, type: 'danger' });
            }
            return;
        }

        const delBtn = e.target.closest('.btn-delete-shipment');
        if (delBtn) {
            const id = delBtn.dataset.id;
            const code = delBtn.dataset.code;
            const status = delBtn.dataset.status;
            const stockNote = status === 'Menunggu'
                ? ' Stok barang pada pengiriman ini akan otomatis dikembalikan ke gudang.'
                : '';

            window.showConfirmModal({
                title: 'Hapus Jadwal Pengiriman?',
                message: `Apakah Anda yakin ingin menghapus data pengiriman ${code}?${stockNote}`,
                confirmText: 'Ya, Hapus',
                cancelText: 'Batal',
                variant: 'danger',
                onConfirm: async () => {
                    try {
                        const res = await fetch(apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ action: 'delete', id: id })
                        });
                        const json = await res.json();
                        if (!res.ok || json.status !== 'success') {
                            throw new Error(json.message || 'Gagal menghapus pengiriman.');
                        }
                        window.showToast({ title: 'Berhasil Dihapus', message: json.message, type: 'success' });
                        loadShipments(currentPage);
                    } catch (err) {
                        window.showToast({ title: 'Gagal Menghapus', message: err.message, type: 'danger' });
                    }
                }
            });
        }
    });

    // Submit Form Tambah / Edit Pengiriman
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!recalculateWeightAndValidateStock()) {
            window.showToast({
                title: 'Stok Tidak Mencukupi',
                message: 'Jumlah muatan melebihi stok barang yang tersedia di gudang.',
                type: 'warning'
            });
            quantityInput.focus();
            return;
        }

        saveBtn.disabled = true;
        const origHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

        try {
            const formData = new FormData(form);
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });
            const json = await res.json();

            if (!res.ok || json.status !== 'success') {
                throw new Error(json.message || 'Gagal menyimpan jadwal pengiriman.');
            }

            bsModal.hide();
            window.showToast({ title: 'Berhasil Disimpan', message: json.message, type: 'success' });
            loadShipments(currentPage);
        } catch (err) {
            window.showToast({ title: 'Gagal Menyimpan', message: err.message, type: 'danger' });
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    });

    // Muat data awal & buka modal otomatis jika diarahkan dari halaman Peta Rute OSRM
    loadShipments(1).then(() => {
        if (autoOpenCreate || prefillCustId > 0) {
            let storedGeometry = null;
            try {
                const cached = JSON.parse(sessionStorage.getItem('last_calculated_route') || 'null');
                if (cached && Number(cached.customer_id) === prefillCustId && cached.geometry) {
                    storedGeometry = cached.geometry;
                }
            } catch (e) {}

            openCreateShipmentModal({
                customer_id: prefillCustId,
                distance_km: prefillDistKm,
                duration_minutes: prefillDurMin,
                geometry: storedGeometry
            });
        }
    });
});
</script>
