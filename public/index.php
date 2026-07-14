<?php
/**
 * Login / Registro
 */
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $action   = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $user = DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Email o contraseña incorrectos';
    } elseif ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            $existing = DB::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing) {
                $error = 'Ya existe una cuenta con ese email';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                DB::insert(
                    'INSERT INTO users (email, password_hash, name, plan) VALUES (?, ?, ?, ?)',
                    [$email, $hash, $name, 'free']
                );
                $_SESSION['user_id'] = (int) DB::get()->lastInsertId();
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kraak Radar — Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0f;
            color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .auth-box {
            background: #14141f;
            border: 1px solid #2a2a3a;
            border-radius: 12px;
            padding: 40px;
            width: 400px;
            max-width: 90vw;
        }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .subtitle { color: #888; margin-bottom: 24px; font-size: 14px; }
        .tabs { display: flex; gap: 0; margin-bottom: 24px; border-bottom: 1px solid #2a2a3a; }
        .tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; color: #888; }
        .tab.active { color: #fff; border-bottom: 2px solid #6c5ce7; }
        input { width: 100%; padding: 12px; background: #1a1a2e; border: 1px solid #2a2a3a; border-radius: 8px; color: #fff; margin-bottom: 16px; font-size: 14px; }
        input:focus { outline: none; border-color: #6c5ce7; }
        button { width: 100%; padding: 12px; background: #6c5ce7; border: none; border-radius: 8px; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        button:hover { background: #5a4bd1; }
        .error { color: #ff6b6b; font-size: 13px; margin-bottom: 16px; }
        .hidden { display: none; }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo span { font-size: 28px; font-weight: 700; background: linear-gradient(135deg, #6c5ce7, #a29bfe); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .footer { text-align: center; margin-top: 12px; font-size: 12px; color: #555; }
    </style>
</head>
<body>
    <div class="auth-box">
        <div class="logo"><span>Kraak Radar</span></div>
        <p class="subtitle">Visibilidad de tu marca en IA</p>

        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="showTab('login')">Entrar</div>
            <div class="tab" onclick="showTab('register')">Registrarse</div>
        </div>

        <form method="post" id="login-form">
            <input type="hidden" name="action" value="login">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Entrar</button>
        </form>

        <form method="post" id="register-form" class="hidden">
            <input type="hidden" name="action" value="register">
            <input type="text" name="name" placeholder="Nombre" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Contraseña (mín 6 caracteres)" required>
            <button type="submit">Crear cuenta</button>
        </form>

        <div class="footer">Kraak Radar &mdash; MIT</div>
    </div>

    <script>
    function showTab(tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('form').forEach(f => f.classList.add('hidden'));
        if (tab === 'login') {
            document.querySelector('.tabs :first-child').classList.add('active');
            document.getElementById('login-form').classList.remove('hidden');
        } else {
            document.querySelector('.tabs :last-child').classList.add('active');
            document.getElementById('register-form').classList.remove('hidden');
        }
    }
    </script>
</body>
</html>
