<?php
/**
 * API: Registro de usuarios de Kraak Radar
 * Recibe POST del formulario en registro.html y guarda en BD
 * CORS habilitado para GitHub Pages
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://rmickel81.github.io');
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

$input = json_decode(file_get_contents('php://input'), true);

$name   = trim($input['name'] ?? '');
$email  = trim($input['email'] ?? '');
$plan   = trim($input['plan'] ?? '');
$domain = trim($input['domain'] ?? '');

if (!$name || !$email || !$plan || !$domain) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son obligatorios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email no valido']);
    exit;
}

$allowed_plans = ['Basic', 'Pro', 'Agency'];
if (!in_array($plan, $allowed_plans)) {
    http_response_code(400);
    echo json_encode(['error' => 'Plan no valido']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=kraak-db;port=3306;dbname=kraak_radar;charset=utf8mb4',
        'kraak',
        'kraak_test_2026',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare(
        'INSERT INTO registrations (name, email, plan, domain) VALUES (:name, :email, :plan, :domain)'
    );
    $stmt->execute([
        ':name'   => $name,
        ':email'  => $email,
        ':plan'   => $plan,
        ':domain' => $domain,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registro completado',
        'redirect' => $plan === 'Basic' ? 'https://buy.stripe.com/placeholder' : 'https://buy.stripe.com/placeholder-pro'
    ]);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['error' => 'Este email ya esta registrado']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno del servidor']);
    }
}
