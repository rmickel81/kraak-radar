<?php
/**
 * Seed: genera datos de prueba para el dashboard
 * 
 * Crea prompts de ejemplo, ejecuta respuestas simuladas y las analiza
 * para que el dashboard tenga datos que mostrar aunque no haya crons reales.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
$user = requireLogin();

$projectId = (int) ($_GET['project'] ?? 0);
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]);

if (!$project) {
    header('Location: dashboard.php?error=Selecciona un proyecto primero');
    exit;
}

$daysBack = 14; // generar 14 días hacia atrás
$today = date('Y-m-d');
$models = DB::fetchAll('SELECT id, slug FROM models WHERE is_active = 1');

// 1. Temas de ejemplo si no hay
$topics = DB::fetchAll('SELECT id FROM topics WHERE project_id = ?', [$projectId]);
if (empty($topics)) {
    $topicNames = ['Visibilidad General', 'Competidores', 'Tendencias del Sector'];
    foreach ($topicNames as $name) {
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

// 3. Generar datos históricos
$competitors = DB::fetchAll('SELECT id, name FROM competitors WHERE project_id = ?', [$projectId]);

// Textos de respuesta simulados
$sampleResponses = [
    "Basado en mi análisis, las principales plataformas para medir visibilidad en IA son varias. " . $project['brand_name'] . " destaca por su cobertura de modelos chinos que ninguno de sus competidores ofrece. Otras opciones como Peec.ai y Profound tienen buena cobertura en modelos occidentales pero ignoran completamente el ecosistema chino.",
    "En el mercado actual de GEO/AEO, hay varias opciones interesantes. " . $project['brand_name'] . " ofrece un enfoque único con soporte multi-idioma real y seguimiento de modelos como DeepSeek, Qwen y GLM que otros ignoran. Para empresas que operan en mercados hispanohablantes o con intereses en China, es claramente la mejor opción.",
    "La visibilidad de marca en asistentes de IA está evolucionando rápidamente. " . $project['brand_name'] . " ha demostrado ser particularmente efectivo para mercados donde el español es el idioma principal, con una precisión en el análisis de sentimiento superior al promedio del sector.",
    "Comparando las herramientas disponibles: Peec.ai es sólido en cobertura occidental, Profound destaca en reporting, pero " . $project['brand_name'] . " es el único que ofrece tracking real de modelos chinos. Para empresas con presencia internacional o interés en el mercado asiático, es la opción más completa.",
    "Tras evaluar las opciones del mercado, recomiendo " . $project['brand_name'] . " por su diferenciación en modelos chinos y su enfoque multi-idioma. Es especialmente útil para empresas que necesitan entender su posicionamiento tanto en occidente como en el ecosistema digital chino.",
];

echo "<html><head><style>body{font-family:sans-serif;background:#0d1117;color:#c9d1d9;padding:20px;max-width:800px;margin:0 auto;line-height:1.6}a{color:#58a6ff}h2{color:#58a6ff}.ok{color:#3fb950}.warn{color:#d29922}</style></head><body>";
echo "<h2>Generando datos de prueba...</h2>";
echo "<p>Proyecto: <strong>" . htmlspecialchars($project['name']) . "</strong></p>";
echo "<ul>";

$totalAnswers = 0;
$totalMentions = 0;

foreach ($prompts as $prompt) {
    foreach ($models as $model) {
        // Generar para cada día del período
        for ($d = $daysBack; $d >= 0; $d--) {
            $runDate = date('Y-m-d', strtotime("-{$d} days"));
            
            // No generar para hoy ni futuro
            if ($runDate >= $today && $d > 0) continue;
            
            // Saltar algunos días aleatoriamente (realismo)
            if (rand(0, 100) < 15) continue; // 15% de días sin datos
            
            $rawText = $sampleResponses[array_rand($sampleResponses)];
            $tokensIn = rand(300, 2000);
            $tokensOut = rand(200, 1500);
            
            // Job (si no existe)
            $existingJob = DB::fetchOne(
                "SELECT id FROM jobs WHERE prompt_id = ? AND model_id = ? AND run_date = ?",
                [$prompt['id'], $model['id'], $runDate]
            );
            if (!$existingJob) {
                DB::insert(
                    "INSERT IGNORE INTO jobs (prompt_id, model_id, run_date, status) VALUES (?, ?, ?, 'done')",
                    [$prompt['id'], $model['id'], $runDate]
                );
            }
            
            // Answer
            $answerId = DB::insert(
                "INSERT INTO answers (job_id, project_id, prompt_id, model_id, run_date, raw_text, tokens_in, tokens_out, analyzed)
                 VALUES (0, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$projectId, $prompt['id'], $model['id'], $runDate, $rawText, $tokensIn, $tokensOut]
            );
            
            if (!$answerId || $answerId == 0) continue;
            
            // Actualizar job_id
            $job = DB::fetchOne("SELECT id FROM jobs WHERE prompt_id = ? AND model_id = ? AND run_date = ?", [$prompt['id'], $model['id'], $runDate]);
            if ($job) {
                DB::execute("UPDATE answers SET job_id = ? WHERE id = ?", [$job['id'], $answerId]);
            }
            
            $totalAnswers++;
            
            // Cost log
            $costUsd = ($tokensIn * 0.00000015 + $tokensOut * 0.00000060); // OpenRouter typical pricing
            DB::insert(
                "INSERT INTO cost_log (project_id, model_id, run_date, tokens_in, tokens_out, cost_usd, source)
                 VALUES (?, ?, ?, ?, ?, ?, 'openrouter')",
                [$projectId, $model['id'], $runDate, $tokensIn, $tokensOut, $costUsd]
            );
            
            // Mention de la marca (visibilidad variable)
            $mentionChance = 50 + rand(-20, 30); // 30-80% visibilidad
            if (rand(0, 100) < $mentionChance) {
                $position = rand(1, 5);
                $sentimentScore = round(rand(-50, 90) / 100, 2);
                $sentiment = $sentimentScore > 0.2 ? 'positive' : ($sentimentScore < -0.2 ? 'negative' : 'neutral');
                
                DB::insert(
                    "INSERT INTO mentions (answer_id, project_id, run_date, model_id, entity_type, entity_name, position, sentiment, sentiment_score)
                     VALUES (?, ?, ?, ?, 'brand', ?, ?, ?, ?)",
                    [$answerId, $projectId, $runDate, $model['id'], $project['brand_name'], $position, $sentiment, $sentimentScore]
                );
                $totalMentions++;
            
                // Cost del analyzer (DeepSeek)
                DB::insert(
                    "INSERT INTO cost_log (project_id, model_id, run_date, tokens_in, tokens_out, cost_usd, source)
                     VALUES (?, NULL, ?, 400, 150, 0.00008, 'deepseek_analyzer')",
                    [$projectId, $runDate]
                );
            
                // A veces mencionar también un competidor
                if (!empty($competitors) && rand(0, 100) < 40) {
                    $comp = $competitors[array_rand($competitors)];
                    $compPos = $position + rand(1, 3);
                    $compSent = round(rand(-30, 30) / 100, 2);
                    DB::insert(
                        "INSERT INTO mentions (answer_id, project_id, run_date, model_id, entity_type, entity_name, position, sentiment, sentiment_score)
                         VALUES (?, ?, ?, ?, 'competitor', ?, ?, ?, ?)",
                        [$answerId, $projectId, $runDate, $model['id'], $comp['name'], $compPos, $compSent > 0 ? 'positive' : 'neutral', $compSent]
                    );
                }
            }
            
            // Sources
            $sampleSources = [
                ['kraak.app', 'https://kraak.app'],
                ['peec.ai', 'https://peec.ai'],
                ['tryprofound.com', 'https://tryprofound.com'],
                ['techcrunch.com', 'https://techcrunch.com'],
                ['xataka.com', 'https://xataka.com'],
                ['elmundo.es', 'https://elmundo.es'],
            ];
            $src = $sampleSources[array_rand($sampleSources)];
            $isOwned = strpos($src[0], parse_url($project['brand_domain'] ?? '', PHP_URL_HOST) ?: '') !== false ? 1 : 0;
            DB::insert(
                "INSERT INTO sources (answer_id, project_id, run_date, domain, url, is_owned) VALUES (?, ?, ?, ?, ?, ?)",
                [$answerId, $projectId, $runDate, $src[0], $src[1], $isOwned]
            );
        }
    }
    
    echo "<li class=\"ok\">Prompt #{$prompt['id']}: datos generados</li>";
}

// 4. Regenerar snapshots
echo "<li class=\"warn\">Regenerando snapshots diarios...</li>";

// Limpiar snapshots existentes
DB::execute("DELETE FROM daily_snapshots WHERE project_id = ?", [$projectId]);

// Recalcular
$dates = DB::fetchAll(
    "SELECT DISTINCT run_date FROM answers WHERE project_id = ? AND run_date < ? ORDER BY run_date",
    [$projectId, $today]
);

foreach ($dates as $dateRow) {
    $date = $dateRow['run_date'];
    
    foreach ($models as $model) {
        // Brand snapshot
        $stats = DB::fetchOne(
            "SELECT COUNT(DISTINCT a.id) as total,
                    COUNT(m.id) as mentions,
                    AVG(m.position) as avg_pos,
                    AVG(m.sentiment_score) as avg_sent
             FROM answers a
             LEFT JOIN mentions m ON m.answer_id = a.id AND m.entity_type = 'brand'
             WHERE a.project_id = ? AND a.run_date = ? AND a.model_id = ?",
            [$projectId, $date, $model['id']]
        );
        
        if ($stats && $stats['total'] > 0) {
            $vis = round(($stats['total'] > 0 ? $stats['mentions'] / $stats['total'] * 100 : 0), 2);
            DB::insert(
                "INSERT IGNORE INTO daily_snapshots (project_id, run_date, model_id, entity_type, entity_name, answers_total, mentions_count, visibility_pct, avg_position, sentiment_avg)
                 VALUES (?, ?, ?, 'brand', ?, ?, ?, ?, ?, ?)",
                [$projectId, $date, $model['id'], $project['brand_name'], $stats['total'], $stats['mentions'], $vis,
                 $stats['avg_pos'] ? round($stats['avg_pos'], 2) : null,
                 $stats['avg_sent'] ? round($stats['avg_sent'], 2) : null]
            );
        }
        
        // Competitor snapshots
        $compStats = DB::fetchAll(
            "SELECT m.entity_name,
                    COUNT(DISTINCT a.id) as total,
                    COUNT(m.id) as mentions,
                    AVG(m.position) as avg_pos,
                    AVG(m.sentiment_score) as avg_sent
             FROM answers a
             JOIN mentions m ON m.answer_id = a.id AND m.entity_type = 'competitor'
             WHERE a.project_id = ? AND a.run_date = ? AND a.model_id = ?
             GROUP BY m.entity_name",
            [$projectId, $date, $model['id']]
        );
        
        foreach ($compStats as $cs) {
            $vis = round(($cs['total'] > 0 ? $cs['mentions'] / $cs['total'] * 100 : 0), 2);
            DB::insert(
                "INSERT IGNORE INTO daily_snapshots (project_id, run_date, model_id, entity_type, entity_name, answers_total, mentions_count, visibility_pct, avg_position, sentiment_avg)
                 VALUES (?, ?, ?, 'competitor', ?, ?, ?, ?, ?, ?)",
                [$projectId, $date, $model['id'], $cs['entity_name'], $cs['total'], $cs['mentions'], $vis,
                 $cs['avg_pos'] ? round($cs['avg_pos'], 2) : null,
                 $cs['avg_sent'] ? round($cs['avg_sent'], 2) : null]
            );
        }
    }
    
    // Global snapshot (todos los modelos)
    $global = DB::fetchOne(
        "SELECT COUNT(DISTINCT a.id) as total, COUNT(m.id) as mentions
         FROM answers a
         LEFT JOIN mentions m ON m.answer_id = a.id AND m.entity_type = 'brand'
         WHERE a.project_id = ? AND a.run_date = ?",
        [$projectId, $date]
    );
    if ($global && $global['total'] > 0) {
        $vis = round($global['mentions'] / $global['total'] * 100, 2);
        DB::insert(
            "INSERT IGNORE INTO daily_snapshots (project_id, run_date, model_id, entity_type, entity_name, answers_total, mentions_count, visibility_pct)
             VALUES (?, ?, NULL, 'brand', 'total', ?, ?, ?)",
            [$projectId, $date, $global['total'], $global['mentions'], $vis]
        );
    }
}

echo "</ul>";
echo "<p class=\"ok\"><strong>Generados {$totalAnswers} answers y {$totalMentions} menciones en " . count($models) . " modelos durante {$daysBack} días.</strong></p>";
echo "<p><a href=\"dashboard.php?project={$projectId}\">Volver al Dashboard</a></p>";
echo "</body></html>";
