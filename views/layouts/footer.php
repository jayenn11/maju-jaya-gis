<?php
/**
 * Layout Footer, Global Confirmation Modal, & Toast Container
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

$flashToast = $_SESSION['flash_toast'] ?? null;
unset($_SESSION['flash_toast']);
?>
        </main>

        <!-- Footer Informasi Sistem -->
        <footer class="px-4 py-3 border-top bg-white text-secondary small d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                &copy; <?= date('Y') ?> <strong>PT Maju Jaya Food</strong> — Sistem Informasi Geografis Distribusi Makanan Wilayah Bandung.
            </div>
            <div class="d-flex align-items-center gap-3">
                <span><i class="bi bi-compass text-primary me-1"></i>Leaflet.js 1.9 + OSRM Routing API</span>
            </div>
        </footer>
    </div>
</div>

<!-- Global Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="globalToastContainer" style="z-index: 1090;"></div>

<!-- Global Custom Confirmation Modal (Pengganti window.confirm) -->
<div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-labelledby="globalConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div id="globalConfirmIconWrap" class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger" style="width: 64px; height: 64px;">
                    <i id="globalConfirmIcon" class="bi bi-trash3-fill fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2" id="globalConfirmTitle">Konfirmasi Tindakan</h5>
                <p class="text-secondary small mb-4" id="globalConfirmMessage">Apakah Anda yakin ingin melanjutkan tindakan ini?</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal" id="globalCancelBtn">Batal</button>
                    <button type="button" class="btn btn-danger px-4 fw-semibold" id="globalConfirmBtn">Ya, Hapus</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Global App Helpers -->
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>

<?php if ($flashToast): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.showToast === 'function') {
            window.showToast({
                title: <?= json_encode($flashToast['title'] ?? 'Berhasil') ?>,
                message: <?= json_encode($flashToast['message'] ?? '') ?>,
                type: <?= json_encode($flashToast['type'] ?? 'success') ?>
            });
        }
    });
</script>
<?php endif; ?>
</body>
</html>
