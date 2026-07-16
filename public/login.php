<?php
/**
 * Login dedicado — recibe a los usuarios que llegan desde la landing.
 * Solo inicia sesion; crear cuenta se hace en registro.html o index.php.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['_csrf'] ?? '';
    $expect = $_SESSION['_csrf'] ?? '';
    if ($token === '' || !hash_equals($expect, $token)) {
        $error = 'Sesion caducada. Recarga la pagina e intentalo de nuevo.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $result   = loginUser($email, $password);
        if ($result['success']) {
            DB::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$_SESSION['user_id']]);
            header('Location: dashboard.php');
            exit;
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Iniciar sesion -- Kraak Radar</title>
    <style>
        :root{--bg:#08090d;--card:#0f1117;--border:#1e2130;--text:#b4b8c5;--h:#f0f2f5;--muted:#6b6f7d;--accent:#5b6af0;--radius:10px;--radius-sm:6px}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:48px 40px;max-width:420px;width:100%}
        .logo{display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:32px;text-decoration:none}
        .logo svg{width:28px;height:28px}
        .logo span{font-weight:700;font-size:18px;color:var(--h)}
        h1{font-size:24px;color:var(--h);text-align:center;margin-bottom:4px}
        .sub{text-align:center;color:var(--muted);font-size:14px;margin-bottom:32px}
        .field{margin-bottom:20px}
        .field label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px;font-weight:500}
        .field input{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:14px;font-family:inherit}
        .field input:focus{outline:none;border-color:var(--accent)}
        .btn{width:100%;padding:12px;border-radius:var(--radius-sm);font-size:15px;font-weight:600;cursor:pointer;border:none;background:var(--accent);color:#fff}
        .btn:hover{background:#4f5de0}
        .error{color:#f87171;font-size:13px;margin-bottom:20px;padding:10px 14px;background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);border-radius:var(--radius-sm)}
        .small{font-size:12px;color:var(--muted);text-align:center;margin-top:20px}
        .small a{color:var(--accent);text-decoration:none}
        .small a:hover{text-decoration:underline}
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="card">
    <a href="/" class="logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4"/><circle cx="12" cy="12" r="3" fill="var(--accent)"/></svg>
        <span>Kraak Radar</span>
    </a>
    <h1>Inicia sesion</h1>
    <p class="sub">Accede a tu dashboard de visibilidad en IA</p>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrfField() ?>
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" required placeholder="tu@email.com" autocomplete="email">
        </div>
        <div class="field">
            <label>Contrasena</label>
            <input type="password" name="password" required placeholder="Tu contrasena" autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Entrar</button>
    </form>

    <p class="small">No tienes cuenta? <a href="registro.html">Registrate</a></p>
</div>
</body>
</html>
