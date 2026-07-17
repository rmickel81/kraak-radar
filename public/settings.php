<?php
/**
 * Panel de APIs y configuración de claves
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
$user = requireLogin();

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    
    $openrouterKey = trim($_POST['openrouter_key'] ?? '');
    $deepseekKey   = trim($_POST['deepseek_key'] ?? '');
    
    // Validar ANTES de guardar: solo persistimos claves que funcionan
    if ($openrouterKey) {
        $ch = curl_init('https://openrouter.ai/api/v1/auth/key');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $openrouterKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            $error = 'La key de OpenRouter no es válida (HTTP ' . $httpCode . '). ';
        }
    }
    
    if ($deepseekKey && empty($error)) {
        $ch = curl_init('https://api.deepseek.com/models');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $deepseekKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            $error .= 'La key de DeepSeek no es válida (HTTP ' . $httpCode . '). ';
        }
    }
    
    if (empty($error)) {
        DB::execute(
            "UPDATE users SET openrouter_key = ?, deepseek_key = ? WHERE id = ?",
            [$openrouterKey ?: null, $deepseekKey ?: null, $user['id']]
        );
        $saved = true;
        $user = DB::fetchOne('SELECT * FROM users WHERE id = ?', [$user['id']]);
    }
}

// Stats de gasto mensual
$monthlyCost = DB::fetchOne(
    "SELECT COALESCE(SUM(cost_usd), 0) as total_cost,
            COALESCE(SUM(tokens_in), 0) as total_in,
            COALESCE(SUM(tokens_out), 0) as total_out
     FROM cost_log
     WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)
       AND run_date >= DATE_FORMAT(NOW(), '%Y-%m-01')",
    [$user['id']]
);

$hasDeepSeek = !empty($user['deepseek_key']);
$hasOpenRouter = !empty($user['openrouter_key']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kraak Radar — APIs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>APIs y Conexiones</h1>
            <p class="page-desc">Gestiona las claves de API para los modelos que trackeas.</p>
        </div>

        <?php if ($saved): ?>
            <div class="alert alert-success">Claves guardadas y verificadas correctamente.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" class="form-api">
                <?= csrfField() ?>
                
                <div class="api-section">
                    <h3>
                        <span class="api-icon"><?php echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'; ?></span>
                        OpenRouter
                        <?php if ($hasOpenRouter): ?><span class="badge badge-ok">Conectado</span>
                        <?php else: ?><span class="badge badge-off">Sin configurar</span><?php endif; ?>
                    </h3>
                    <p class="api-desc">Proxy para consultar todos los modelos (OpenAI, Google, Anthropic, Perplexity, Qwen, etc.) desde una sola API. <a href="https://openrouter.ai/keys" target="_blank">Obtener key</a></p>
                    <div class="input-group">
                        <input type="password" name="openrouter_key" 
                               value="<?= htmlspecialchars($user['openrouter_key'] ?? '') ?>"
                               placeholder="sk-or-v1-..." class="input-mono" 
                               id="or-key">
                        <button type="button" class="btn-eye" onclick="toggleKey('or-key')" title="Mostrar/Ocultar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="api-divider"></div>

                <div class="api-section">
                    <h3>
                        <span class="api-icon"><?php echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/></svg>'; ?></span>
                        DeepSeek
                        <?php if ($hasDeepSeek): ?><span class="badge badge-ok">Conectado</span>
                        <?php else: ?><span class="badge badge-off">Sin configurar</span><?php endif; ?>
                    </h3>
                    <p class="api-desc">Usado por el analyzer para extraer menciones, sentimiento y fuentes de las respuestas. <a href="https://platform.deepseek.com/api_keys" target="_blank">Obtener key</a></p>
                    <div class="input-group">
                        <input type="password" name="deepseek_key" 
                               value="<?= htmlspecialchars($user['deepseek_key'] ?? '') ?>"
                               placeholder="sk-..." class="input-mono"
                               id="ds-key">
                        <button type="button" class="btn-eye" onclick="toggleKey('ds-key')" title="Mostrar/Ocultar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <p class="api-hint">El analyzer usa <strong>deepseek-v4-flash</strong> (coste ~$0.0001 por análisis).</p>
                </div>

                <div class="api-divider"></div>

                <div class="api-section">
                    <h3><span class="api-icon"><?php echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'; ?></span> Consumo estimado este mes</h3>
                    <div class="cost-stats">
                        <div class="cost-stat">
                            <span class="cost-value">$<?= number_format($monthlyCost['total_cost'] ?? 0, 4) ?></span>
                            <span class="cost-label">Gasto total</span>
                        </div>
                        <div class="cost-stat">
                            <span class="cost-value"><?= number_format($monthlyCost['total_in'] ?? 0) ?></span>
                            <span class="cost-label">Tokens entrada</span>
                        </div>
                        <div class="cost-stat">
                            <span class="cost-value"><?= number_format($monthlyCost['total_out'] ?? 0) ?></span>
                            <span class="cost-label">Tokens salida</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary btn-lg">Guardar y verificar claves</button>
            </form>
        </div>
    </main>
</div>

<script>
function toggleKey(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
