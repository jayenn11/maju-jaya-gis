<?php
/**
 * Modul Master Data Barang (Katalog Produk Makanan Distribusi)
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 * Mematuhi standar AGENTS.md Bagian 4:
 * - Custom Searchable Dropdown (Kategori & Satuan tanpa <select> bawaan)
 * - Modal-First CRUD + overflow: visible pada .modal-content
 * - Live Search (debounce 300ms) + Auto-Filter Kategori
 * - Proteksi hapus jika barang sudah digunakan pada pengiriman
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
                    <input type="text" id="productSearchInput" class="form-control border-start-0 ps-0" placeholder="Ketik kode, nama barang, atau satuan kemasan (Live Search)...">
                </div>
            </div>

            <div class="col-md-7 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                <!-- Filter Kategori Barang (Custom Searchable Dropdown) -->
                <div style="width: 245px;">
                    <div class="custom-dropdown" id="filterProductCategoryDropdown" data-custom-dropdown data-name="filter_category" data-placeholder="Semua Kategori Barang" data-search-placeholder="Cari kategori...">
                        <input type="hidden" id="filterProductCategoryInput" name="filter_category" value="Semua">
                        <script type="application/json" class="dropdown-options">
                            [
                                {"value": "Semua", "label": "Semua Kategori Barang"},
                                {"value": "Makanan Kemasan", "label": "Makanan Kemasan"},
                                {"value": "Bahan Pokok", "label": "Bahan Pokok"},
                                {"value": "Minuman", "label": "Minuman"},
                                {"value": "Frozen Food", "label": "Frozen Food"}
                            ]
                        </script>
                    </div>
                </div>

                <button type="button" class="btn btn-primary fw-semibold px-3 d-inline-flex align-items-center gap-2 text-nowrap" id="btnAddProduct">
                    <i class="bi bi-plus-lg"></i>
                    <span>Tambah Barang</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabel Katalog Produk Makanan -->
    <div class="table-scroll-container" tabindex="0" role="region" aria-label="Tabel Katalog Barang">
        <table class="table table-gis">
            <thead>
                <tr>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama Barang</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Satuan</th>
                    <th scope="col">Berat / Satuan (kg)</th>
                    <th scope="col">Stok Gudang</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody id="productTableBody">
                <tr>
                    <td colspan="7" class="text-center py-5 text-secondary">
                        <span class="spinner-border spinner-border-sm text-primary me-2"></span>Memuat katalog barang...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Footer Pagination Terang -->
    <div class="p-3 p-lg-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 bg-light bg-opacity-50">
        <div class="small text-secondary" id="productPaginationInfo">Menampilkan 0 produk makanan</div>
        <nav aria-label="Navigasi Halaman Barang">
            <ul class="pagination pagination-sm pagination-light-theme mb-0" id="productPaginationList"></ul>
        </nav>
    </div>
</div>

<!-- Modal Tambah & Edit Barang (overflow: visible) -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0" id="productModalTitle">Tambah Produk Distribusi</h5>
                    <small class="text-secondary">Masukkan rincian produk makanan dan bobot per satuan kemasan.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <form id="productForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="productId" name="id" value="">
                    <input type="hidden" id="productFormAction" name="action" value="create">

                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label for="productCode" class="form-label small fw-semibold text-secondary mb-1">Kode Barang <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light fw-bold font-monospace" id="productCode" name="code" readonly required>
                        </div>

                        <div class="col-sm-8">
                            <label for="productName" class="form-label small fw-semibold text-secondary mb-1">Nama Barang Makanan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="productName" name="name" placeholder="Contoh: Makanan Kemasan Ringan" required>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold text-secondary mb-1">Kategori Produk <span class="text-danger">*</span></label>
                            <div class="custom-dropdown" id="modalProductCategoryDropdown" data-custom-dropdown data-name="category" data-placeholder="Pilih Kategori..." data-search-placeholder="Cari kategori...">
                                <input type="hidden" id="productCategory" name="category" value="Makanan Kemasan">
                                <script type="application/json" class="dropdown-options">
                                    [
                                        {"value": "Makanan Kemasan", "label": "Makanan Kemasan"},
                                        {"value": "Bahan Pokok", "label": "Bahan Pokok"},
                                        {"value": "Minuman", "label": "Minuman"},
                                        {"value": "Frozen Food", "label": "Frozen Food"}
                                    ]
                                </script>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold text-secondary mb-1">Satuan Kemasan <span class="text-danger">*</span></label>
                            <div class="custom-dropdown" id="modalProductUnitDropdown" data-custom-dropdown data-name="unit" data-placeholder="Pilih Satuan..." data-search-placeholder="Cari satuan...">
                                <input type="hidden" id="productUnit" name="unit" value="Kardus">
                                <script type="application/json" class="dropdown-options">
                                    [
                                        {"value": "Kardus", "label": "Kardus"},
                                        {"value": "Bal", "label": "Bal"},
                                        {"value": "Pack", "label": "Pack"},
                                        {"value": "Box", "label": "Box"}
                                    ]
                                </script>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label for="productWeight" class="form-label small fw-semibold text-secondary mb-1">Berat per Satuan (kg) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="productWeight" name="weight_per_unit" placeholder="Contoh: 10.00" required>
                                <span class="input-group-text bg-light text-secondary">kg</span>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label for="productStock" class="form-label small fw-semibold text-secondary mb-1">Stok Tersedia <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="productStock" name="stock" data-numeric-only inputmode="numeric" placeholder="Contoh: 100" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="productSaveBtn">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Barang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiUrl = '<?= e(base_url('api/products.php')) ?>';

    let currentPage = 1;
    let currentSearch = '';
    let currentCategory = 'Semua';
    let nextGeneratedCode = 'BRG-009';

    const tableBody = document.getElementById('productTableBody');
    const paginationInfo = document.getElementById('productPaginationInfo');
    const paginationList = document.getElementById('productPaginationList');
    const searchInput = document.getElementById('productSearchInput');
    const filterCatInput = document.getElementById('filterProductCategoryInput');

    const modalEl = document.getElementById('productModal');
    const bsModal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('productForm');
    const modalTitle = document.getElementById('productModalTitle');
    const saveBtn = document.getElementById('productSaveBtn');

    function getCategoryBadgeHtml(cat) {
        const map = {
            'Makanan Kemasan': 'badge-soft-primary',
            'Bahan Pokok': 'badge-soft-warning',
            'Minuman': 'badge-soft-info',
            'Frozen Food': 'badge-soft-purple'
        };
        const cls = map[cat] || 'badge-soft-primary';
        return `<span class="badge ${cls} px-2 py-1">${window.escapeHtml(cat)}</span>`;
    }

    async function loadProducts(page = 1) {
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
                throw new Error(json.message || 'Gagal memuat katalog barang.');
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
                        <i class="bi bi-box-seam fs-3 d-block mb-2 text-muted"></i>
                        Tidak ada produk makanan yang sesuai dengan pencarian/filter.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = rows.map(r => {
            const stockVal = Number(r.stock);
            const stockBadge = stockVal <= 20
                ? `<span class="badge badge-soft-danger">${stockVal} ${window.escapeHtml(r.unit)} (Menipis)</span>`
                : `<span class="badge badge-soft-success">${stockVal} ${window.escapeHtml(r.unit)}</span>`;

            return `
                <tr>
                    <th scope="row" class="fw-bold text-primary font-monospace text-nowrap">${window.escapeHtml(r.code)}</th>
                    <td class="col-name-prop">
                        <div class="fw-bold text-dark">${window.escapeHtml(r.name)}</div>
                        ${Number(r.shipment_count) > 0 ? `<small class="text-muted"><i class="bi bi-truck me-1"></i>Terpakai di ${Number(r.shipment_count)} pengiriman</small>` : ''}
                    </td>
                    <td class="text-nowrap">${getCategoryBadgeHtml(r.category)}</td>
                    <td class="text-nowrap fw-medium text-secondary">${window.escapeHtml(r.unit)}</td>
                    <td class="text-nowrap fw-semibold text-dark">${Number(r.weight_per_unit).toFixed(2)} kg</td>
                    <td class="text-nowrap">${stockBadge}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-light border text-primary me-1 btn-edit-product" data-id="${r.id}" title="Edit Barang">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-product" data-id="${r.id}" data-name="${window.escapeHtml(r.name)}" data-code="${window.escapeHtml(r.code)}" title="Hapus Barang">
                            <i class="bi bi-trash3 me-1"></i>Hapus
                        </button>
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
        paginationInfo.textContent = `Menampilkan ${start}–${end} dari total ${total} produk distribusi`;

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
            loadProducts(targetPage);
        }
    });

    // Live Search Debounce 300ms
    searchInput.addEventListener('input', window.debounce(function () {
        currentSearch = this.value.trim();
        loadProducts(1);
    }, 300));

    // Auto-Filter Kategori
    filterCatInput.addEventListener('change', function () {
        currentCategory = this.value || 'Semua';
        loadProducts(1);
    });

    // Buka Modal Tambah Barang
    document.getElementById('btnAddProduct').addEventListener('click', function () {
        form.reset();
        document.getElementById('productId').value = '';
        document.getElementById('productFormAction').value = 'create';
        document.getElementById('productCode').value = nextGeneratedCode;
        window.setCustomDropdownValue('modalProductCategoryDropdown', 'Makanan Kemasan', false);
        window.setCustomDropdownValue('modalProductUnitDropdown', 'Kardus', false);
        modalTitle.textContent = 'Tambah Produk Distribusi Baru';
        bsModal.show();
    });

    // Delegasi Klik Edit & Hapus Barang
    tableBody.addEventListener('click', async function (e) {
        const editBtn = e.target.closest('.btn-edit-product');
        if (editBtn) {
            const id = editBtn.dataset.id;
            try {
                const res = await fetch(`${apiUrl}?action=detail&id=${encodeURIComponent(id)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal mengambil detail barang.');
                }
                const d = json.data;
                document.getElementById('productId').value = d.id;
                document.getElementById('productFormAction').value = 'update';
                document.getElementById('productCode').value = d.code;
                document.getElementById('productName').value = d.name;
                document.getElementById('productWeight').value = Number(d.weight_per_unit).toFixed(2);
                document.getElementById('productStock').value = d.stock;
                window.setCustomDropdownValue('modalProductCategoryDropdown', d.category, false);
                window.setCustomDropdownValue('modalProductUnitDropdown', d.unit, false);

                modalTitle.textContent = `Edit Barang (${d.code})`;
                bsModal.show();
            } catch (err) {
                window.showToast({ title: 'Gagal', message: err.message, type: 'danger' });
            }
            return;
        }

        const delBtn = e.target.closest('.btn-delete-product');
        if (delBtn) {
            const id = delBtn.dataset.id;
            const name = delBtn.dataset.name;
            const code = delBtn.dataset.code;

            window.showConfirmModal({
                title: 'Hapus Barang dari Katalog?',
                message: `Apakah Anda yakin ingin menghapus "${name}" (${code}) dari katalog produk PT Maju Jaya Food?`,
                confirmText: 'Ya, Hapus Barang',
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
                            throw new Error(json.message || 'Gagal menghapus data barang.');
                        }
                        window.showToast({ title: 'Berhasil Dihapus', message: json.message, type: 'success' });
                        loadProducts(currentPage);
                    } catch (err) {
                        window.showToast({ title: 'Tidak Dapat Dihapus', message: err.message, type: 'danger' });
                    }
                }
            });
        }
    });

    // Submit Form Tambah / Edit Barang
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
                throw new Error(json.message || 'Terjadi kesalahan saat menyimpan data barang.');
            }

            bsModal.hide();
            window.showToast({ title: 'Berhasil', message: json.message, type: 'success' });
            loadProducts(currentPage);
        } catch (err) {
            window.showToast({ title: 'Gagal Menyimpan', message: err.message, type: 'danger' });
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    });

    loadProducts(1);
});
</script>
