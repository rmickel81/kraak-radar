<?php
/**
 * run.php - Ejecuta jobs pendientes contra OpenRouter
 * 
 * - Recupera jobs zombies (proceso muerto a mitad) usando JOB_TIMEOUT_MIN
 * - Bloqueo atómico con dueño (lock_owner): dos runners no pisan el mismo job
 * - BYOK: usa la OpenRouter key del usuario dueño del proyecto; fallback a global
 * - Registra coste real por respuesta (answers.cost_usd + cost_log)
 * 
 * Ejecución: cada 5 minutos
 */

require_once __DIR__ . '/bootstrap.php';
require_once BASE_PATH . '/lib/openrouter.php';

$batchSize = CRON_BATCH_RUN;
$owner = gethostname() . '-' . getmypid() . '-' . bin2hex(random_bytes(4));

// 1. Recuperar jobs zombies: running con lock expirado vuelven a pending
$recovered = DB::execute(
    "UPDATE jobs SET status = 'pending', locked_at = NULL, lock_owner = NULL
     WHERE status = 'running'
       AND locked_at < DATE_SUB(NOW(), INTERVAL " . (int) JOB_TIMEOUT_MIN . " MINUTE)"
);
if ($recovered > 0) {
    logMsg("run.php: {$recovered} jobs zombies recuperados a pending");
}

// 2. Bloquear batch atómicamente asignando dueño
DB::execute(
    "UPDATE jobs SET status = 'running', locked_at = NOW(), lock_owner = ?
     WHERE status = 'pending'
     ORDER BY id ASC
     LIMIT ?",
    [$owner, $batchSize]
);

// 3. Leer SOLO los jobs de este runner
$jobs = DB::fetchAll(
    "SELECT j.id, j.prompt_id, j.model_id, j.run_date,
            p.project_id, p.text AS prompt_text,
            m.slug AS model_slug, m.price_in_usd, m.price_out_usd,
            u.openrouter_key
     FROM jobs j
     JOIN prompts  p  ON p.id  = j.prompt_id
     JOIN projects pr ON pr.id = p.project_id
     JOIN users    u  ON u.id  = pr.user_id
     JOIN models   m  ON m.id  = j.model_id
     WHERE j.status = 'running' AND j.lock_owner = ?
     ORDER BY j.id",
    [$owner]
);

if (empty($jobs)) {
    logMsg("run.php: no hay jobs pendientes");
    exit;
}

// Jobs huérfanos: su prompt o modelo fue eliminado tras planificar.
// Marcarlos como error para no re-bloquearlos en bucle.
DB::execute(
    "UPDATE jobs j
     LEFT JOIN prompts p ON p.id = j.prompt_id
     LEFT JOIN models  m ON m.id = j.model_id
     SET j.status = 'error', j.last_error = 'prompt o modelo eliminado', j.lock_owner = NULL
     WHERE j.lock_owner = ? AND (p.id IS NULL OR m.id IS NULL)",
    [$owner]
);

// Releer tras limpiar huérfanos
$jobs = array_values(array_filter($jobs, fn($j) => !empty($j['prompt_text']) && !empty($j['model_slug'])));

if (empty($jobs)) {
    logMsg("run.php: solo había jobs huérfanos (marcados como error)");
    exit;
}

logMsg("run.php: ejecutando " . count($jobs) . " jobs (owner {$owner})");

// Clientes por key (BYOK: usuarios distintos pueden tener keys distintas)
$clients = [];
$getClient = function (?string $userKey) use (&$clients) {
    $key = $userKey ?: OPENROUTER_API_KEY;
    if (!isset($clients[$key])) {
        $clients[$key] = new OpenRouter($key);
    }
    return $clients[$key];
};

$done = 0;
$errors = 0;

foreach ($jobs as $job) {
    logMsg("  job #{$job['id']}: {$job['model_slug']} ← {$job['prompt_text']}");

    $client = $getClient($job['openrouter_key']);
    $result = $client->chatWithRetry($job['model_slug'], $job['prompt_text']);

    if (isset($result['error'])) {
        $attempts = (int) (DB::fetchOne("SELECT attempts FROM jobs WHERE id = ?", [$job['id']])['attempts'] ?? 0) + 1;
        $shortErr = mb_substr($result['error'], 0, 255);

        if ($attempts >= MAX_RETRIES) {
            DB::execute(
                "UPDATE jobs SET status = 'error', attempts = ?, last_error = ?, lock_owner = NULL WHERE id = ?",
                [$attempts, $shortErr, $job['id']]
            );
            logMsg("  ERROR (final): {$shortErr}");
        } else {
            DB::execute(
                "UPDATE jobs SET status = 'pending', attempts = ?, last_error = ?, locked_at = NULL, lock_owner = NULL WHERE id = ?",
                [$attempts, $shortErr, $job['id']]
            );
            logMsg("  ERROR (retry {$attempts}): {$shortErr}");
        }
        $errors++;
        continue;
    }

    // Coste real según precios del modelo (USD por 1M tokens)
    $costUsd = ($result['tokens_in'] / 1_000_000) * (float) $job['price_in_usd']
             + ($result['tokens_out'] / 1_000_000) * (float) $job['price_out_usd'];

    try {
        $answerId = DB::insert(
            "INSERT INTO answers (job_id, project_id, prompt_id, model_id, run_date, raw_text, tokens_in, tokens_out, cost_usd)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $job['id'],
                $job['project_id'],
                $job['prompt_id'],
                $job['model_id'],
                $job['run_date'],
                $result['text'],
                $result['tokens_in'],
                $result['tokens_out'],
                $costUsd,
            ]
        );
    } catch (PDOException $e) {
        // UNIQUE(job_id): ya existía respuesta para este job → marcar done y seguir
        if ($e->getCode() === '23000') {
            DB::execute("UPDATE jobs SET status = 'done', lock_owner = NULL WHERE id = ?", [$job['id']]);
            logMsg("  job #{$job['id']}: respuesta duplicada ignorada (ya existía)");
            continue;
        }
        throw $e;
    }

    // Registro de coste
    DB::insert(
        "INSERT INTO cost_log (project_id, model_id, run_date, tokens_in, tokens_out, cost_usd, source)
         VALUES (?, ?, ?, ?, ?, ?, 'openrouter')",
        [$job['project_id'], $job['model_id'], $job['run_date'],
         $result['tokens_in'], $result['tokens_out'], $costUsd]
    );

    DB::execute("UPDATE jobs SET status = 'done', lock_owner = NULL WHERE id = ?", [$job['id']]);
    $done++;
}

logMsg("run.php: {$done} OK, {$errors} errores");
