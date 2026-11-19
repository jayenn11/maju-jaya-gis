<?php
/**
 * Halaman Login Administrator Logistik
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

$loginError  = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$isLoggedOut = isset($_GET['logout']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Administrator — SIG Distribusi Makanan PT Maju Jaya Food</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --slate-900: #0F172A;
            --slate-800: #1E293B;
            --slate-600: #475569;
            --brand-blue: #2563EB;
            --brand-blue-dark: #1D4ED8;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #F1F5F9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image:
                radial-gradient(at 15% 15%, rgba(37, 99, 235, 0.10) 0px, transparent 50%),
                radial-gradient(at 85% 85%, rgba(16, 185, 129, 0.10) 0px, transparent 50%);
        }
        .login-shell {
            max-width: 980px;
            width: 100%;
            border-radius: 1.25rem;
            overflow: hidden;
            box-shadow: 0 20px 45px -15px rgba(15, 23, 42, 0.15);
            background: #FFFFFF;
        }
        .login-hero {
            background: linear-gradient(155deg, #0F172A 0%, #1E293B 60%, #1E3A8A 100%);
            color: #F8FAFC;
            position: relative;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .login-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.18);
            border: 1px solid rgba(96, 165, 250, 0.35);
            color: #93C5FD;
            font-size: 0.8rem;
            font-weight: 600;
            width: fit-content;
        }
        .feature-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.85rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            color: #E2E8F0;
        }
        .form-control {
            padding: 0.7rem 0.95rem;
            border-radius: 0.65rem;
            border-color: #CBD5E1;
            font-size: 0.92rem;
        }
        .form-control:focus {
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn-login {
            background: var(--brand-blue);
            border-color: var(--brand-blue);
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            border-radius: 0.65rem;
            transition: all 0.2s ease;
        }
        .btn-login:hover {
            background: var(--brand-blue-dark);
            border-color: var(--brand-blue-dark);
            transform: translateY(-1px);
        }
        .demo-box {
            background: #F8FAFC;
            border: 1px dashed #94A3B8;
            border-radius: 0.85rem;
            padding: 0.9rem 1rem;
        }
    </style>
</head>
<body>
    <div class="login-shell row g-0">
        <!-- Kolom Informasi Spasial Kiri -->
        <div class="col-lg-6 login-hero d-none d-lg-flex">
            <div class="position-relative z-1">
                <div class="hero-badge mb-4">
                    <i class="bi bi-geo-alt-fill text-info"></i>
                    <span>Sistem Informasi Geografis Logistik</span>
                </div>
                <h2 class="fw-bold mb-3" style="line-height: 1.25;">
                    SIG Distribusi Makanan<br>
                    <span class="text-info">PT Maju Jaya Food</span>
                </h2>
                <p class="text-light text-opacity-75 mb-4" style="font-size: 0.94rem; line-height: 1.65;">
                    Platform pemetaan spasial dan monitoring rute distribusi produk makanan ke jaringan Toko, Minimarket, dan Restoran di seluruh kawasan Kota Bandung berbasis Leaflet.js &amp; OSRM Routing API.
                </p>

                <div class="d-flex flex-column gap-2 mt-4">
                    <div class="feature-pill">
                        <i class="bi bi-pin-map-fill text-info fs-5"></i>
                        <div>
                            <div class="fw-semibold text-white">Pemetaan Titik Gudang &amp; Pelanggan</div>
                            <div class="small text-light text-opacity-75">Koordinat akurat wilayah Bandung &amp; sekitarnya</div>
                        </div>
                    </div>
                    <div class="feature-pill">
                        <i class="bi bi-sign-turn-right-fill text-warning fs-5"></i>
                        <div>
                            <div class="fw-semibold text-white">Kalkulasi Rute Jalan Raya (OSRM API)</div>
                            <div class="small text-light text-opacity-75">Jarak tempuh (km), estimasi waktu, &amp; visualisasi jalur</div>
                        </div>
                    </div>
                    <div class="feature-pill">
                        <i class="bi bi-truck text-success fs-5"></i>
                        <div>
                            <div class="fw-semibold text-white">Monitoring Armada &amp; Stok Real-Time</div>
                            <div class="small text-light text-opacity-75">Kendali penuh status pengiriman harian</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="position-relative z-1 pt-4 border-top border-light border-opacity-10 d-flex justify-content-between align-items-center small text-light text-opacity-75">
                <span><i class="bi bi-building me-1"></i> Gudang Utama: Jl. Soekarno-Hatta No. 450, Bandung</span>
                <span class="badge bg-light bg-opacity-10 text-light">v1.0</span>
            </div>
        </div>

        <!-- Kolom Form Login Kanan -->
        <div class="col-lg-6 p-4 p-sm-5 d-flex flex-column justify-content-center bg-white">
            <div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-3 bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-box-seam-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark lh-1">PT Maju Jaya Food</div>
                        <small class="text-secondary">Portal Administrator Logistik — Bandung</small>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mt-3 mb-1">Masuk ke Panel Kendali</h4>
                <p class="text-secondary small mb-0">Silakan masukkan kredensial administrator untuk mengelola distribusi makanan.</p>
            </div>

            <?php if ($isLoggedOut): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 small rounded-3 border-0 bg-success bg-opacity-10 text-success mb-3">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Anda telah berhasil keluar dari sesi administrator.</span>
                </div>
            <?php endif; ?>

            <?php if ($loginError !== ''): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small rounded-3 border-0 bg-danger bg-opacity-10 text-danger mb-3" id="loginAlert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= e($loginError) ?></span>
                </div>
            <?php endif; ?>

            <div id="ajaxLoginError" class="alert alert-danger d-none align-items-center gap-2 py-2 px-3 small rounded-3 border-0 bg-danger bg-opacity-10 text-danger mb-3">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span id="ajaxLoginErrorText"></span>
            </div>

            <form id="loginForm" action="<?= e(base_url('api/auth.php')) ?>" method="POST" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-secondary mb-1">Username Administrator</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" value="admin" placeholder="Masukkan username" required autocomplete="username">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold text-secondary mb-1">Kata Sandi</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="password" name="password" value="admin123" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                        <button type="button" class="input-group-text bg-light text-secondary" id="togglePasswordBtn" title="Tampilkan/Sembunyikan Sandi">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-login w-100 text-white d-flex align-items-center justify-content-center gap-2 mb-4" id="submitLoginBtn">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Masuk ke Dashboard SIG</span>
                </button>
            </form>

            <!-- Kotak Informasi Kredensial Demo -->
            <div class="demo-box">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-bold text-dark">
                        <i class="bi bi-key-fill text-primary me-1"></i> Akun Demo Pengujian
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" id="fillDemoBtn">
                        Isi Otomatis
                    </button>
                </div>
                <div class="row g-2 small text-secondary">
                    <div class="col-6">
                        <div class="p-2 rounded bg-white border">
                            <span class="d-block text-muted" style="font-size: 0.72rem;">Username</span>
                            <code class="fw-bold text-dark">admin</code>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded bg-white border">
                            <span class="d-block text-muted" style="font-size: 0.72rem;">Password</span>
                            <code class="fw-bold text-dark">admin123</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            const passInput = document.getElementById('password');
            const userInput = document.getElementById('username');
            const fillDemoBtn = document.getElementById('fillDemoBtn');
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitLoginBtn');
            const errBox = document.getElementById('ajaxLoginError');
            const errText = document.getElementById('ajaxLoginErrorText');

            if (toggleBtn && passInput) {
                toggleBtn.addEventListener('click', function () {
                    const isPass = passInput.type === 'password';
                    passInput.type = isPass ? 'text' : 'password';
                    toggleIcon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
                });
            }

            if (fillDemoBtn) {
                fillDemoBtn.addEventListener('click', function () {
                    userInput.value = 'admin';
                    passInput.value = 'admin123';
                    userInput.focus();
                });
            }

            if (loginForm) {
                loginForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    errBox.classList.add('d-none');
                    errBox.classList.remove('d-flex');
                    submitBtn.disabled = true;
                    const originalHtml = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><span>Memverifikasi...</span>';

                    try {
                        const formData = new FormData(loginForm);
                        const response = await fetch(loginForm.action, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: formData
                        });
                        const result = await response.json();

                        if (response.ok && result.status === 'success') {
                            window.location.href = result.redirect || '<?= e(base_url('index.php?page=dashboard')) ?>';
                            return;
                        }

                        errText.textContent = result.message || 'Gagal melakukan autentikasi.';
                        errBox.classList.remove('d-none');
                        errBox.classList.add('d-flex');
                    } catch (err) {
                        loginForm.submit();
                        return;
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                    }
                });
            }
        })();
    </script>
</body>
</html>
