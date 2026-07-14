<?php
/**
 * Auth: login, sesiones seguras, CSRF, rate limiting
 * 
 * Convenciones:
 * - Siempre usa initSession() antes de tocar $_SESSION
 * - loginUser() regenera el ID de sesion para evitar session fixation
 * - requireLogin() redirige a index.php si no hay sesion activa
 */

function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    $regenerated = $_SESSION['_last_regenerated'] ?? 0;
    if (time() - $regenerated > 3600) {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    }
}

function requireLogin(): array {
    initSession();

    if (empty($_SESSION['user_id'])) {
        header('Location: index.php?r=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    $user = DB::fetchOne('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);
    if (!$user) {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    return $user;
}

// ── CSRF ──

function csrfToken(): string {
    initSession();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

function csrfVerify(): void {
    initSession();
    $token  = $_POST['_csrf'] ?? '';
    $expect = $_SESSION['_csrf'] ?? '';
    if ($token === '' || !hash_equals($expect, $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token invalido. Recarga la pagina.']));
    }
}

// ── Rate Limiting ──

function checkRateLimit(string $action, int $max = 5, int $window = 300): bool {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $file = sys_get_temp_dir() . '/krl_' . md5($action . '_' . $ip);
    $now  = time();

    $attempts = [];
    if (is_file($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $attempts = $data;
        }
    }

    $attempts = array_values(array_filter($attempts, fn($t) => $t > $now - $window));

    if (count($attempts) >= $max) {
        return false;
    }

    $attempts[] = $now;
    file_put_contents($file, json_encode($attempts), LOCK_EX);
    return true;
}

function rateLimitRemaining(string $action): int {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $file = sys_get_temp_dir() . '/krl_' . md5($action . '_' . $ip);
    if (!is_file($file)) return 0;

    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || empty($data)) return 0;

    $remaining = 300 - (time() - min($data));
    return max(0, $remaining);
}

// ── Login / Logout ──

function loginUser(string $email, string $password): array {
    $result = ['success' => false, 'error' => ''];

    if (!checkRateLimit('login')) {
        $result['error'] = 'Demasiados intentos. Espera ' . rateLimitRemaining('login') . ' segundos.';
        return $result;
    }

    $user = DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $result['error'] = 'Email o contrasena incorrectos.';
        return $result;
    }

    initSession();
    session_regenerate_id(true);
    $_SESSION['user_id']            = (int) $user['id'];
    $_SESSION['_last_regenerated']  = time();
    unset($_SESSION['_csrf']);

    $result['success'] = true;
    return $result;
}

function logoutUser(): void {
    initSession();
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    $_SESSION = [];
    session_destroy();
}
