<?php
/**
 * Seed: genera datos de prueba para el dashboard
 * 
 * Crea prompts de ejemplo, respuestas simuladas y menciones,
 * y recalcula snapshots con la MISMA lógica que el cron (lib/aggregation.php).
 * 
 * Seguridad: solo POST con CSRF válido. GET muestra la confirmación.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/aggregation.php';
$user = requireLogin();

$projectId = (int) ($_REQUEST['project'] ?? 0);
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]);

if (!$project) {
    header('Location: dashboard.php');
    exit;
}

// GET → pantalla de confirmación (nada se modifica por GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kraak Radar — Datos de prueba</title>
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
            <h1>Generar datos de prueba</h1>
            <p class="page-desc">Proyecto: <strong><?= htmlspecialchars($project['name']) ?></strong></p>
        </div>
        <div class="card" style="max-width:560px;">
            <p class="note" style="margin-bottom:16px;">
                Se generarán respuestas simuladas para los últimos 14 días: prompts de ejemplo,
                menciones de tu marca y competidores, fuentes citadas y costes ficticios.
                Los snapshots se recalculan con la lógica real del sistema.
            </p>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="project" value="<?= $projectId ?>">
                <button type="submit" class="btn-primary">Generar ahora</button>
                <a href="dashboard.php?project=<?= $projectId ?>" style="margin-left:12px;">Cancelar</a>
            </form>
        </div>
    </main>
</div>
</body>
</html>
    <?php
    exit;
}

csrfVerify();

$daysBack = 14;
$today = date('Y-m-d');
$models = DB::fetchAll('SELECT id, slug FROM models WHERE is_active = 1');

// 1. Temas de ejemplo si no hay
$topics = DB::fetchAll('SELECT id FROM topics WHERE project_id = ?', [$projectId]);
if (empty($topics)) {
    foreach (['Visibilidad General', 'Competidores', 'Tendencias del Sector'] as $name) {
        DB::insert("INSERT INTO topics (project_id, name) VALUES (?, ?)", [$projectId, $name]);
    }
    $topics = DB::fetchAll('SELECT id FROM topics WHERE project_id = ?', [$projectId]);
}

// 2. Prompts de ejemplo si no hay
$prompts = DB::fetchAll('SELECT id FROM prompts WHERE project_id = ?', [$projectId]);
if (empty($prompts)) {
    $promptTexts = [
        ['¿Cuál es la mejor herramienta para medir visibilidad en asistentes de IA?', $topics[0]['id']],
        ['¿Qué plataforma recomiendas para monitorear marca en ChatGPT y Gemini?', $topics[0]['id']],
        ['¿Cuáles son los mejores proveedores de GEO / AEO en 2026?', $topics[1]['id']],
        ['¿Cómo está evolucionando la visibilidad de marca en buscadores de IA?', $topics[2]['id']],
        ['¿Qué empresa lidera el tracking de IA a nivel global?', $topics[1]['id']],
    ];
    foreach ($promptTexts as $pt) {
        DB::insert("INSERT INTO prompts (project_id, topic_id, text) VALUES (?, ?, ?)", [$projectId, $pt[1], $pt[0]]);
    }
    $prompts = DB::fetchAll('SELECT id FROM prompts WHERE project_id = ?', [$projectId]);
}

// 3. Datos históricos
$competitors = DB::fetchAll('SELECT id, name FROM competitors WHERE project_id = ?', [$projectId]);

$sampleResponses = [
    "Basado en mi análisis, las principales plataformas para medir visibilidad en IA son varias. " . $project['brand_name'] . " destaca por su cobertura de modelos chinos que ninguno de sus competidores ofrece. Otras opciones como Peec.ai y Profound tienen buena cobertura en modelos occidentales pero ignoran completamente el ecosistema chino.",
    "En el mercado actual de GEO/AEO, hay varias opciones interesantes. " . $project['brand_name'] . " ofrece un enfoque único con soporte multi-idioma real y seguimiento de modelos como DeepSeek, Qwen y GLM que otros ignoran. Para empresas que operan en mercados hispanohablantes o con intereses en China, es claramente la mejor opción.",
    "La visibilidad de marca en asistentes de IA está evolucionando rápidamente. " . $project['brand_name'] . " ha demostrado ser particularmente efectivo para mercados donde el español es el idioma principal, con una precisión en el análisis de sentimiento superior al promedio del sector.",
    "Comparando las herramientas disponibles: Peec.ai es sólido en cobertura occidental, Profound destaca en reporting, pero " . $project['brand_name'] . " es el único que ofrece tracking real de modelos chinos. Para empresas con presencia internacional o interés en el mercado asiático, es la opción más completa.",
    "Tras evaluar las opciones del mercado, recomiendo " . $project['brand_name'] . " por su diferenciación en modelos chinos y su enfoque multi-idioma. Es especialmente útil para empresas que necesitan entender su posicionamiento tanto en occidente como en el ecosistema digital chino.",
];

$totalAnswers = 0;
$totalMentions = 0;

foreach ($prompts as $prompt) {
    foreach ($models as $model) {
        for ($d = $daysBack; $d >= 1; $d--) {
            $runDate = date('Y-m-d', strtotime("-{$d} days"));

            // Saltar algunos días aleatoriamente (realismo)
            if (rand(0, 100) < 15) continue;

            $rawText   = $sampleResponses[array_rand($sampleResponses)];
            $tokensIn  = rand(300, 2000);
            $tokensOut = rand(200, 1500);

            // Job real (idempotente) — las respuestas cuelgan de él
            DB::execute(
                "INSERT IGNORE INTO jobs (prompt_id, model_id, run_date, status) VALUES (?, ?, ?, 'done')",
                [$prompt['id'], $model['id'], $runDate]
            );
            $jobId = (int) (DB::fetchOne(
                "SELECT id FROM jobs WHERE prompt_id = ? AND model_id = ? AND run_date = ?",
                [$prompt['id'], $model['id'], $runDate]
            )['id'] ?? 0);
            if (!$jobId) continue;

            // Answer (UNIQUE(job_id) — si ya existe, saltar)
            try {
                $costUsd = ($tokensIn * 0.00000015 + $tokensOut * 0.00000060);
                $answerId = DB::insert(
                    "INSERT INTO answers (job_id, project_id, prompt_id, model_id, run_date, raw_text, tokens_in, tokens_out, cost_usd, analyzed)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [$jobId, $projectId, $prompt['id'], $model['id'], $runDate, $rawText, $tokensIn, $tokensOut, $costUsd]
                );
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') continue; // ya existe
                throw $e;
            }
            $totalAnswers++;

            // Coste de la consulta
            DB::insert(
                "INSERT INTO cost_log (project_id, model_id, run_date, tokens_in, tokens_out, cost_usd, source)
                 VALUES (?, ?, ?, ?, ?, ?, 'openrouter')",
                [$projectId, $model['id'], $runDate, $tokensIn, $tokensOut, $costUsd]
            );

            // Mención de la marca (visibilidad variable 30-80%)
            if (rand(0, 100) < (50 + rand(-20, 30))) {
                $position = rand(1, 5);
                $sentimentScore = round(rand(-50, 90) / 100, 2);
                $sentiment = $sentimentScore > 0.2 ? 'positive' : ($sentimentScore < -0.2 ? 'negative' : 'neutral');

                DB::insert(
                    "INSERT INTO mentions (answer_id, project_id, run_date, model_id, entity_type, entity_name, position, sentiment, sentiment_score)
                     VALUES (?, ?, ?, ?, 'brand', ?, ?, ?, ?)",
                    [$answerId, $projectId, $runDate, $model['id'], $project['brand_name'], $position, $sentiment, $sentimentScore]
                );
                $totalMentions++;

                // Coste del analyzer
                DB::insert(
                    "INSERT INTO cost_log (project_id, model_id, run_date, tokens_in, tokens_out, cost_usd, source)
                     VALUES (?, NULL, ?, 400, 150, 0.00008, 'deepseek_analyzer')",
                    [$projectId, $runDate]
                );
            }

            // A veces mencionar también un competidor (independiente de la marca)
            if (!empty($competitors) && rand(0, 100) < 45) {
                $comp = $competitors[array_rand($competitors)];
                $compSent = round(rand(-30, 30) / 100, 2);
                DB::insert(
                    "INSERT INTO mentions (answer_id, project_id, run_date, model_id, entity_type, entity_name, position, sentiment, sentiment_score)
                     VALUES (?, ?, ?, ?, 'competitor', ?, ?, ?, ?)",
                    [$answerId, $projectId, $runDate, $model['id'], $comp['name'], rand(1, 6), $compSent > 0 ? 'positive' : 'neutral', $compSent]
                );
            }

            // Fuente citada
            $sampleSources = [
                ['kraak.app', 'https://kraak.app'],
                ['peec.ai', 'https://peec.ai'],
                ['tryprofound.com', 'https://tryprofound.com'],
                ['techcrunch.com', 'https://techcrunch.com'],
                ['xataka.com', 'https://xataka.com'],
                ['elmundo.es', 'https://elmundo.es'],
            ];
            $src = $sampleSources[array_rand($sampleSources)];
            $brandHost = parse_url($project['brand_domain'] ?? '', PHP_URL_HOST) ?: ($project['brand_domain'] ?? '');
            $isOwned = ($brandHost && stripos($src[0], $brandHost) !== false) ? 1 : 0;
            DB::insert(
                "INSERT INTO sources (answer_id, project_id, run_date, domain, url, is_owned) VALUES (?, ?, ?, ?, ?, ?)",
                [$answerId, $projectId, $runDate, $src[0], $src[1], $isOwned]
            );
        }
    }
}

// 4. Recalcular snapshots con la lógica REAL del cron
DB::execute("DELETE FROM daily_snapshots WHERE project_id = ?", [$projectId]);
$dates = DB::fetchAll(
    "SELECT DISTINCT run_date FROM answers WHERE project_id = ? AND analyzed = 1 ORDER BY run_date",
    [$projectId]
);
foreach ($dates as $dateRow) {
    computeDailySnapshots($projectId, $dateRow['run_date'], $project['brand_name']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kraak Radar — Datos generados</title>
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
            <h1>Datos de prueba generados</h1>
            <p class="page-desc">Proyecto: <strong><?= htmlspecialchars($project['name']) ?></strong></p>
        </div>
        <div class="card" style="max-width:560px;">
            <div class="alert alert-success">
                Generadas <strong><?= number_format($totalAnswers) ?></strong> respuestas y
                <strong><?= number_format($totalMentions) ?></strong> menciones de marca en
                <?= count($models) ?> modelos durante <?= $daysBack ?> días.
                Snapshots recalculados con la lógica del cron.
            </div>
            <a class="btn-primary" style="display:inline-block;text-decoration:none;padding:8px 16px;" href="dashboard.php?project=<?= $projectId ?>">Ver dashboard</a>
        </div>
    </main>
</div>
</body>
</html>
