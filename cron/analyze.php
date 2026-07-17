<?php
/**
 * analyze.php - Analiza respuestas crudas y extrae menciones + fuentes
 * 
 * Usa DeepSeek v4-flash como analyzer (sin Claude / sin Haiku).
 * - Reintentos con analyze_attempts (error permanente solo tras MAX_RETRIES)
 * - BYOK: usa la DeepSeek key del usuario; fallback a global
 * - Registra coste del analyzer en cost_log (source 'deepseek_analyzer')
 * 
 * Ejecución: cada 5 minutos
 */

require_once __DIR__ . '/bootstrap.php';

$batchSize = CRON_BATCH_ANALYZE;

// Coger answers pendientes de analizar (analyzed = 0; 2 = error permanente)
$answers = DB::fetchAll(
    "SELECT a.id, a.project_id, a.prompt_id, a.model_id, a.raw_text,
            a.run_date, a.analyze_attempts,
            p.brand_name, p.brand_domain, p.aliases,
            u.deepseek_key
     FROM answers a
     JOIN projects p ON p.id = a.project_id
     JOIN users u ON u.id = p.user_id
     WHERE a.analyzed = 0
     ORDER BY a.id ASC
     LIMIT ?",
    [$batchSize]
);

if (empty($answers)) {
    logMsg("analyze.php: no hay respuestas pendientes");
    exit;
}

logMsg("analyze.php: analizando " . count($answers) . " respuestas");

// Competidores agrupados por proyecto
$competitors = [];
$allComp = DB::fetchAll("SELECT id, project_id, name, domain, aliases FROM competitors");
foreach ($allComp as $c) {
    $competitors[$c['project_id']][] = $c;
}

$done = 0;
$errors = 0;

foreach ($answers as $answer) {
    $projId      = (int) $answer['project_id'];
    $aliases     = json_decode($answer['aliases'] ?? '[]', true) ?: [];
    $compList    = $competitors[$projId] ?? [];
    $brandDomain = $answer['brand_domain'] ?? '';

    $result = analyzeAnswer(
        $answer['raw_text'],
        $answer['brand_name'],
        $brandDomain,
        $aliases,
        $compList,
        $answer['deepseek_key'] ?: null
    );

    if (isset($result['error'])) {
        $attempts = (int) $answer['analyze_attempts'] + 1;
        $shortErr = mb_substr($result['error'], 0, 255);
        $permanent = $attempts >= MAX_RETRIES;

        DB::execute(
            "UPDATE answers SET analyzed = ?, analyze_attempts = ?, analyze_error = ? WHERE id = ?",
            [$permanent ? 2 : 0, $attempts, $shortErr, $answer['id']]
        );
        logMsg("  answer #{$answer['id']}: ERROR" . ($permanent ? ' (permanente)' : " (retry {$attempts})") . " — {$shortErr}");
        $errors++;
        continue;
    }

    $data = $result['data'];

    // Menciones
    foreach ($data['brands'] as $brand) {
        $entityName = (string) ($brand['name'] ?? '');
        if ($entityName === '') continue;

        // Clasificar: ¿marca objetivo o competidor?
        // 1) match contra brand_name/aliases del proyecto → brand
        // 2) match contra competidores registrados → competitor
        // 3) fallback al flag is_target del analyzer
        $entityType = null;
        $brandTerms = array_merge([$answer['brand_name']], $aliases);
        foreach ($brandTerms as $t) {
            if ($t !== '' && mb_stripos($entityName, $t) !== false) {
                $entityType = 'brand';
                break;
            }
        }
        if ($entityType === null) {
            foreach ($compList as $c) {
                if (mb_stripos($entityName, $c['name']) !== false) {
                    $entityType = 'competitor';
                    break;
                }
                $cAliases = json_decode($c['aliases'] ?? '[]', true) ?: [];
                foreach ($cAliases as $a) {
                    if ($a !== '' && mb_stripos($entityName, $a) !== false) {
                        $entityType = 'competitor';
                        break 2;
                    }
                }
            }
        }
        if ($entityType === null) {
            $entityType = !empty($brand['is_target']) ? 'brand' : 'competitor';
        }

        DB::insert(
            "INSERT INTO mentions (answer_id, project_id, run_date, model_id, entity_type, entity_name, position, sentiment, sentiment_score)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $answer['id'],
                $projId,
                $answer['run_date'],
                $answer['model_id'],
                $entityType,
                mb_substr($entityName, 0, 120),
                $brand['position'] ?? null,
                $brand['sentiment'] ?? 'neutral',
                $brand['sentiment_score'] ?? null,
            ]
        );
    }

    // Fuentes
    $brandHost = $brandDomain ? (parse_url($brandDomain, PHP_URL_HOST) ?: $brandDomain) : '';
    foreach ($data['sources'] as $source) {
        $domain = trim((string) ($source['domain'] ?? ''));
        $url    = $source['url'] ?? null;
        if ($domain === '') continue;

        $isOwned = ($brandHost && mb_stripos($domain, $brandHost) !== false) ? 1 : 0;

        DB::insert(
            "INSERT INTO sources (answer_id, project_id, run_date, domain, url, is_owned)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $answer['id'],
                $projId,
                $answer['run_date'],
                mb_substr($domain, 0, 190),
                $url ? mb_substr((string) $url, 0, 512) : null,
                $isOwned,
            ]
        );
    }

    // Coste del analyzer (DeepSeek directo)
    $costUsd = ($result['tokens_in'] / 1_000_000) * (float) DEEPSEEK_PRICE_IN
             + ($result['tokens_out'] / 1_000_000) * (float) DEEPSEEK_PRICE_OUT;
    DB::insert(
        "INSERT INTO cost_log (project_id, model_id, run_date, tokens_in, tokens_out, cost_usd, source)
         VALUES (?, NULL, ?, ?, ?, ?, 'deepseek_analyzer')",
        [$projId, $answer['run_date'], $result['tokens_in'], $result['tokens_out'], $costUsd]
    );

    DB::execute(
        "UPDATE answers SET analyzed = 1, analyze_error = NULL WHERE id = ?",
        [$answer['id']]
    );
    $done++;
}

logMsg("analyze.php: {$done} OK, {$errors} errores");
