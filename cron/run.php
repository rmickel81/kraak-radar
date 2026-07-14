<?php
/**
 * run.php - Ejecuta jobs pendientes contra OpenRouter
 * 
 * Coge batch de N jobs pending, los ejecuta contra la API del modelo
 * y guarda las respuestas en answers.
 * 
 * Idempotente: los jobs se marcan como running con lock.
 * 
 * Ejecución: cada 5 minutos
 */

require_once __DIR__ . '/bootstrap.php';
require_once BASE_PATH . '/lib/openrouter.php';

$batchSize = CRON_BATCH_RUN;

// Bloquear batch de jobs pending (atómico)
DB::execute(
    "UPDATE jobs SET status = 'running', locked_at = NOW()
     WHERE status = 'pending'
     ORDER BY id ASC
     LIMIT ?",
    [$batchSize]
);

$jobs = DB::fetchAll(
    "SELECT j.id, j.prompt_id, j.model_id,
            p.project_id, p.text AS prompt_text,
            m.slug AS model_slug
     FROM jobs j
     JOIN prompts p ON p.id = j.prompt_id
     JOIN models m  ON m.id  = j.model_id
     WHERE j.status = 'running' AND j.locked_at IS NOT NULL
     ORDER BY j.id
     LIMIT ?",
    [$batchSize]
);

if (empty($jobs)) {
    logMsg("run.php: no hay jobs pendientes");
    exit;
}

logMsg("run.php: ejecutando " . count($jobs) . " jobs");

// Obtener API key del usuario del primer proyecto
// (BYOK: cada usuario puede tener su propia key)
// Para MVP: usamos la key de OpenRouter configurada
$openrouter = new OpenRouter(OPENROUTER_API_KEY);

$done = 0;
$errors = 0;

foreach ($jobs as $job) {
    logMsg("  job #{$job['id']}: {$job['model_slug']} ← {$job['prompt_text']}");

    $result = $openrouter->chatWithRetry($job['model_slug'], $job['prompt_text']);

    if (isset($result['error'])) {
        // Incrementar intentos
        $attempts = DB::fetchOne(
            "SELECT attempts FROM jobs WHERE id = ?", [$job['id']]
        );
        $newAttempts = ($attempts['attempts'] ?? 0) + 1;

        if ($newAttempts >= MAX_RETRIES) {
            DB::execute(
                "UPDATE jobs SET status = 'error', attempts = ?, last_error = ? WHERE id = ?",
                [$newAttempts, $result['error'], $job['id']]
            );
            logMsg("  ERROR (final): {$result['error']}");
        } else {
            DB::execute(
                "UPDATE jobs SET status = 'pending', attempts = ?, last_error = ? WHERE id = ?",
                [$newAttempts, $result['error'], $job['id']]
            );
            logMsg("  ERROR (retry {$newAttempts}): {$result['error']}");
        }
        $errors++;
        continue;
    }

    // Guardar respuesta
    DB::insert(
        "INSERT INTO answers (job_id, project_id, prompt_id, model_id, run_date, raw_text, tokens_in, tokens_out)
         VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?)",
        [
            $job['id'],
            $job['project_id'],
            $job['prompt_id'],
            $job['model_id'],
            $result['text'],
            $result['tokens_in'],
            $result['tokens_out'],
        ]
    );

    DB::execute("UPDATE jobs SET status = 'done' WHERE id = ?", [$job['id']]);
    $done++;
}

logMsg("run.php: {$done} OK, {$errors} errores");
