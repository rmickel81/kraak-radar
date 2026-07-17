<?php
/**
 * API pública: registro de leads desde la landing (GitHub Pages)
 * 
 * - CORS restringido al origen de la landing (config: LANDING_ORIGIN)
 * - Rate limiting por IP (5 registros/hora)
 * - Planes normalizados al ENUM de la app: starter | pro | agency
 * - Credenciales desde config.php (nada hardcodeado)
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . LANDING_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!checkRateLimit('landing_register', 5, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Demasiadas solicitudes. Inténtalo más tarde.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$name   = trim($input['name'] ?? '');
$email  = trim($input['email'] ?? '');
$plan   = trim($input['plan'] ?? '');
$domain = trim($input['domain'] ?? '');

if ($name === '' || $email === '' || $plan === '' || $domain === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son obligatorios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email no válido']);
    exit;
}

// Normalizar plan: la landing usa Basic/Pro/Agency; la app usa starter/pro/agency
$planMap = [
    'basic' => 'starter', 'starter' => 'starter',
    'pro' => 'pro',
    'agency' => 'agency', 'enterprise' => 'agency',
];
$planNorm = $planMap[mb_strtolower($plan)] ?? null;
if ($planNorm === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Plan no válido']);
    exit;
}

// Dominio: saneado básico (solo host, sin esquema ni path)
$domain = preg_replace('#^https?://#i', '', $domain);
$domain = strtok($domain, '/');
if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dominio no válido']);
    exit;
}

try {
    DB::execute(
        'INSERT INTO registrations (name, email, plan, domain) VALUES (?, ?, ?, ?)',
        [mb_substr($name, 0, 120), $email, $planNorm, mb_substr($domain, 0, 190)]
    );

    $paymentLinks = defined('STRIPE_PAYMENT_LINKS') ? STRIPE_PAYMENT_LINKS : [];
    $redirect = $paymentLinks[$planNorm] ?? '';

    http_response_code(201);
    echo json_encode([
        'success'  => true,
        'message'  => 'Registro completado',
        'plan'     => $planNorm,
        'redirect' => $redirect !== '' ? $redirect : null,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['error' => 'Este email ya está registrado']);
    } else {
        error_log('register.php DB error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error interno del servidor']);
    }
}
