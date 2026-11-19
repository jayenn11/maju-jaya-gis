<?php
/**
 * Halaman Peta Pengiriman Interaktif & Mesin Perutean Jaringan Jalan (OSRM Routing API)
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 *
 * Mematuhi standar AGENTS.md:
 * - Custom Searchable Dropdown (0 elemen <select> bawaan browser)
 * - Builder Tujuan Single & Multi-Stop (Tambah, Naik, Turun, Hapus, Cegah Duplikat)
 * - Integrasi OSRM Public API via api/routing.php + Fallback Haversine Otomatis
 * - Toggle Overlay Pengiriman Aktif ('Dalam pengiriman') dari database
 */

// 1. Ambil seluruh titik pelanggan untuk marker peta & opsi dropdown tujuan
$customersStmt = $pdo->query('
    SELECT id, code, name, category, pic_name, phone, address, latitude, longitude
    FROM customers
    ORDER BY code ASC
');
$allCustomers = $customersStmt->fetchAll();

// 2. Ambil pengiriman yang sedang berjalan ('Dalam pengiriman') untuk Overlay Peta
$activeShipmentsStmt = $pdo->query("
    SELECT s.id,
           s.shipment_code,
           s.customer_id,
           s.quantity,
           s.total_weight,
           s.vehicle_type,
           s.driver_name,
           s.distance_km,
           s.duration_minutes,
           s.route_geometry,
           s.shipment_date,
           s.notes,
           c.code AS customer_code,
           c.name AS customer_name,
           c.category AS customer_category,
           c.address AS customer_address,
           c.latitude AS customer_lat,
           c.longitude AS customer_lng,
           p.code AS product_code,
           p.name AS product_name,
           p.unit AS product_unit
    FROM shipments s
    INNER JOIN customers c ON s.customer_id = c.id
    INNER JOIN products p ON s.product_id = p.id
    WHERE s.status = 'Dalam pengiriman'
    ORDER BY s.id DESC
");
$activeShipmentsOverlay = $activeShipmentsStmt->fetchAll();

// Preselect pelanggan jika halaman dibuka dengan ?customer_id=...
$preselectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
?>

<div class="routing-workspace">
    <!-- ===================================================================
         PANEL KIRI: KONTROL RUTE & HASIL KALKULASI OSRM (~390px)
         =================================================================== -->
    <div class="d-flex flex-column gap-3">
        <!-- Kartu Builder Rute -->
        <div class="routing-sidebar-card p-3 p-lg-4">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-sign-turn-right-fill fs-5"></i>
                    </div>
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-0">Perencana Rute Distribusi</h2>
                        <small class="text-secondary">OSRM Road Network Routing</small>
                    </div>
                </div>
                <span class="badge badge-soft-primary">Single / Multi-Stop</span>
            </div>

            <!-- Informasi Titik Awal: Gudang Utama -->
            <div class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge bg-danger text-white">
                        <i class="bi bi-building-fill me-1"></i>Titik Awal (Origin)
                    </span>
                    <a href="<?= e(base_url('index.php?page=gudang')) ?>" class="small fw-semibold text-danger text-decoration-none">
                        <i class="bi bi-pencil-square me-1"></i>Ubah Lokasi
                    </a>
                </div>
                <div class="fw-bold text-dark small mt-1"><?= e($activeWarehouse['name']) ?></div>
                <div class="text-secondary" style="font-size: 0.78rem;"><?= e($activeWarehouse['address']) ?></div>
                <div class="font-monospace text-muted mt-1" style="font-size: 0.74rem;">
                    Koordinat: <?= number_format((float)$activeWarehouse['latitude'], 6, '.', '') ?>, <?= number_format((float)$activeWarehouse['longitude'], 6, '.', '') ?>
                </div>
            </div>

            <!-- Builder Tujuan Pengiriman (Stop 1, Stop 2, dst.) -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label small fw-bold text-dark mb-0">
                        <i class="bi bi-geo-alt-fill text-primary me-1"></i>Urutan Tujuan Pelanggan
                    </label>
                    <span class="small text-secondary" id="stopsCountBadge">1 Tujuan</span>
                </div>

                <div id="routeStopsContainer" class="d-flex flex-column gap-2"></div>

                <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2 fw-semibold py-2 d-flex align-items-center justify-content-center gap-1" id="btnAddStop">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>+ Tambah Tujuan Berikutnya</span>
                </button>
            </div>

            <!-- Tombol Utama Kalkulasi & Reset -->
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-primary fw-semibold py-2 d-flex align-items-center justify-content-center gap-2" id="btnCalculateRoute">
                    <i class="bi bi-calculator-fill"></i>
                    <span>Hitung Rute &amp; Estimasi Waktu</span>
                </button>
                <button type="button" class="btn btn-light border fw-semibold py-2 text-secondary d-flex align-items-center justify-content-center gap-2" id="btnResetRoute">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset Peta</span>
                </button>
            </div>
        </div>

        <!-- Kartu Hasil Perhitungan Rute (Muncul setelah kalkulasi) -->
        <div class="routing-sidebar-card p-3 p-lg-4 d-none" id="routeResultCard">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                <div>
                    <h3 class="h6 fw-bold text-dark mb-0">Hasil Perhitungan Rute</h3>
                    <small class="text-secondary" id="routeResultSubtitle">Ringkasan jarak &amp; waktu tempuh</small>
                </div>
                <span class="badge badge-soft-success" id="routeSourceBadge">OSRM API</span>
            </div>

            <!-- Alert Pemberitahuan jika Mode Fallback Haversine Aktif -->
            <div class="alert alert-warning d-none align-items-start gap-2 py-2 px-3 small rounded-3 mb-3" id="routeFallbackAlert" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
                <div id="routeFallbackAlertText">
                    Layanan OSRM sedang mengalami gangguan jaringan. Menampilkan estimasi cadangan berbasis rumus Haversine (×1.25).
                </div>
            </div>

            <!-- 2 Kotak Metrik Utama: Jarak (km) & Estimasi Waktu (menit) -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 text-center">
                        <div class="small text-secondary fw-semibold mb-1">Total Jarak Tempuh</div>
                        <div class="h4 fw-bold text-primary mb-0" id="resultTotalDistance">0.00 km</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-3 bg-info bg-opacity-10 border border-info border-opacity-25 text-center">
                        <div class="small text-secondary fw-semibold mb-1">Estimasi Waktu</div>
                        <div class="h4 fw-bold text-dark mb-0" id="resultTotalDuration">0 mnt</div>
                    </div>
                </div>
            </div>

            <!-- Rincian Segmen Rute (Legs) -->
            <div class="mb-3">
                <div class="small fw-bold text-dark mb-2">
                    <i class="bi bi-list-ol text-primary me-1"></i>Rincian Segmen Perjalanan
                </div>
                <div id="routeLegsList" class="d-flex flex-column gap-2"></div>
            </div>

            <!-- Tombol Aksi Langsung: Buat Jadwal Pengiriman dari Rute Ini -->
            <div class="pt-2 border-top">
                <a href="<?= e(base_url('index.php?page=pengiriman')) ?>" id="btnCreateShipmentFromRoute" class="btn btn-success w-100 fw-semibold py-2 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-send-check-fill"></i>
                    <span>Buat Jadwal Pengiriman dari Rute Ini</span>
                </a>
                <div class="text-center text-muted mt-1" style="font-size: 0.74rem;" id="createShipmentHint">
                    Meneruskan tujuan, jarak (km), &amp; waktu tempuh ke Manajemen Pengiriman
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         PANEL KANAN: PETA INTERAKTIF FULL-HEIGHT LEAFLET.JS
         =================================================================== -->
    <div class="content-card p-3 p-lg-4">
        <!-- Toolbar Atas Peta: Legenda & Toggle Overlay Pengiriman Aktif -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-2 border-bottom">
            <div class="d-flex flex-wrap align-items-center gap-2 small">
                <span class="fw-bold text-dark me-1"><i class="bi bi-layers-fill text-primary me-1"></i>Legenda:</span>
                <span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-building-fill me-1"></i>Gudang Utama</span>
                <span class="badge badge-soft-primary px-2 py-1"><i class="bi bi-shop me-1"></i>Toko</span>
                <span class="badge badge-soft-info px-2 py-1"><i class="bi bi-cart-check-fill me-1"></i>Minimarket</span>
                <span class="badge badge-soft-purple px-2 py-1"><i class="bi bi-cup-hot-fill me-1"></i>Restoran</span>
                <span class="badge bg-primary text-white px-2 py-1"><i class="bi bi-bezier2 me-1"></i>Rute OSRM</span>
            </div>

            <!-- Toggle Overlay Pengiriman Aktif -->
            <div class="d-flex align-items-center gap-3">
                <label class="form-check form-switch mb-0 d-inline-flex align-items-center gap-2 small fw-semibold text-dark" style="cursor: pointer;">
                    <input class="form-check-input" type="checkbox" switch role="switch" id="toggleActiveShipmentsOverlay" checked>
                    <span>
                        <i class="bi bi-truck text-success me-1"></i>Overlay Pengiriman Aktif
                        <span class="badge badge-soft-success ms-1"><?= count($activeShipmentsOverlay) ?></span>
                    </span>
                </label>

                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnFitAllBandung" title="Tampilkan Seluruh Titik Bandung">
                    <i class="bi bi-arrows-fullscreen me-1"></i>Semua Titik
                </button>
            </div>
        </div>

        <!-- Container Peta Interaktif -->
        <div id="routingMap" class="map-routing-full"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const routingApiUrl = '<?= e(base_url('api/routing.php')) ?>';
    const shipmentPageUrl = '<?= e(base_url('index.php?page=pengiriman')) ?>';
    const warehouseData = <?= json_encode($activeWarehouse, JSON_UNESCAPED_UNICODE) ?>;
    const customersData = <?= json_encode($allCustomers, JSON_UNESCAPED_UNICODE) ?>;
    const activeShipmentsData = <?= json_encode($activeShipmentsOverlay, JSON_UNESCAPED_UNICODE) ?>;
    const preselectedCustomerId = <?= (int)$preselectedCustomerId ?>;

    const stopsContainer = document.getElementById('routeStopsContainer');
    const stopsCountBadge = document.getElementById('stopsCountBadge');
    const btnAddStop = document.getElementById('btnAddStop');
    const btnCalculateRoute = document.getElementById('btnCalculateRoute');
    const btnResetRoute = document.getElementById('btnResetRoute');
    const btnFitAllBandung = document.getElementById('btnFitAllBandung');
    const toggleActiveOverlay = document.getElementById('toggleActiveShipmentsOverlay');

    const resultCard = document.getElementById('routeResultCard');
    const resultSubtitle = document.getElementById('routeResultSubtitle');
    const sourceBadge = document.getElementById('routeSourceBadge');
    const fallbackAlert = document.getElementById('routeFallbackAlert');
    const fallbackAlertText = document.getElementById('routeFallbackAlertText');
    const totalDistEl = document.getElementById('resultTotalDistance');
    const totalDurEl = document.getElementById('resultTotalDuration');
    const legsListEl = document.getElementById('routeLegsList');
    const btnCreateShipment = document.getElementById('btnCreateShipmentFromRoute');
    const createShipmentHint = document.getElementById('createShipmentHint');

    // =========================================================================
    // 1. INISIALISASI PETA LEAFLET.JS KOTA BANDUNG
    // =========================================================================
    const whLat = parseFloat(warehouseData.latitude) || -6.943097;
    const whLng = parseFloat(warehouseData.longitude) || 107.633545;

    const map = L.map('routingMap').setView([whLat, whLng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const allPointsBounds = L.latLngBounds();
    const customerMarkersGroup = L.layerGroup().addTo(map);
    const activeShipmentsLayerGroup = L.layerGroup().addTo(map);
    const calculatedRouteLayerGroup = L.layerGroup().addTo(map);
    const numberedStopMarkersGroup = L.layerGroup().addTo(map);

    // Marker Gudang Utama
    const warehouseIcon = window.createMapPinIcon('#DC2626', 'bi-building-fill');
    const whMarker = L.marker([whLat, whLng], { icon: warehouseIcon, zIndexOffset: 900 }).addTo(map);
    whMarker.bindPopup(`
        <div style="min-width: 220px;">
            <span class="badge bg-danger mb-1">Titik Awal Distribusi (Gudang)</span>
            <div class="fw-bold text-dark">${window.escapeHtml(warehouseData.name)}</div>
            <div class="small text-secondary mt-1"><i class="bi bi-geo-alt me-1"></i>${window.escapeHtml(warehouseData.address)}</div>
            <div class="small text-muted font-monospace mt-1">${whLat.toFixed(6)}, ${whLng.toFixed(6)}</div>
        </div>
    `);
    allPointsBounds.extend([whLat, whLng]);

    // Marker Seluruh Pelanggan
    const categoryStyle = {
        'Toko':       { color: '#2563EB', icon: 'bi-shop' },
        'Minimarket': { color: '#0284C7', icon: 'bi-cart-check-fill' },
        'Restoran':   { color: '#7C3AED', icon: 'bi-cup-hot-fill' }
    };

    const customerMarkersById = {};
    customersData.forEach(c => {
        const lat = parseFloat(c.latitude);
        const lng = parseFloat(c.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        const style = categoryStyle[c.category] || categoryStyle['Toko'];
        const pinIcon = window.createMapPinIcon(style.color, style.icon);
        const marker = L.marker([lat, lng], { icon: pinIcon }).addTo(customerMarkersGroup);

        marker.bindPopup(`
            <div style="min-width: 230px;">
                <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                        ${window.escapeHtml(c.code)} &bull; ${window.escapeHtml(c.category)}
                    </span>
                </div>
                <div class="fw-bold text-dark">${window.escapeHtml(c.name)}</div>
                <div class="small text-secondary mt-1"><i class="bi bi-geo-alt me-1"></i>${window.escapeHtml(c.address)}</div>
                <div class="small text-secondary mt-1"><i class="bi bi-person me-1"></i>${window.escapeHtml(c.pic_name)} (${window.escapeHtml(c.phone)})</div>
                <div class="small text-muted font-monospace mt-1 mb-2">${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
                <button type="button" class="btn btn-sm btn-primary w-100 fw-semibold btn-popup-set-destination" data-customer-id="${c.id}">
                    <i class="bi bi-sign-turn-right-fill me-1"></i>Jadikan Tujuan Rute
                </button>
            </div>
        `);

        customerMarkersById[c.id] = marker;
        allPointsBounds.extend([lat, lng]);
    });

    if (allPointsBounds.isValid()) {
        map.fitBounds(allPointsBounds, { padding: [40, 40] });
    }

    // =========================================================================
    // 2. OVERLAY RUTE PENGIRIMAN AKTIF ('Dalam pengiriman')
    // =========================================================================
    function renderActiveShipmentsOverlay() {
        activeShipmentsLayerGroup.clearLayers();
        if (!toggleActiveOverlay || !toggleActiveOverlay.checked) return;

        activeShipmentsData.forEach((ship, idx) => {
            let geojsonObj = null;
            if (ship.route_geometry) {
                try {
                    geojsonObj = JSON.parse(ship.route_geometry);
                } catch (e) {
                    geojsonObj = null;
                }
            }

            if (!geojsonObj) {
                const cLat = parseFloat(ship.customer_lat);
                const cLng = parseFloat(ship.customer_lng);
                if (!isNaN(cLat) && !isNaN(cLng)) {
                    geojsonObj = {
                        type: 'LineString',
                        coordinates: [[whLng, whLat], [cLng, cLat]]
                    };
                }
            }

            if (geojsonObj) {
                const colors = ['#10B981', '#F59E0B', '#06B6D4', '#EC4899'];
                const strokeColor = colors[idx % colors.length];

                const layer = L.geoJSON(geojsonObj, {
                    style: {
                        color: strokeColor,
                        weight: 4,
                        opacity: 0.85,
                        dashArray: '8, 8'
                    }
                }).addTo(activeShipmentsLayerGroup);

                layer.bindPopup(`
                    <div style="min-width: 225px;">
                        <span class="badge badge-soft-primary mb-1">
                            <i class="bi bi-truck me-1"></i>Pengiriman Aktif: ${window.escapeHtml(ship.shipment_code)}
                        </span>
                        <div class="fw-bold text-dark">Tujuan: ${window.escapeHtml(ship.customer_name)}</div>
                        <div class="small text-secondary mt-1">Barang: ${window.escapeHtml(ship.product_name)} (${ship.quantity} ${window.escapeHtml(ship.product_unit)})</div>
                        <div class="small text-secondary">Armada: ${window.escapeHtml(ship.vehicle_type)} &bull; Sopir: <strong>${window.escapeHtml(ship.driver_name)}</strong></div>
                        <div class="small fw-semibold text-primary mt-1">
                            Jarak: ${Number(ship.distance_km).toFixed(2)} km &bull; Estimasi: ${ship.duration_minutes} menit
                        </div>
                    </div>
                `);
            }
        });
    }

    if (toggleActiveOverlay) {
        toggleActiveOverlay.addEventListener('change', renderActiveShipmentsOverlay);
    }
    renderActiveShipmentsOverlay();

    // =========================================================================
    // 3. BUILDER TUJUAN PENGIRIMAN (SINGLE & MULTI-STOP + ANTI DUPLIKAT)
    // =========================================================================
    let stopsState = []; // Array of selected customer IDs (string, e.g. ['1', '3'])

    function buildCustomerOptionsForStop(currentStopIndex) {
        const currentSelectedVal = String(stopsState[currentStopIndex] || '');
        const otherSelectedIds = new Set(
            stopsState
                .filter((val, idx) => idx !== currentStopIndex && val !== '')
                .map(val => String(val))
        );

        const opts = [];
        customersData.forEach(c => {
            const idStr = String(c.id);
            // Cegah pemilihan pelanggan yang sama pada baris tujuan lain
            if (otherSelectedIds.has(idStr) && idStr !== currentSelectedVal) {
                return;
            }
            opts.push({
                value: idStr,
                label: `${c.code} — ${c.name}`,
                sub: `${c.address} ${c.category}`,
                badge: c.category
            });
        });
        return opts;
    }

    function renderStopsBuilder() {
        stopsContainer.innerHTML = '';

        stopsState.forEach((selectedId, idx) => {
            const stopCard = document.createElement('div');
            stopCard.className = 'stop-item-card';
            const stopNumber = idx + 1;
            const totalStops = stopsState.length;

            const optionsJson = JSON.stringify(buildCustomerOptionsForStop(idx));

            stopCard.innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="stop-number-badge">${stopNumber}</span>
                        <span class="small fw-bold text-dark">Tujuan Stop #${stopNumber}</span>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Kontrol Urutan Stop">
                        <button type="button" class="btn btn-light border py-0 px-2 btn-move-stop-up" data-index="${idx}" ${idx === 0 ? 'disabled' : ''} title="Geser Naik">
                            <i class="bi bi-arrow-up-short"></i>
                        </button>
                        <button type="button" class="btn btn-light border py-0 px-2 btn-move-stop-down" data-index="${idx}" ${idx === totalStops - 1 ? 'disabled' : ''} title="Geser Turun">
                            <i class="bi bi-arrow-down-short"></i>
                        </button>
                        <button type="button" class="btn btn-light border text-danger py-0 px-2 btn-remove-stop" data-index="${idx}" ${totalStops <= 1 ? 'disabled' : ''} title="Hapus Tujuan">
                            <i class="bi bi-x-lg" style="font-size: 0.72rem;"></i>
                        </button>
                    </div>
                </div>
                <div class="custom-dropdown stop-customer-dropdown"
                     data-custom-dropdown
                     data-stop-index="${idx}"
                     data-name="stop_customer_${idx}"
                     data-placeholder="Pilih Pelanggan Tujuan #${stopNumber}..."
                     data-search-placeholder="Ketik nama, kode, atau alamat...">
                    <input type="hidden" name="stop_customer_${idx}" value="${window.escapeHtml(selectedId)}">
                    <script type="application/json" class="dropdown-options">${optionsJson}<\/script>
                </div>
            `;

            stopsContainer.appendChild(stopCard);
        });

        window.initCustomDropdown(stopsContainer);

        // Bind event ketika pilihan dropdown berubah
        stopsContainer.querySelectorAll('.stop-customer-dropdown').forEach(dd => {
            dd.addEventListener('dropdown:change', function (e) {
                const idx = parseInt(this.dataset.stopIndex, 10);
                const val = String(e.detail.value || '');
                stopsState[idx] = val;
                syncAllStopDropdownOptions();
            });
        });

        stopsCountBadge.textContent = `${stopsState.length} Tujuan`;
        btnAddStop.disabled = stopsState.length >= customersData.length;
    }

    function syncAllStopDropdownOptions() {
        const dropdownEls = stopsContainer.querySelectorAll('.stop-customer-dropdown');
        dropdownEls.forEach(dd => {
            const idx = parseInt(dd.dataset.stopIndex, 10);
            const currentVal = String(stopsState[idx] || '');
            const allowedOpts = buildCustomerOptionsForStop(idx);
            window.updateCustomDropdownOptions(dd, allowedOpts, currentVal);
        });
    }

    // Event delegasi tombol Naik / Turun / Hapus Stop
    stopsContainer.addEventListener('click', function (e) {
        const upBtn = e.target.closest('.btn-move-stop-up');
        if (upBtn) {
            const idx = parseInt(upBtn.dataset.index, 10);
            if (idx > 0) {
                const temp = stopsState[idx - 1];
                stopsState[idx - 1] = stopsState[idx];
                stopsState[idx] = temp;
                renderStopsBuilder();
            }
            return;
        }

        const downBtn = e.target.closest('.btn-move-stop-down');
        if (downBtn) {
            const idx = parseInt(downBtn.dataset.index, 10);
            if (idx < stopsState.length - 1) {
                const temp = stopsState[idx + 1];
                stopsState[idx + 1] = stopsState[idx];
                stopsState[idx] = temp;
                renderStopsBuilder();
            }
            return;
        }

        const removeBtn = e.target.closest('.btn-remove-stop');
        if (removeBtn) {
            const idx = parseInt(removeBtn.dataset.index, 10);
            if (stopsState.length > 1) {
                stopsState.splice(idx, 1);
                renderStopsBuilder();
            }
        }
    });

    btnAddStop.addEventListener('click', function () {
        if (stopsState.length >= customersData.length) {
            window.showToast({
                title: 'Maksimum Tujuan Tercapai',
                message: 'Seluruh titik pelanggan di Bandung sudah ditambahkan ke dalam daftar tujuan.',
                type: 'warning'
            });
            return;
        }
        stopsState.push('');
        renderStopsBuilder();
    });

    // Klik tombol "Jadikan Tujuan Rute" dari Popup Marker Peta
    document.addEventListener('click', function (e) {
        const popupBtn = e.target.closest('.btn-popup-set-destination');
        if (!popupBtn) return;

        const cid = String(popupBtn.dataset.customerId || '');
        if (!cid) return;

        if (stopsState.includes(cid)) {
            window.showToast({
                title: 'Tujuan Sudah Dipilih',
                message: 'Pelanggan ini sudah berada di dalam urutan tujuan rute.',
                type: 'info'
            });
            return;
        }

        const emptyIdx = stopsState.findIndex(v => v === '');
        if (emptyIdx !== -1) {
            stopsState[emptyIdx] = cid;
        } else {
            stopsState.push(cid);
        }

        map.closePopup();
        renderStopsBuilder();
        calculateAndRenderRoute();
    });

    // =========================================================================
    // 4. KALKULASI RUTE OSRM & VISUALISASI POLYLINE + MARKER BERNOMOR
    // =========================================================================
    async function calculateAndRenderRoute() {
        const selectedCustomerIds = stopsState.filter(v => v !== '');
        if (selectedCustomerIds.length === 0) {
            window.showToast({
                title: 'Pilih Pelanggan Tujuan',
                message: 'Silakan pilih minimal 1 pelanggan tujuan pengiriman terlebih dahulu.',
                type: 'warning'
            });
            return;
        }

        btnCalculateRoute.disabled = true;
        const origHtml = btnCalculateRoute.innerHTML;
        btnCalculateRoute.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><span>Menghitung Rute OSRM...</span>';

        try {
            const res = await fetch(routingApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    warehouse_id: warehouseData.id,
                    customer_ids: selectedCustomerIds
                })
            });

            const json = await res.json();
            if (!res.ok || json.status !== 'success') {
                throw new Error(json.message || 'Gagal menghitung rute pengiriman.');
            }

            renderRouteOnMapAndPanel(json);
        } catch (err) {
            window.showToast({
                title: 'Gagal Menghitung Rute',
                message: err.message,
                type: 'danger'
            });
        } finally {
            btnCalculateRoute.disabled = false;
            btnCalculateRoute.innerHTML = origHtml;
        }
    }

    function renderRouteOnMapAndPanel(data) {
        calculatedRouteLayerGroup.clearLayers();
        numberedStopMarkersGroup.clearLayers();

        // 1. Gambar GeoJSON LineString dengan outline kontras + garis biru utama #2563EB
        if (data.geometry) {
            const isFallback = data.source === 'fallback';

            // Outline putih tebal agar kontras di atas jalan OpenStreetMap
            L.geoJSON(data.geometry, {
                style: {
                    color: '#FFFFFF',
                    weight: 9,
                    opacity: 0.9,
                    lineCap: 'round',
                    lineJoin: 'round'
                }
            }).addTo(calculatedRouteLayerGroup);

            // Garis rute utama (#2563EB untuk OSRM, #D97706 putus-putus untuk Fallback)
            const mainRouteLayer = L.geoJSON(data.geometry, {
                style: {
                    color: isFallback ? '#D97706' : '#2563EB',
                    weight: 5,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round',
                    dashArray: isFallback ? '10, 8' : null
                }
            }).addTo(calculatedRouteLayerGroup);

            mainRouteLayer.bindPopup(`
                <div style="min-width: 210px;">
                    <span class="badge ${isFallback ? 'badge-soft-warning' : 'badge-soft-primary'} mb-1">
                        ${isFallback ? 'Estimasi Rute Haversine' : 'Jalur Rute Jalan Raya OSRM'}
                    </span>
                    <div class="fw-bold text-dark">Total Jarak: ${Number(data.total_distance_km).toFixed(2)} km</div>
                    <div class="small text-secondary">Estimasi Waktu Tempuh: ${data.total_duration_minutes} menit</div>
                    <div class="small text-muted mt-1">Jumlah Titik Tujuan: ${(data.stops || []).length} Stop</div>
                </div>
            `);
        }

        // 2. Tambahkan Marker Bernomor Urutan Tujuan (1, 2, 3, ...)
        const routeBounds = L.latLngBounds([[whLat, whLng]]);
        (data.stops || []).forEach(stop => {
            const sLat = parseFloat(stop.latitude);
            const sLng = parseFloat(stop.longitude);
            if (isNaN(sLat) || isNaN(sLng)) return;

            routeBounds.extend([sLat, sLng]);

            const numberedIcon = L.divIcon({
                className: 'bg-transparent border-0',
                html: `<div class="route-stop-number-pin" title="Stop #${stop.stop_number}: ${window.escapeHtml(stop.name)}">${stop.stop_number}</div>`,
                iconSize: [34, 34],
                iconAnchor: [17, 17],
                popupAnchor: [0, -18]
            });

            const numMarker = L.marker([sLat, sLng], {
                icon: numberedIcon,
                zIndexOffset: 1000 + Number(stop.stop_number)
            }).addTo(numberedStopMarkersGroup);

            numMarker.bindPopup(`
                <div style="min-width: 220px;">
                    <span class="badge bg-primary text-white mb-1">Stop Tujuan #${stop.stop_number}</span>
                    <div class="fw-bold text-dark">${window.escapeHtml(stop.code)} — ${window.escapeHtml(stop.name)}</div>
                    <div class="small text-secondary mt-1"><i class="bi bi-geo-alt me-1"></i>${window.escapeHtml(stop.address)}</div>
                    <div class="small text-secondary"><i class="bi bi-person me-1"></i>PIC: ${window.escapeHtml(stop.pic_name)} (${window.escapeHtml(stop.phone)})</div>
                </div>
            `);
        });

        if (routeBounds.isValid()) {
            map.fitBounds(routeBounds, { padding: [55, 55] });
        }

        // 3. Tampilkan Kartu Hasil Kalkulasi di Panel Kiri
        resultCard.classList.remove('d-none');
        const stopsCount = (data.stops || []).length;
        resultSubtitle.textContent = `${stopsCount} titik tujuan dari Gudang Utama`;

        if (data.source === 'fallback') {
            sourceBadge.className = 'badge badge-soft-warning';
            sourceBadge.textContent = 'Fallback Haversine';
            fallbackAlert.classList.remove('d-none');
            fallbackAlert.classList.add('d-flex');
            fallbackAlertText.textContent = data.message;
        } else {
            sourceBadge.className = 'badge badge-soft-success';
            sourceBadge.textContent = 'OSRM Routing API';
            fallbackAlert.classList.add('d-none');
            fallbackAlert.classList.remove('d-flex');
        }

        totalDistEl.textContent = `${Number(data.total_distance_km).toFixed(2)} km`;
        totalDurEl.textContent = `${data.total_duration_minutes} menit`;

        // 4. Render Rincian Segmen (Legs)
        const legs = data.legs || [];
        legsListEl.innerHTML = legs.map(leg => {
            const singleScheduleParams = new URLSearchParams({
                page: 'pengiriman',
                create: '1',
                customer_id: leg.to_customer_id,
                distance_km: Number(leg.distance_km).toFixed(2),
                duration_minutes: leg.duration_minutes
            });

            return `
                <div class="route-leg-item">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="badge bg-primary text-white">Segmen #${leg.leg_number}</span>
                        <span class="small fw-bold text-dark">
                            ${Number(leg.distance_km).toFixed(2)} km &bull; ${leg.duration_minutes} mnt
                        </span>
                    </div>
                    <div class="small fw-semibold text-dark">
                        ${window.escapeHtml(leg.from_name)} <i class="bi bi-arrow-right text-primary mx-1"></i> ${window.escapeHtml(leg.to_name)}
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.75rem;">
                        <i class="bi bi-signpost-2 me-1"></i>${window.escapeHtml(leg.via_roads || 'Jalur Jalan Raya Bandung')}
                    </div>
                    ${legs.length > 1 ? `
                        <div class="mt-2 pt-1 border-top d-flex justify-content-end">
                            <a href="<?= e(base_url('index.php')) ?>?${singleScheduleParams.toString()}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.74rem;">
                                <i class="bi bi-calendar-plus me-1"></i>Jadwalkan Segmen Ini
                            </a>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');

        // 5. Konfigurasi Tombol "Buat Jadwal Pengiriman dari Rute Ini"
        const firstStop = (data.stops || [])[0];
        if (firstStop) {
            // Simpan geometri rute di sessionStorage agar halaman Manajemen Pengiriman dapat menggunakannya
            try {
                sessionStorage.setItem('last_calculated_route', JSON.stringify({
                    customer_id: firstStop.id,
                    customer_ids: (data.stops || []).map(s => s.id),
                    distance_km: Number(data.total_distance_km).toFixed(2),
                    duration_minutes: data.total_duration_minutes,
                    geometry: data.geometry
                }));
            } catch (e) {}

            const urlParams = new URLSearchParams({
                page: 'pengiriman',
                create: '1',
                customer_id: firstStop.id,
                distance_km: Number(data.total_distance_km).toFixed(2),
                duration_minutes: data.total_duration_minutes
            });
            if (stopsCount > 1) {
                urlParams.set('multi_stops', (data.stops || []).map(s => s.id).join(','));
            }
            btnCreateShipment.setAttribute('href', `<?= e(base_url('index.php')) ?>?${urlParams.toString()}`);
            createShipmentHint.textContent = stopsCount === 1
                ? `Tujuan: ${firstStop.code} (${firstStop.name}) • ${Number(data.total_distance_km).toFixed(2)} km • ${data.total_duration_minutes} menit`
                : `Rute ${stopsCount} Tujuan (Utama: ${firstStop.name}) • Total ${Number(data.total_distance_km).toFixed(2)} km`;
        }
    }

    btnCalculateRoute.addEventListener('click', calculateAndRenderRoute);

    btnResetRoute.addEventListener('click', function () {
        stopsState = [''];
        renderStopsBuilder();
        calculatedRouteLayerGroup.clearLayers();
        numberedStopMarkersGroup.clearLayers();
        resultCard.classList.add('d-none');
        if (allPointsBounds.isValid()) {
            map.fitBounds(allPointsBounds, { padding: [40, 40] });
        }
    });

    if (btnFitAllBandung) {
        btnFitAllBandung.addEventListener('click', function () {
            if (allPointsBounds.isValid()) {
                map.fitBounds(allPointsBounds, { padding: [40, 40] });
            }
        });
    }

    // Inisialisasi awal Builder Stop
    if (preselectedCustomerId > 0 && customersData.some(c => Number(c.id) === preselectedCustomerId)) {
        stopsState = [String(preselectedCustomerId)];
        renderStopsBuilder();
        calculateAndRenderRoute();
    } else if (customersData.length > 0) {
        // Default pilih pelanggan pertama agar admin dapat langsung menguji klik Hitung Rute
        stopsState = [String(customersData[0].id)];
        renderStopsBuilder();
    } else {
        stopsState = [''];
        renderStopsBuilder();
    }
});
</script>
