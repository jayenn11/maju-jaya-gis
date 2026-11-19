<?php
/**
 * Modul Master Data Pelanggan (Toko, Minimarket, Restoran)
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 * Mematuhi standar AGENTS.md Bagian 4:
 * - Custom Searchable Dropdown (Tanpa <select> bawaan browser)
 * - Modal-First CRUD + overflow: visible pada .modal-content
 * - Interactive Map Coordinate Picker (Leaflet.js) di dalam Modal
 * - Live Search (debounce 300ms) + Auto-Filter tanpa tombol Cari
 * - Validasi nomor telepon hanya angka (frontend & backend)
 * - Tombol aksi text-nowrap & Light Pagination
 */
?>

<div class="content-card">
    <!-- Header Bar & Filter Otomatis -->
    <div class="p-3 p-lg-4 border-bottom">
        <div class="row g-3 align-items-center justify-content-between">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-secondary">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="customerSearchInput" class="form-control border-start-0 ps-0" placeholder="Ketik kode, nama pelanggan, PIC, atau alamat (Live Search)...">
                </div>
            </div>

            <div class="col-md-7 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                <!-- Filter Kategori (Custom Searchable Dropdown) -->
                <div style="width: 230px;">
                    <div class="custom-dropdown" id="filterCustomerCategoryDropdown" data-custom-dropdown data-name="filter_category" data-placeholder="Semua Kategori" data-search-placeholder="Cari kategori...">
                        <input type="hidden" id="filterCustomerCategoryInput" name="filter_category" value="Semua">
                        <script type="application/json" class="dropdown-options">
                            [
                                {"value": "Semua", "label": "Semua Kategori"},
                                {"value": "Toko", "label": "Toko"},
                                {"value": "Minimarket", "label": "Minimarket"},
                                {"value": "Restoran", "label": "Restoran"}
                            ]
                        </script>
                    </div>
                </div>

                <button type="button" class="btn btn-primary fw-semibold px-3 d-inline-flex align-items-center gap-2 text-nowrap" id="btnAddCustomer">
                    <i class="bi bi-plus-lg"></i>
                    <span>Tambah Pelanggan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Pelanggan -->
    <div class="table-scroll-container" tabindex="0" role="region" aria-label="Tabel Daftar Pelanggan">
        <table class="table table-gis">
            <thead>
                <tr>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama Pelanggan</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">PIC &amp; No. Telepon</th>
                    <th scope="col">Alamat Lengkap</th>
                    <th scope="col">Koordinat (Lat, Lng)</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody id="customerTableBody">
                <tr>
                    <td colspan="7" class="text-center py-5 text-secondary">
                        <span class="spinner-border spinner-border-sm text-primary me-2"></span>Memuat data pelanggan...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Footer Pagination Terang -->
    <div class="p-3 p-lg-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 bg-light bg-opacity-50">
        <div class="small text-secondary" id="customerPaginationInfo">Menampilkan 0 data pelanggan</div>
        <nav aria-label="Navigasi Halaman Pelanggan">
            <ul class="pagination pagination-sm pagination-light-theme mb-0" id="customerPaginationList"></ul>
        </nav>
    </div>
</div>

<!-- Modal Tambah & Edit Pelanggan (overflow: visible via CSS) -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0" id="customerModalTitle">Tambah Pelanggan Baru</h5>
                    <small class="text-secondary">Isi informasi pelanggan dan klik titik lokasi pada peta Bandung di bawah.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <form id="customerForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="customerId" name="id" value="">
                    <input type="hidden" id="customerFormAction" name="action" value="create">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="customerCode" class="form-label small fw-semibold text-secondary mb-1">Kode Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light fw-bold font-monospace" id="customerCode" name="code" readonly required>
                        </div>

                        <div class="col-md-8">
                            <label for="customerName" class="form-label small fw-semibold text-secondary mb-1">Nama Pelanggan (Toko/Minimarket/Restoran) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customerName" name="name" placeholder="Contoh: Toko Berkah Jaya" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary mb-1">Kategori Pelanggan <span class="text-danger">*</span></label>
                            <div class="custom-dropdown" id="modalCustomerCategoryDropdown" data-custom-dropdown data-name="category" data-placeholder="Pilih Kategori..." data-search-placeholder="Cari kategori...">
                                <input type="hidden" id="customerCategory" name="category" value="Toko">
                                <script type="application/json" class="dropdown-options">
                                    [
                                        {"value": "Toko", "label": "Toko", "badge": "Retail"},
                                        {"value": "Minimarket", "label": "Minimarket", "badge": "Modern"},
                                        {"value": "Restoran", "label": "Restoran", "badge": "Kuliner"}
                                    ]
                                </script>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="customerPic" class="form-label small fw-semibold text-secondary mb-1">Nama Penanggung Jawab (PIC) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customerPic" name="pic_name" placeholder="Contoh: H. Asep Sunandar" required>
                        </div>

                        <div class="col-md-4">
                            <label for="customerPhone" class="form-label small fw-semibold text-secondary mb-1">Nomor Telepon (Hanya Angka) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="customerPhone" name="phone" data-numeric-only inputmode="numeric" pattern="[0-9]*" maxlength="20" placeholder="081223456789" required>
                        </div>

                        <div class="col-12">
                            <label for="customerAddress" class="form-label small fw-semibold text-secondary mb-1">Alamat Lengkap di Wilayah Bandung <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="customerAddress" name="address" rows="2" placeholder="Contoh: Jl. Buah Batu No. 182, Lengkong, Kota Bandung" required></textarea>
                        </div>

                        <!-- Interactive Map Coordinate Picker di Dalam Modal -->
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                <label class="form-label small fw-semibold text-secondary mb-0">
                                    <i class="bi bi-pin-map-fill text-primary me-1"></i> Pilih Titik Koordinat pada Peta Bandung <span class="text-danger">*</span>
                                </label>
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-normal">
                                    Klik pada peta atau geser marker biru untuk mengambil koordinat otomatis
                                </span>
                            </div>
                            <div id="customerModalMap" class="map-modal-picker mb-2"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="customerLatitude" class="form-label small fw-semibold text-secondary mb-1">Latitude <span class="text-danger">*</span></label>
                            <input type="number" step="any" class="form-control font-monospace" id="customerLatitude" name="latitude" placeholder="-6.94881200" required>
                        </div>

                        <div class="col-md-6">
                            <label for="customerLongitude" class="form-label small fw-semibold text-secondary mb-1">Longitude <span class="text-danger">*</span></label>
                            <input type="number" step="any" class="form-control font-monospace" id="customerLongitude" name="longitude" placeholder="107.63489000" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="customerSaveBtn">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Data Pelanggan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiUrl = '<?= e(base_url('api/customers.php')) ?>';
    const defaultLat = <?= json_encode((float)($activeWarehouse['latitude'] ?? -6.943097)) ?>;
    const defaultLng = <?= json_encode((float)($activeWarehouse['longitude'] ?? 107.633545)) ?>;

    let currentPage = 1;
    let currentSearch = '';
    let currentCategory = 'Semua';
    let nextGeneratedCode = 'PLG-011';

    const tableBody = document.getElementById('customerTableBody');
    const paginationInfo = document.getElementById('customerPaginationInfo');
    const paginationList = document.getElementById('customerPaginationList');
    const searchInput = document.getElementById('customerSearchInput');
    const filterCatInput = document.getElementById('filterCustomerCategoryInput');

    const modalEl = document.getElementById('customerModal');
    const bsModal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('customerForm');
    const modalTitle = document.getElementById('customerModalTitle');
    const saveBtn = document.getElementById('customerSaveBtn');

    // Inisialisasi Interactive Map Picker di dalam Modal
    let pickerMap = null;
    let pickerMarker = null;

    function initOrUpdatePickerMap(lat, lng) {
        const targetLat = !isNaN(parseFloat(lat)) ? parseFloat(lat) : defaultLat;
        const targetLng = !isNaN(parseFloat(lng)) ? parseFloat(lng) : defaultLng;

        if (!pickerMap) {
            pickerMap = L.map('customerModalMap').setView([targetLat, targetLng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(pickerMap);

            const pinIcon = window.createMapPinIcon('#2563EB', 'bi-geo-alt-fill');
            pickerMarker = L.marker([targetLat, targetLng], {
                draggable: true,
                icon: pinIcon
            }).addTo(pickerMap);

            pickerMap.on('click', function (e) {
                const { lat: cLat, lng: cLng } = e.latlng;
                pickerMarker.setLatLng([cLat, cLng]);
                document.getElementById('customerLatitude').value = cLat.toFixed(8);
                document.getElementById('customerLongitude').value = cLng.toFixed(8);
            });

            pickerMarker.on('dragend', function () {
                const pos = pickerMarker.getLatLng();
                document.getElementById('customerLatitude').value = pos.lat.toFixed(8);
                document.getElementById('customerLongitude').value = pos.lng.toFixed(8);
            });
        } else {
            pickerMap.setView([targetLat, targetLng], 14);
            pickerMarker.setLatLng([targetLat, targetLng]);
        }
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        const latVal = document.getElementById('customerLatitude').value;
        const lngVal = document.getElementById('customerLongitude').value;
        initOrUpdatePickerMap(latVal, lngVal);
        if (pickerMap) {
            pickerMap.invalidateSize();
        }
    });

    // Sinkronisasi input manual koordinat ke marker peta
    ['customerLatitude', 'customerLongitude'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function () {
                const lat = parseFloat(document.getElementById('customerLatitude').value);
                const lng = parseFloat(document.getElementById('customerLongitude').value);
                if (pickerMap && pickerMarker && !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    pickerMarker.setLatLng([lat, lng]);
                    pickerMap.panTo([lat, lng]);
                }
            });
        }
    });

    function getCategoryBadgeHtml(cat) {
        const map = {
            'Toko': 'badge-soft-primary',
            'Minimarket': 'badge-soft-info',
            'Restoran': 'badge-soft-purple'
        };
        const cls = map[cat] || 'badge-soft-primary';
        return `<span class="badge ${cls} px-2 py-1">${window.escapeHtml(cat)}</span>`;
    }

    async function loadCustomers(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({
            page: currentPage,
            per_page: 10,
            search: currentSearch,
            category: currentCategory
        });

        try {
            const res = await fetch(`${apiUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();

            if (!res.ok || json.status !== 'success') {
                throw new Error(json.message || 'Gagal memuat data pelanggan.');
            }

            if (json.next_code) {
                nextGeneratedCode = json.next_code;
            }

            renderTable(json.data || []);
            renderPagination(json.pagination || { total: 0, page: 1, per_page: 10, total_pages: 1 });
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${window.escapeHtml(err.message)}</td></tr>`;
        }
    }

    function renderTable(rows) {
        if (!rows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-secondary">
                        <i class="bi bi-inbox fs-3 d-block mb-2 text-muted"></i>
                        Tidak ada data pelanggan yang sesuai dengan pencarian/filter.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = rows.map(r => `
            <tr>
                <th scope="row" class="fw-bold text-primary font-monospace text-nowrap">${window.escapeHtml(r.code)}</th>
                <td class="col-name-prop">
                    <div class="fw-bold text-dark">${window.escapeHtml(r.name)}</div>
                    ${Number(r.shipment_count) > 0 ? `<small class="text-muted"><i class="bi bi-truck me-1"></i>${Number(r.shipment_count)} pengiriman</small>` : ''}
                </td>
                <td class="text-nowrap">${getCategoryBadgeHtml(r.category)}</td>
                <td class="text-nowrap">
                    <div class="fw-semibold text-dark">${window.escapeHtml(r.pic_name)}</div>
                    <div class="small text-secondary font-monospace"><i class="bi bi-telephone me-1"></i>${window.escapeHtml(r.phone)}</div>
                </td>
                <td class="col-address-prop small text-secondary">${window.escapeHtml(r.address)}</td>
                <td class="text-nowrap small font-monospace text-secondary">
                    <div>Lat: ${Number(r.latitude).toFixed(6)}</div>
                    <div>Lng: ${Number(r.longitude).toFixed(6)}</div>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-light border text-primary me-1 btn-edit-customer" data-id="${r.id}" title="Edit Pelanggan">
                        <i class="bi bi-pencil-square me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-customer" data-id="${r.id}" data-name="${window.escapeHtml(r.name)}" data-code="${window.escapeHtml(r.code)}" title="Hapus Pelanggan">
                        <i class="bi bi-trash3 me-1"></i>Hapus
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderPagination(meta) {
        const total = Number(meta.total || 0);
        const page = Number(meta.page || 1);
        const perPage = Number(meta.per_page || 10);
        const totalPages = Number(meta.total_pages || 1);

        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(total, page * perPage);
        paginationInfo.textContent = `Menampilkan ${start}–${end} dari total ${total} titik pelanggan`;

        let html = '';
        html += `
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
            loadCustomers(targetPage);
        }
    });

    // Live Search Debounce 300ms
    searchInput.addEventListener('input', window.debounce(function () {
        currentSearch = this.value.trim();
        loadCustomers(1);
    }, 300));

    // Auto-Filter Kategori saat nilai Custom Dropdown berubah
    filterCatInput.addEventListener('change', function () {
        currentCategory = this.value || 'Semua';
        loadCustomers(1);
    });

    // Buka Modal Tambah Pelanggan
    document.getElementById('btnAddCustomer').addEventListener('click', function () {
        form.reset();
        document.getElementById('customerId').value = '';
        document.getElementById('customerFormAction').value = 'create';
        document.getElementById('customerCode').value = nextGeneratedCode;
        document.getElementById('customerLatitude').value = defaultLat.toFixed(8);
        document.getElementById('customerLongitude').value = defaultLng.toFixed(8);
        window.setCustomDropdownValue('modalCustomerCategoryDropdown', 'Toko', false);
        modalTitle.textContent = 'Tambah Titik Pelanggan Baru';
        bsModal.show();
    });

    // Delegasi Klik Edit & Hapus
    tableBody.addEventListener('click', async function (e) {
        const editBtn = e.target.closest('.btn-edit-customer');
        if (editBtn) {
            const id = editBtn.dataset.id;
            try {
                const res = await fetch(`${apiUrl}?action=detail&id=${encodeURIComponent(id)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal mengambil detail pelanggan.');
                }
                const d = json.data;
                document.getElementById('customerId').value = d.id;
                document.getElementById('customerFormAction').value = 'update';
                document.getElementById('customerCode').value = d.code;
                document.getElementById('customerName').value = d.name;
                document.getElementById('customerPic').value = d.pic_name;
                document.getElementById('customerPhone').value = d.phone;
                document.getElementById('customerAddress').value = d.address;
                document.getElementById('customerLatitude').value = Number(d.latitude).toFixed(8);
                document.getElementById('customerLongitude').value = Number(d.longitude).toFixed(8);
                window.setCustomDropdownValue('modalCustomerCategoryDropdown', d.category, false);

                modalTitle.textContent = `Edit Pelanggan (${d.code})`;
                bsModal.show();
            } catch (err) {
                window.showToast({ title: 'Gagal', message: err.message, type: 'danger' });
            }
            return;
        }

        const delBtn = e.target.closest('.btn-delete-customer');
        if (delBtn) {
            const id = delBtn.dataset.id;
            const name = delBtn.dataset.name;
            const code = delBtn.dataset.code;

            window.showConfirmModal({
                title: 'Hapus Titik Pelanggan?',
                message: `Apakah Anda yakin ingin menghapus "${name}" (${code}) dari daftar pelanggan distribusi?`,
                confirmText: 'Ya, Hapus Data',
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
                            throw new Error(json.message || 'Gagal menghapus data pelanggan.');
                        }
                        window.showToast({ title: 'Berhasil Dihapus', message: json.message, type: 'success' });
                        loadCustomers(currentPage);
                    } catch (err) {
                        window.showToast({ title: 'Tidak Dapat Dihapus', message: err.message, type: 'danger' });
                    }
                }
            });
        }
    });

    // Submit Form Tambah / Edit
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const phoneVal = document.getElementById('customerPhone').value.trim();
        if (!/^[0-9]{8,20}$/.test(phoneVal)) {
            window.showToast({
                title: 'Validasi Nomor Telepon',
                message: 'Nomor telepon wajib berisi angka saja (8 s.d. 20 digit).',
                type: 'warning'
            });
            document.getElementById('customerPhone').focus();
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
                throw new Error(json.message || 'Terjadi kesalahan saat menyimpan data pelanggan.');
            }

            bsModal.hide();
            window.showToast({ title: 'Berhasil', message: json.message, type: 'success' });
            loadCustomers(currentPage);
        } catch (err) {
            window.showToast({ title: 'Gagal Menyimpan', message: err.message, type: 'danger' });
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    });

    loadCustomers(1);
});
</script>
