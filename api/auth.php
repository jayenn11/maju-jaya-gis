<?php
/**
 * Endpoint API Autentikasi Admin (Login & Logout)
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 */

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
    (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
);

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'   => 'success',
            'message'  => 'Berhasil keluar dari sistem.',
            'redirect' => base_url('index.php?page=login'),
        ]);
        exit;
    }

    header('Location: ' . base_url('index.php?page=login&logout=1'));
    exit;
}

if ($method === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($username === '' || $password === '') {
        if ($isAjax) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'error',
                'message' => 'Username dan kata sandi wajib diisi.',
            ]);
            exit;
        }
        $_SESSION['login_error'] = 'Username dan kata sandi wajib diisi.';
        header('Location: ' . base_url('index.php?page=login'));
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, name, username, password FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'error',
                'message' => 'Username atau kata sandi tidak valid.',
            ]);
            exit;
        }
        $_SESSION['login_error'] = 'Username atau kata sandi tidak valid.';
        header('Location: ' . base_url('index.php?page=login'));
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']       = (int)$admin['id'];
    $_SESSION['admin_name']     = $admin['name'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['flash_toast']    = [
        'type'    => 'success',
        'title'   => 'Selamat Datang!',
        'message' => 'Halo ' . $admin['name'] . ', Anda berhasil masuk ke Dashboard SIG PT Maju Jaya Food.',
    ];

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'   => 'success',
            'message'  => 'Login berhasil.',
            'redirect' => base_url('index.php?page=dashboard'),
        ]);
        exit;
    }

    header('Location: ' . base_url('index.php?page=dashboard'));
    exit;
}

http_response_code(405);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'error', 'message' => 'Metode request tidak didukung.']);
