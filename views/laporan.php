<?php
/**
 * Modul Laporan Rekapitulasi Pengiriman, Ekspor CSV, & Cetak Resmi
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 *
 * Mematuhi standar AGENTS.md:
 * - Custom Searchable Dropdown (Tanpa elemen select bawaan browser)
 * - Auto-Filter Rentang Tanggal, Status, dan Pelanggan tanpa tombol Cari
 * - 4 Kartu Ringkasan Rekapitulasi (Total Pengiriman, Total Jarak, Total Berat, Rata-rata Waktu)
 * - Ekspor CSV & Cetak Laporan Resmi (@media print)
 */

$defaultStartDate = date('Y-m-01');
$defaultEndDate   = date('Y-m-t');

// Ambil daftar pelanggan untuk Custom Searchable Dropdown Filter
$custStmt = $pdo->query('SELECT id, code, name, category FROM customers ORDER BY code ASC');
$customersFilterList = $custStmt->fetchAll();

$customerFilterOptions = [
    ['value' => '0', 'label' => 'Semua Pelanggan Tujuan'],
];
foreach ($customersFilterList as $c) {
    $customerFilterOptions[] = [
        'value' => (string)$c['id'],
        'label' => $c['code'] . ' — ' . $c['name'],
        'badge' => $c['category'],
    ];
}
?>

<!-- Kop Laporan Resmi (Hanya Tampil Saat Cetak / @media print) -->
<div class="print-only-header">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h2 class="fw-bold mb-1" style="font-size: 16pt; color: #0F172A;">
                PT MAJU JAYA FOOD — Laporan Distribusi Pengiriman Barang (Bandung)
            </h2>
            <div style="font-size: 10pt; color: #334155;">
                <?= e($activeWarehouse['name']) ?> &bull; <?= e($activeWarehouse['address']) ?>
            </div>
        </div>
        <div class="text-end" style="font-size: 9.5pt; color: #334155;">
            <div><strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i') ?> WIB</div>
            <div id="printFilterSummaryText">Periode: <?= e(date('d/m/Y', strtotime($defaultStartDate))) ?> s/d <?= e(date('d/m/Y', strtotime($defaultEndDate))) ?></div>
        </div>
    </div>
</div>

<!-- Panel Filter Otomatis & Tombol Ekspor/Cetak (Disembunyikan Saat Cetak) -->
<div class="content-card p-3 p-lg-4 mb-4 no-print">
    <div class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-2">
            <label for="reportStartDate" class="form-label small fw-semibold text-secondary mb-1">Dari Tanggal</label>
            <input type="date" id="reportStartDate" class="form-control" value="<?= e($defaultStartDate) ?>">
        </div>

        <div class="col-sm-6 col-lg-2">
            <label for="reportEndDate" class="form-label small fw-semibold text-secondary mb-1">Sampai Tanggal</label>
            <input type="date" id="reportEndDate" class="form-control" value="<?= e($defaultEndDate) ?>">
        </div>

        <div class="col-sm-6 col-lg-3">
            <label class="form-label small fw-semibold text-secondary mb-1">Filter Status Pengiriman</label>
            <div class="custom-dropdown" id="reportStatusDropdown" data-custom-dropdown data-name="report_status" data-placeholder="Semua Status" data-search-placeholder="Cari status...">
                <input type="hidden" id="reportStatusInput" name="report_status" value="Semua">
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

        <div class="col-sm-6 col-lg-3">
            <label class="form-label small fw-semibold text-secondary mb-1">Filter Pelanggan Tujuan</label>
            <div class="custom-dropdown" id="reportCustomerDropdown" data-custom-dropdown data-name="report_customer" data-placeholder="Semua Pelanggan" data-search-placeholder="Ketik nama/kode pelanggan...">
                <input type="hidden" id="reportCustomerInput" name="report_customer" value="0">
                <script type="application/json" class="dropdown-options"><?= json_encode($customerFilterOptions, JSON_UNESCAPED_UNICODE) ?></script>
            </div>
        </div>

        <div class="col-lg-2 d-flex gap-2">
            <a href="<?= e(base_url('api/reports.php?action=export_csv')) ?>" id="btnExportCsv" class="btn btn-success fw-semibold flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" title="Unduh File CSV untuk Excel">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Export CSV</span>
            </a>
            <button type="button" id="btnPrintReport" class="btn btn-primary fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-3" title="Cetak Laporan Resmi">
                <i class="bi bi-printer-fill"></i>
                <span>Cetak</span>
            </button>
        </div>
    </div>
</div>

<!-- 4 Kartu Ringkasan Rekapitulasi -->
<div class="row g-3 mb-4">
    <!-- 1. Total Pengiriman -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Total Pengiriman</div>
                    <div class="h3 fw-bold text-dark mb-0" id="summaryTotalShipments">0</div>
                </div>
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary no-print">
                    <i class="bi bi-clipboard2-data-fill"></i>
                </div>
            </div>
            <div class="pt-2 border-top small text-secondary d-flex align-items-center justify-content-between">
                <span>Selesai Dikirim:</span>
                <span class="badge badge-soft-success" id="summaryCompletedCount">0 Selesai</span>
            </div>
        </div>
    </div>

    <!-- 2. Total Jarak Tempuh (km) -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Total Jarak Tempuh</div>
                    <div class="h3 fw-bold text-primary mb-0" id="summaryTotalDistance">0.00 km</div>
                </div>
                <div class="kpi-icon bg-info bg-opacity-10 text-info no-print">
                    <i class="bi bi-signpost-split-fill"></i>
                </div>
            </div>
            <div class="pt-2 border-top small text-secondary">
                Akumulasi jarak rute jalan raya OSRM
            </div>
        </div>
    </div>

    <!-- 3. Total Berat Muatan Terkirim (kg) -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Total Berat Muatan</div>
                    <div class="h3 fw-bold text-dark mb-0" id="summaryTotalWeight">0.00 kg</div>
                </div>
                <div class="kpi-icon bg-success bg-opacity-10 text-success no-print">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
            </div>
            <div class="pt-2 border-top small text-secondary">
                Total tonase distribusi produk makanan
            </div>
        </div>
    </div>

    <!-- 4. Rata-rata Waktu Tempuh (menit) -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div>
                    <div class="text-secondary small fw-semibold mb-1">Rata-rata Waktu Tempuh</div>
                    <div class="h3 fw-bold text-dark mb-0" id="summaryAvgDuration">0 mnt</div>
                </div>
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning no-print">
                    <i class="bi bi-stopwatch-fill"></i>
                </div>
            </div>
            <div class="pt-2 border-top small text-secondary">
                Estimasi durasi perjalanan per pengiriman
            </div>
        </div>
    </div>
</div>

<!-- Tabel Laporan Pengiriman -->
<div class="content-card overflow-hidden">
    <div class="p-3 p-lg-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 no-print">
        <div>
            <h2 class="h6 fw-bold text-dark mb-1">
                <i class="bi bi-table text-primary me-1"></i> Rincian Data Laporan Pengiriman
            </h2>
            <p class="text-secondary small mb-0">Data diperbarui secara otomatis mengikuti filter periode tanggal, status, dan pelanggan di atas.</p>
        </div>
        <div style="width: 260px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-search"></i></span>
                <input type="text" id="reportSearchInput" class="form-control border-start-0 ps-0" placeholder="Cari cepat di tabel laporan...">
            </div>
        </div>
    </div>

    <div class="table-scroll-container" tabindex="0" role="region" aria-label="Tabel Laporan Pengiriman">
        <table class="table table-gis">
            <thead>
                <tr>
                    <th scope="col">No</th>
                    <th scope="col">Kode</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Pelanggan Tujuan</th>
                    <th scope="col">Barang Distribusi</th>
                    <th scope="col">Qty &amp; Berat</th>
                    <th scope="col">Kendaraan &amp; Sopir</th>
                    <th scope="col">Jarak (km)</th>
                    <th scope="col">Estimasi Waktu</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody id="reportTableBody">
                <tr>
                    <td colspan="10" class="text-center py-5 text-secondary">
                        <span class="spinner-border spinner-border-sm text-primary me-2"></span>Memuat laporan pengiriman...
                    </td>
                </tr>
            </tbody>
            <tfoot class="bg-light fw-bold" id="reportTableFoot"></tfoot>
        </table>
    </div>
</div>

<!-- Blok Tanda Tangan Resmi (Hanya Tampil Saat Cetak / @media print) -->
<div class="print-only-footer">
    <div class="text-center" style="width: 240px;">
        <div>Bandung, <?= date('d F Y') ?></div>
        <div class="small text-secondary mb-5">Penanggung Jawab Operasional,</div>
        <div class="fw-bold text-decoration-underline"><?= e($_SESSION['admin_name'] ?? 'Administrator Logistik') ?></div>
        <div class="small">PT Maju Jaya Food — Bandung</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiUrl = '<?= e(base_url('api/reports.php')) ?>';

    const startDateInput = document.getElementById('reportStartDate');
    const endDateInput = document.getElementById('reportEndDate');
    const statusInput = document.getElementById('reportStatusInput');
    const customerInput = document.getElementById('reportCustomerInput');
    const searchInput = document.getElementById('reportSearchInput');
    const btnExportCsv = document.getElementById('btnExportCsv');
    const btnPrintReport = document.getElementById('btnPrintReport');

    const totalShipEl = document.getElementById('summaryTotalShipments');
    const completedCountEl = document.getElementById('summaryCompletedCount');
    const totalDistEl = document.getElementById('summaryTotalDistance');
    const totalWeightEl = document.getElementById('summaryTotalWeight');
    const avgDurEl = document.getElementById('summaryAvgDuration');
    const printFilterText = document.getElementById('printFilterSummaryText');

    const tableBody = document.getElementById('reportTableBody');
    const tableFoot = document.getElementById('reportTableFoot');

    function getStatusBadgeHtml(status) {
        if (status === 'Selesai dikirim') {
            return `<span class="badge badge-soft-success px-2 py-1">Selesai dikirim</span>`;
        }
        if (status === 'Dalam pengiriman') {
            return `<span class="badge badge-soft-primary px-2 py-1">Dalam pengiriman</span>`;
        }
        return `<span class="badge badge-soft-warning px-2 py-1">Menunggu</span>`;
    }

    function buildFilterParams() {
        return new URLSearchParams({
            start_date: startDateInput.value || '',
            end_date: endDateInput.value || '',
            status: statusInput.value || 'Semua',
            customer_id: customerInput.value || '0',
            search: (searchInput.value || '').trim()
        });
    }

    function updateExportLinkAndPrintHeader() {
        const params = buildFilterParams();
        params.set('action', 'export_csv');
        btnExportCsv.setAttribute('href', `${apiUrl}?${params.toString()}`);

        const sDate = startDateInput.value ? startDateInput.value.split('-').reverse().join('/') : 'Awal';
        const eDate = endDateInput.value ? endDateInput.value.split('-').reverse().join('/') : 'Sekarang';
        const stLabel = statusInput.value || 'Semua';
        printFilterText.textContent = `Periode: ${sDate} s/d ${eDate} • Status: ${stLabel}`;
    }

    async function loadReportData() {
        updateExportLinkAndPrintHeader();
        const params = buildFilterParams();

        try {
            const res = await fetch(`${apiUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();

            if (!res.ok || json.status !== 'success') {
                throw new Error(json.message || 'Gagal memuat data laporan pengiriman.');
            }

            const sum = json.summary || {};
            totalShipEl.textContent = sum.total_shipments ?? 0;
            completedCountEl.textContent = `${sum.completed_shipments ?? 0} Selesai`;
            totalDistEl.textContent = `${Number(sum.total_distance_km || 0).toFixed(2)} km`;
            totalWeightEl.textContent = `${Number(sum.total_weight_kg || 0).toFixed(2)} kg`;
            avgDurEl.textContent = `${Number(sum.avg_duration_minutes || 0).toFixed(1)} mnt`;

            renderReportTable(json.data || [], sum);
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">${window.escapeHtml(err.message)}</td></tr>`;
            tableFoot.innerHTML = '';
        }
    }

    function renderReportTable(rows, sum) {
        if (!rows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-5 text-secondary">
                        <i class="bi bi-file-earmark-x fs-3 d-block mb-2 text-muted"></i>
                        Tidak ada data pengiriman pada rentang filter yang dipilih.
                    </td>
                </tr>
            `;
            tableFoot.innerHTML = '';
            return;
        }

        tableBody.innerHTML = rows.map((r, idx) => {
            const dateFormatted = r.shipment_date ? r.shipment_date.split('-').reverse().join('/') : '-';
            return `
                <tr>
                    <td class="text-secondary small">${idx + 1}</td>
                    <th scope="row" class="fw-bold text-primary font-monospace text-nowrap">${window.escapeHtml(r.shipment_code)}</th>
                    <td class="text-nowrap small text-secondary">${window.escapeHtml(dateFormatted)}</td>
                    <td class="col-name-prop">
                        <div class="fw-bold text-dark">${window.escapeHtml(r.customer_name)}</div>
                        <div class="small text-secondary">${window.escapeHtml(r.customer_code)} &bull; ${window.escapeHtml(r.customer_category)}</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${window.escapeHtml(r.product_name)}</div>
                        <div class="small text-secondary">${window.escapeHtml(r.product_code)}</div>
                    </td>
                    <td class="text-nowrap">
                        <div class="fw-semibold text-dark">${Number(r.quantity)} ${window.escapeHtml(r.product_unit)}</div>
                        <div class="small text-secondary">${Number(r.total_weight).toFixed(2)} kg</div>
                    </td>
                    <td class="text-nowrap">
                        <div class="fw-medium text-dark">${window.escapeHtml(r.vehicle_type)}</div>
                        <div class="small text-secondary">Sopir: ${window.escapeHtml(r.driver_name)}</div>
                    </td>
                    <td class="text-nowrap fw-semibold text-dark">${Number(r.distance_km).toFixed(2)} km</td>
                    <td class="text-nowrap fw-semibold text-dark">${Number(r.duration_minutes)} menit</td>
                    <td class="text-nowrap">${getStatusBadgeHtml(r.status)}</td>
                </tr>
            `;
        }).join('');

        tableFoot.innerHTML = `
            <tr>
                <td colspan="5" class="text-end py-3 px-3">TOTAL REKAPITULASI (${rows.length} Pengiriman):</td>
                <td class="text-nowrap py-3 px-3 text-dark">${Number(sum.total_weight_kg || 0).toFixed(2)} kg</td>
                <td class="py-3 px-3 text-secondary small">Rata-rata Waktu:</td>
                <td class="text-nowrap py-3 px-3 text-primary">${Number(sum.total_distance_km || 0).toFixed(2)} km</td>
                <td class="text-nowrap py-3 px-3 text-dark">${Number(sum.avg_duration_minutes || 0).toFixed(1)} menit</td>
                <td></td>
            </tr>
        `;
    }

    // Auto-reload saat filter tanggal, status, pelanggan, atau pencarian berubah
    startDateInput.addEventListener('change', loadReportData);
    endDateInput.addEventListener('change', loadReportData);
    statusInput.addEventListener('change', loadReportData);
    customerInput.addEventListener('change', loadReportData);
    searchInput.addEventListener('input', window.debounce(loadReportData, 300));

    btnPrintReport.addEventListener('click', function () {
        window.print();
    });

    loadReportData();
});
</script>
