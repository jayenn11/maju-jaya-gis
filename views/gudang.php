<?php
/**
 * Modul Pengaturan Lokasi Gudang Utama (Titik Awal Pengiriman)
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

$wh = $activeWarehouse;
?>

<div class="row g-4">
    <!-- Kolom Form Pengaturan Gudang -->
    <div class="col-lg-5">
        <div class="content-card p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="rounded-3 bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                    <i class="bi bi-building-fill-gear fs-4"></i>
                </div>
                <div>
                    <h2 class="h6 fw-bold text-dark mb-0">Konfigurasi Gudang Pusat</h2>
                    <small class="text-secondary">Titik awal perhitungan rute pengiriman OSRM</small>
                </div>
            </div>

            <form id="warehouseForm" novalidate>
                <input type="hidden" name="id" id="warehouseId" value="<?= (int)($wh['id'] ?? 1) ?>">

                <div class="mb-3">
                    <label for="warehouseName" class="form-label small fw-semibold text-secondary mb-1">Nama Gudang Distribusi <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="warehouseName" name="name" value="<?= e($wh['name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="warehouseAddress" class="form-label small fw-semibold text-secondary mb-1">Alamat Lengkap Gudang <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="warehouseAddress" name="address" rows="3" required><?= e($wh['address'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label for="warehouseLatitude" class="form-label small fw-semibold text-secondary mb-1">Latitude <span class="text-danger">*</span></label>
                        <input type="number" step="any" class="form-control font-monospace" id="warehouseLatitude" name="latitude" value="<?= e($wh['latitude'] ?? '-6.94309700') ?>" required>
                    </div>
                    <div class="col-6">
                        <label for="warehouseLongitude" class="form-label small fw-semibold text-secondary mb-1">Longitude <span class="text-danger">*</span></label>
                        <input type="number" step="any" class="form-control font-monospace" id="warehouseLongitude" name="longitude" value="<?= e($wh['longitude'] ?? '107.63354500') ?>" required>
                    </div>
                </div>

                <div class="p-3 rounded-3 bg-light border small text-secondary mb-4">
                    <div class="fw-semibold text-dark mb-1">
                        <i class="bi bi-info-circle-fill text-primary me-1"></i> Petunjuk Pemetaan Spasial
                    </div>
                    Anda dapat mengklik langsung pada peta di sebelah kanan atau menggeser marker merah untuk memperbarui koordinat <code>Latitude</code> dan <code>Longitude</code> Gudang Utama secara presisi.
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-semibold px-4 flex-grow-1" id="saveWarehouseBtn">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Lokasi
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="resetDefaultWarehouseBtn" title="Kembalikan ke Soekarno-Hatta No. 450">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Kolom Peta Interaktif Lokasi Gudang -->
    <div class="col-lg-7">
        <div class="content-card p-4 h-100 d-flex flex-column justify-content-between">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h6 fw-bold text-dark mb-1">
                        <i class="bi bi-pin-map-fill text-danger me-1"></i> Peta Interaktif Titik Awal Gudang (Bandung)
                    </h2>
                    <p class="text-secondary small mb-0">Klik lokasi pada peta atau seret marker merah untuk memindahkan titik gudang.</p>
                </div>
                <span class="badge bg-danger text-white px-3 py-2">
                    <i class="bi bi-geo-alt-fill me-1"></i> Titik Keberangkatan Armada
                </span>
            </div>

            <div id="warehouseMap" class="map-warehouse-box"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiUrl = '<?= e(base_url('api/warehouse.php')) ?>';
    const latInput = document.getElementById('warehouseLatitude');
    const lngInput = document.getElementById('warehouseLongitude');
    const nameInput = document.getElementById('warehouseName');
    const addressInput = document.getElementById('warehouseAddress');
    const form = document.getElementById('warehouseForm');
    const saveBtn = document.getElementById('saveWarehouseBtn');
    const resetBtn = document.getElementById('resetDefaultWarehouseBtn');

    const initLat = parseFloat(latInput.value) || -6.94309700;
    const initLng = parseFloat(lngInput.value) || 107.63354500;

    const map = L.map('warehouseMap').setView([initLat, initLng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const whIcon = window.createMapPinIcon('#DC2626', 'bi-building-fill');
    const marker = L.marker([initLat, initLng], {
        draggable: true,
        icon: whIcon
    }).addTo(map);

    function bindWarehousePopup(lat, lng) {
        marker.bindPopup(`
            <div style="min-width: 200px;">
                <span class="badge bg-danger mb-1">Titik Awal Distribusi</span>
                <div class="fw-bold text-dark">${window.escapeHtml(nameInput.value)}</div>
                <div class="small text-secondary mt-1">${window.escapeHtml(addressInput.value)}</div>
                <div class="small text-muted font-monospace mt-1">${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}</div>
            </div>
        `).openPopup();
    }

    bindWarehousePopup(initLat, initLng);

    map.on('click', function (e) {
        const { lat, lng } = e.latlng;
        marker.setLatLng([lat, lng]);
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
        bindWarehousePopup(lat, lng);
    });

    marker.on('dragend', function () {
        const pos = marker.getLatLng();
        latInput.value = pos.lat.toFixed(8);
        lngInput.value = pos.lng.toFixed(8);
        bindWarehousePopup(pos.lat, pos.lng);
    });

    [latInput, lngInput].forEach(inp => {
        inp.addEventListener('input', function () {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng]);
            }
        });
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            const defLat = -6.94309700;
            const defLng = 107.63354500;
            nameInput.value = 'Gudang Utama PT Maju Jaya Food';
            addressInput.value = 'Jl. Soekarno-Hatta No. 450, Batununggal, Kota Bandung, Jawa Barat';
            latInput.value = defLat.toFixed(8);
            lngInput.value = defLng.toFixed(8);
            marker.setLatLng([defLat, defLng]);
            map.setView([defLat, defLng], 14);
            bindWarehousePopup(defLat, defLng);
            window.showToast({
                title: 'Koordinat Default Dimuat',
                message: 'Titik koordinat dikembalikan ke Jl. Soekarno-Hatta No. 450. Klik Simpan untuk menerapkan.',
                type: 'info'
            });
        });
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
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
                throw new Error(json.message || 'Gagal memperbarui lokasi gudang.');
            }

            const d = json.data;
            const badgeEl = document.getElementById('topbarWarehouseBadge');
            if (badgeEl && d && d.name) {
                badgeEl.textContent = `${d.name} (Bandung)`;
            }

            bindWarehousePopup(d.latitude, d.longitude);
            window.showToast({
                title: 'Berhasil Disimpan',
                message: json.message,
                type: 'success'
            });
        } catch (err) {
            window.showToast({
                title: 'Gagal Menyimpan',
                message: err.message,
                type: 'danger'
            });
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    });
});
</script>
