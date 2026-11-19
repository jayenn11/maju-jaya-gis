<?php
/**
 * Konfigurasi Koneksi Database PDO & Fungsi Helper Dasar
 * Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pengaturan Zona Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// ============================================================================
// 1. KONFIGURASI KREDENSIAL DATABASE MYSQL
// ============================================================================
// Catatan: Kredensial disesuaikan dengan MySQL lokal ('rahasia').
// Ubah DB_PASS menjadi '' apabila dijalankan pada XAMPP standar tanpa password.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'maju_jaya_gis');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'rahasia');

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
    } catch (PDOException $firstException) {
        // Fallback otomatis ke password kosong ('') jika dijalankan di XAMPP default
        if (DB_PASS !== '') {
            $pdo = new PDO($dsn, DB_USER, '', $pdoOptions);
        } else {
            throw $firstException;
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Koneksi database gagal: Pastikan layanan MySQL aktif dan database "' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '" telah dibuat. Detail: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ============================================================================
// 2. FUNGSI HELPER KEAMANAN & URL DINAMIS
// ============================================================================

if (!function_exists('e')) {
    /**
     * Escape output string untuk mencegah celah XSS pada tampilan HTML.
     *
     * @param mixed $string Nilai teks yang akan ditampilkan
     * @return string Teks yang telah di-escape
     */
    function e($string): string
    {
        return htmlspecialchars((string)($string ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Menghasilkan URL dinamis yang kompatibel baik pada:
     * - PHP Built-in Server (`php -S localhost:8000`) -> root: ""
     * - XAMPP / Apache (`http://localhost/maju-jaya-gis`) -> root: "/maju-jaya-gis"
     *
     * @param string $path Path relatif dari root proyek (contoh: 'assets/css/style.css')
     * @return string URL lengkap atau path absolut dari root web server
     */
    function base_url(string $path = ''): string
    {
        static $basePath = null;

        if ($basePath === null) {
            $projectRoot = realpath(__DIR__ . '/..');
            $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

            if ($projectRoot && $docRoot && str_starts_with($projectRoot, $docRoot)) {
                $relative = substr($projectRoot, strlen($docRoot));
                $basePath = rtrim(str_replace('\\', '/', $relative), '/');
            } else {
                $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
                $scriptDir = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
                $basePath = preg_replace('#/(api|views|config)(/.*)?$#', '', $scriptDir);
            }
        }

        $cleanPath = ltrim($path, '/');
        if ($cleanPath === '') {
            return $basePath !== '' ? $basePath : '/';
        }

        return ($basePath !== '' ? $basePath : '') . '/' . $cleanPath;
    }
}

if (!function_exists('validate_coordinates')) {
    /**
     * Memvalidasi rentang koordinat spasial Latitude (-90 s.d. 90) dan Longitude (-180 s.d. 180).
     *
     * @param mixed $latitude
     * @param mixed $longitude
     * @return bool
     */
    function validate_coordinates($latitude, $longitude): bool
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return false;
        }
        $lat = (float)$latitude;
        $lng = (float)$longitude;

        return ($lat >= -90.0 && $lat <= 90.0) && ($lng >= -180.0 && $lng <= 180.0);
    }
}
