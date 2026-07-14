<?php
/**
 * Login / Registro con rate limiting y CSRF
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';

initSession();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$rateLimited = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $action   = $_POST['action'] ?? 'login';
    
    if ($action === 'login') {
        $result = loginUser($email, $password);
        if ($result['success']) {
            DB::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$_SESSION['user_id']]);
            $redirect = $_GET['r'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            exit;
        }
        $error = $result['error'];
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
                initSession();
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
            background: #0d1117;
            color: #c9d1d9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .auth-box {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 40px;
            width: 400px;
            max-width: 90vw;
            box-shadow: 0 4px 24px rgba(0,0,0,0.4);
        }
        h1 { font-size: 24px; margin-bottom: 4px; color: #f0f6fc; }
        .subtitle { color: #8b949e; margin-bottom: 24px; font-size: 14px; }
        .tabs { display: flex; margin-bottom: 24px; border-bottom: 1px solid #30363d; }
        .tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; color: #8b949e; font-size: 14px; font-weight: 500; transition: all 0.15s; }
        .tab:hover { color: #c9d1d9; }
        .tab.active { color: #58a6ff; border-bottom: 2px solid #58a6ff; }
        input {
            width: 100%; padding: 10px 12px; background: #0d1117;
            border: 1px solid #30363d; border-radius: 6px; color: #c9d1d9;
            margin-bottom: 16px; font-size: 14px;
        }
        input:focus { outline: none; border-color: #58a6ff; }
        button {
            width: 100%; padding: 10px; background: #238636;
            border: 1px solid rgba(240,246,252,0.1); border-radius: 6px;
            color: #fff; font-size: 15px; font-weight: 500; cursor: pointer;
        }
        button:hover { background: #2ea043; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .error { color: #f85149; font-size: 13px; margin-bottom: 16px; padding: 8px 12px; background: rgba(248,81,73,0.1); border: 1px solid rgba(248,81,73,0.3); border-radius: 6px; }
        .hidden { display: none; }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo span { font-size: 28px; font-weight: 700; background: linear-gradient(135deg, #58a6ff, #3fb950); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .footer { text-align: center; margin-top: 16px; font-size: 12px; color: #6e7681; }
        .footer a { color: #58a6ff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="auth-box">
        <div class="logo"><span>Kraak Radar</span></div>
        <p class="subtitle">Visibilidad de tu marca en asistentes de IA</p>

        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="showTab('login')">Entrar</div>
            <div class="tab" onclick="showTab('register')">Registrarse</div>
        </div>

        <form method="post" id="login-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="login">
            <input type="email" name="email" placeholder="Email" required autocomplete="email">
            <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
            <button type="submit" <?= $rateLimited ? 'disabled' : '' ?>>
                <?= $rateLimited ? 'Espera...' : 'Entrar' ?>
            </button>
        </form>

        <form method="post" id="register-form" class="hidden">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="register">
            <input type="text" name="name" placeholder="Nombre" required autocomplete="name">
            <input type="email" name="email" placeholder="Email" required autocomplete="email">
            <input type="password" name="password" placeholder="Contraseña (mín 6 caracteres)" required autocomplete="new-password">
            <button type="submit">Crear cuenta</button>
        </form>

        <div class="footer">
            Kraak Radar &mdash; <a href="https://github.com/rmickel81/kraak-radar">código abierto</a>
        </div>
    </div>

    <script>
    function showTab(tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('form').forEach(f => f.classList.add('hidden'));
        document.querySelector('.tabs :' + (tab === 'login' ? 'first' : 'last') + '-child').classList.add('active');
        document.getElementById(tab + '-form').classList.remove('hidden');
    }
    </script>
</body>
</html>
