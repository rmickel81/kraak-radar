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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $action   = $_POST['action'] ?? 'login';
    
    if ($action === 'login') {
        $result = loginUser($email, $password);
        if ($result['success']) {
            DB::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$_SESSION['user_id']]);
            header('Location: ' . safeRedirect($_GET['r'] ?? ''));
            exit;
        }
        $error = $result['error'];
    } elseif ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email no válido';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif (!checkRateLimit('register', 5, 3600)) {
            $error = 'Demasiados registros desde tu IP. Inténtalo más tarde.';
        } else {
            $existing = DB::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing) {
                $error = 'Ya existe una cuenta con ese email';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $newId = DB::insert(
                    'INSERT INTO users (email, password_hash, name, plan) VALUES (?, ?, ?, ?)',
                    [$email, $hash, $name, 'free']
                );
                initSession();
                session_regenerate_id(true);
                $_SESSION['user_id']           = $newId;
                $_SESSION['_last_regenerated'] = time();
                unset($_SESSION['_csrf']);
                DB::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$newId]);
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
    <title>Kraak Radar — Acceso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0d1117; --bg-card: #161b22; --border: #30363d; --border-light: #21262d;
            --text: #c9d1d9; --text-h: #f0f6fc; --text-muted: #8b949e; --text-dim: #6e7681;
            --accent: #58a6ff; --accent-hover: #79b8ff; --green: #3fb950; --red: #f85149;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg); color: var(--text);
            display: flex; min-height: 100vh; font-size: 14px; line-height: 1.6;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { color: var(--accent-hover); }

        /* ── Panel de marca ── */
        .brand-panel {
            flex: 1.15; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
            padding: 56px 64px;
            background:
                radial-gradient(600px 400px at 20% 20%, rgba(88,166,255,0.08), transparent 60%),
                radial-gradient(500px 380px at 85% 85%, rgba(63,185,80,0.06), transparent 60%),
                #0a0e14;
        }
        .brand-panel::before {
            content: ''; position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(88,166,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(88,166,255,0.04) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
        }
        .brand-inner { position: relative; max-width: 460px; }
        .brand-mark { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
        .brand-mark svg { color: var(--accent); }
        .brand-name { font-size: 20px; font-weight: 800; color: var(--text-h); letter-spacing: -0.3px; }
        .brand-tag { font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; }
        h1.brand-h {
            font-size: 34px; font-weight: 800; color: var(--text-h);
            line-height: 1.2; letter-spacing: -0.8px; margin-bottom: 16px;
        }
        h1.brand-h em { font-style: normal; color: var(--accent); }
        .brand-lead { color: var(--text-muted); font-size: 15px; margin-bottom: 36px; max-width: 400px; }

        .radar-visual { position: relative; width: 200px; height: 200px; margin: 8px 0 36px; }
        .radar-ring { position: absolute; border: 1px solid rgba(88,166,255,0.22); border-radius: 50%; }
        .radar-ring.r1 { inset: 0; }
        .radar-ring.r2 { inset: 30px; }
        .radar-ring.r3 { inset: 60px; }
        .radar-cross-h, .radar-cross-v { position: absolute; background: rgba(88,166,255,0.10); }
        .radar-cross-h { left: 0; right: 0; top: 50%; height: 1px; }
        .radar-cross-v { top: 0; bottom: 0; left: 50%; width: 1px; }
        .radar-sweep {
            position: absolute; inset: 0; border-radius: 50%;
            background: conic-gradient(from 0deg, rgba(88,166,255,0.40), rgba(88,166,255,0.05) 70deg, transparent 90deg);
            animation: kr-sweep 4.5s linear infinite;
        }
        @keyframes kr-sweep { to { transform: rotate(360deg); } }
        .radar-blip {
            position: absolute; width: 7px; height: 7px; border-radius: 50%;
            background: var(--accent); box-shadow: 0 0 10px var(--accent);
            animation: kr-blip 4.5s ease-in-out infinite;
        }
        .radar-blip.b1 { top: 27%; left: 63%; }
        .radar-blip.b2 { top: 60%; left: 33%; background: var(--green); box-shadow: 0 0 10px var(--green); animation-delay: 1.4s; }
        .radar-blip.b3 { top: 44%; left: 74%; width: 5px; height: 5px; animation-delay: 2.6s; }
        @keyframes kr-blip { 0%, 100% { opacity: 0.2; } 50% { opacity: 1; } }
        .radar-center {
            position: absolute; top: 50%; left: 50%; width: 9px; height: 9px;
            transform: translate(-50%, -50%); background: var(--accent); border-radius: 50%;
            box-shadow: 0 0 12px var(--accent);
        }

        .brand-points { display: flex; flex-direction: column; gap: 14px; }
        .brand-point { display: flex; gap: 12px; align-items: flex-start; color: var(--text-muted); font-size: 14px; }
        .brand-point svg { flex-shrink: 0; margin-top: 2px; color: var(--green); }
        .brand-point strong { color: var(--text-h); font-weight: 600; }
        .brand-foot { position: absolute; bottom: 28px; left: 64px; font-size: 12px; color: var(--text-dim); }

        /* ── Panel de acceso ── */
        .auth-panel {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 48px 32px; border-left: 1px solid var(--border-light); background: var(--bg);
        }
        .auth-box { width: 100%; max-width: 380px; }
        .auth-box h2 { font-size: 22px; font-weight: 700; color: var(--text-h); margin-bottom: 4px; letter-spacing: -0.3px; }
        .auth-sub { color: var(--text-muted); font-size: 13px; margin-bottom: 28px; }

        .tabs {
            display: flex; gap: 4px; margin-bottom: 24px;
            background: var(--bg-card); border: 1px solid var(--border-light);
            border-radius: 8px; padding: 4px;
        }
        .tab {
            flex: 1; text-align: center; padding: 8px; cursor: pointer;
            color: var(--text-muted); font-size: 13px; font-weight: 600;
            border-radius: 6px; transition: all 0.15s; user-select: none;
        }
        .tab:hover { color: var(--text); }
        .tab.active { background: rgba(88,166,255,0.12); color: var(--accent); }

        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
        .field input {
            width: 100%; padding: 10px 12px; background: var(--bg);
            border: 1px solid var(--border); border-radius: 6px;
            color: var(--text-h); font-size: 14px; font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .field input::placeholder { color: var(--text-dim); }
        .field input:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(88,166,255,0.15);
        }
        button[type="submit"] {
            width: 100%; padding: 11px; margin-top: 6px;
            background: #238636; border: 1px solid rgba(240,246,252,0.1);
            border-radius: 6px; color: #fff; font-size: 14px; font-weight: 600;
            font-family: inherit; cursor: pointer; transition: background 0.15s, transform 0.05s;
        }
        button[type="submit"]:hover { background: #2ea043; }
        button[type="submit"]:active { transform: translateY(1px); }
        button[type="submit"]:disabled { opacity: 0.5; cursor: not-allowed; }

        .error {
            color: var(--red); font-size: 13px; margin-bottom: 18px;
            padding: 10px 12px; background: rgba(248,81,73,0.08);
            border: 1px solid rgba(248,81,73,0.3); border-radius: 6px;
        }
        .hidden { display: none; }
        .auth-foot { text-align: center; margin-top: 28px; font-size: 12px; color: var(--text-dim); }
        .auth-foot a { font-weight: 500; }

        @media (max-width: 920px) {
            body { flex-direction: column; }
            .brand-panel { padding: 40px 32px 32px; flex: none; }
            .brand-inner { max-width: none; }
            h1.brand-h { font-size: 26px; }
            .radar-visual { display: none; }
            .brand-foot { position: static; margin-top: 24px; }
            .auth-panel { border-left: none; border-top: 1px solid var(--border-light); }
        }
    </style>
</head>
<body>
    <div class="brand-panel">
        <div class="brand-inner">
            <div class="brand-mark">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>
                    <line x1="12" y1="12" x2="19" y2="5"/>
                </svg>
                <div>
                    <div class="brand-name">Kraak Radar</div>
                    <div class="brand-tag">GEO / AEO Tracker</div>
                </div>
            </div>

            <h1 class="brand-h">Tu marca, en el radar de <em>todos los asistentes de IA</em></h1>
            <p class="brand-lead">Mide y mejora cómo apareces cuando la gente pregunta a ChatGPT, Gemini, DeepSeek o Perplexity sobre tu sector.</p>

            <div class="radar-visual" aria-hidden="true">
                <div class="radar-ring r1"></div>
                <div class="radar-ring r2"></div>
                <div class="radar-ring r3"></div>
                <div class="radar-cross-h"></div>
                <div class="radar-cross-v"></div>
                <div class="radar-sweep"></div>
                <div class="radar-blip b1"></div>
                <div class="radar-blip b2"></div>
                <div class="radar-blip b3"></div>
                <div class="radar-center"></div>
            </div>

            <div class="brand-points">
                <div class="brand-point">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>9 modelos trackeados</strong> — incluidos los chinos (Qwen, GLM, Kimi, MiniMax) que otros ignoran.</span>
                </div>
                <div class="brand-point">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>Visibilidad, posición y sentimiento</strong> — con benchmark automático contra tus competidores.</span>
                </div>
                <div class="brand-point">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>BYOK</strong> — usas tus propias API keys. Tus datos, tu coste, tu control.</span>
                </div>
            </div>
        </div>
        <div class="brand-foot">Kraak Radar — <a href="https://github.com/rmickel81/kraak-radar">código abierto</a></div>
    </div>

    <div class="auth-panel">
        <div class="auth-box">
            <h2>Accede a tu cuenta</h2>
            <p class="auth-sub">Gestiona la visibilidad de tu marca en IA.</p>

            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="tabs">
                <div class="tab active" onclick="showTab('login')">Entrar</div>
                <div class="tab" onclick="showTab('register')">Crear cuenta</div>
            </div>

            <form method="post" id="login-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="login">
                <div class="field">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" placeholder="tu@empresa.com" required autocomplete="email">
                </div>
                <div class="field">
                    <label for="login-password">Contraseña</label>
                    <input type="password" id="login-password" name="password" placeholder="Tu contraseña" required autocomplete="current-password">
                </div>
                <button type="submit">Entrar</button>
            </form>

            <form method="post" id="register-form" class="hidden">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="register">
                <div class="field">
                    <label for="reg-name">Nombre</label>
                    <input type="text" id="reg-name" name="name" placeholder="Tu nombre" required autocomplete="name">
                </div>
                <div class="field">
                    <label for="reg-email">Email</label>
                    <input type="email" id="reg-email" name="email" placeholder="tu@empresa.com" required autocomplete="email">
                </div>
                <div class="field">
                    <label for="reg-password">Contraseña</label>
                    <input type="password" id="reg-password" name="password" placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                </div>
                <button type="submit">Crear cuenta gratis</button>
            </form>

            <div class="auth-foot">
                Al entrar aceptas la <a href="https://github.com/rmickel81/kraak-radar/blob/main/LICENSE">licencia de uso</a>.
            </div>
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
