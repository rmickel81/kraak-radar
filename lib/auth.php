<?php
/**
 * Auth: login, sesiones seguras, CSRF, rate limiting
 */

// Iniciar sesión segura si no está iniciada
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

    // Regenerar ID periódicamente
    $regenerated = $_SESSION['_last_regenerated'] ?? 0;
    if (time() - $regenerated > 3600) {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    }
}

/**
 * Requiere login. Devuelve array con datos del usuario.
 */
function requireLogin(): array {
    initSession();

    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    $user = DB::fetchOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!$user) {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    return $user;
}

// ── CSRF ───────────────────────────────────────────────────────────────

/**
 * Genera y guarda un token CSRF en sesión.
 */
function csrfToken(): string {
    initSession();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/**
 * Renderiza un campo hidden con el token CSRF.
 */
function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

/**
 * Verifica el token CSRF. Muestra error y muere si falla.
 */
function csrfVerify(): void {
    initSession();
    $token = $_POST['_csrf'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido. Recarga la página.']);
        exit;
    }
}

/**
 * Verifica CSRF solo si es POST. Para llamadas AJAX que verifican aparte.
 */
function csrfVerifyPost(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfVerify();
    }
}

// ── Rate Limiting (login) ──────────────────────────────────────────────

/**
 * Rate limiter simple basado en IP + archivo.
 * Permite N intentos en una ventana de tiempo.
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/krl_' . md5($action . '_' . $ip);
    $now = time();

    $data = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true) ?: [];
    }

    // Limpiar entradas antiguas
    $data = array_filter($data, fn($t) => $t > $now - $windowSeconds);

    if (count($data) >= $maxAttempts) {
        return false; // Bloqueado
    }

    $data[] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true; // Permiso concedido
}

/**
 * Cuántos segundos quedan para que se libere el rate limit.
 */
function rateLimitRemaining(string $action): int {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/krl_' . md5($action . '_' . $ip);
    if (!file_exists($file)) return 0;

    $data = json_decode(file_get_contents($file), true) ?: [];
    if (empty($data)) return 0;

    $oldest = min($data);
    $remaining = 300 - (time() - $oldest);
    return max(0, $remaining);
}

// ── Login / Logout ─────────────────────────────────────────────────────

function loginUser(string $email, string $password): array {
    $result = ['success' => false, 'error' => ''];

    if (!checkRateLimit('login')) {
        $remaining = rateLimitRemaining('login');
        $result['error'] = "Demasiados intentos. Espera {$remaining} segundos.";
        return $result;
    }

    $user = DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $result['error'] = 'Email o contraseña incorrectos.';
        return $result;
    }

    initSession();
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['_last_regenerated'] = time();
    $result['success'] = true;
    return $result;
}

function logoutUser(): void {
    initSession();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    session_destroy();
}
