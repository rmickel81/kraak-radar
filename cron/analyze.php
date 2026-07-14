<?php
/**
 * analyze.php - Analiza respuestas crudas y extrae menciones + fuentes
 * 
 * Usa DeepSeek v4-flash como analyzer (sin Claude / sin Haiku).
 * Coge batch de N answers sin analizar, las procesa y guarda resultados.
 * 
 * Ejecución: cada 5 minutos
 */

require_once __DIR__ . '/bootstrap.php';

$batchSize = CRON_BATCH_ANALYZE;

// Coger answers sin analizar
$answers = DB::fetchAll(
    "SELECT a.id, a.project_id, a.prompt_id, a.model_id, a.raw_text,
            a.run_date,
            p.brand_name, p.brand_domain, p.aliases
     FROM answers a
     JOIN projects p ON p.id = a.project_id
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

// Obtener competidores de cada proyecto
$competitors = [];
$allComp = DB::fetchAll("SELECT id, project_id, name, domain, aliases FROM competitors");
foreach ($allComp as $c) {
    $competitors[$c['project_id']][] = $c;
}

$done = 0;
$errors = 0;

foreach ($answers as $answer) {
    $projId   = $answer['project_id'];
    $aliases  = json_decode($answer['aliases'] ?? '[]', true) ?: [];
    $compList = $competitors[$projId] ?? [];
    $brandDomain = $answer['brand_domain'] ?? '';

    $result = analyzeAnswer(
        $answer['id'],
        $answer['raw_text'],
        $answer['brand_name'],
        $brandDomain,
        $aliases,
        $compList
    );

    if (isset($result['error'])) {
        logMsg("  answer #{$answer['id']}: ERROR {$result['error']}");
        DB::execute(
            "UPDATE answers SET analyzed = 2 WHERE id = ?",
            [$answer['id']]
        );
        $errors++;
        continue;
    }

    $data = $result['data'];

    // Guardar menciones
    foreach ($data['brands'] as $brand) {
        $isTarget = $brand['is_target'] ? 'brand' : 'competitor';
        $entityName = $brand['name'];

        // Buscar si es competidor registrado
        $entityType = 'brand';
        foreach ($compList as $c) {
            if (stripos($entityName, $c['name']) !== false) {
                $entityType = 'competitor';
                break;
            }
            $cAliases = json_decode($c['aliases'] ?? '[]', true) ?: [];
            foreach ($cAliases as $a) {
                if (stripos($entityName, $a) !== false) {
                    $entityType = 'competitor';
                    break 2;
                }
            }
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

    // Guardar fuentes
    foreach ($data['sources'] as $source) {
        $domain = $source['domain'] ?? '';
        $url    = $source['url'] ?? null;
        if (empty($domain)) continue;

        $isOwned = 0;
        if ($brandDomain && stripos($domain, parse_url($brandDomain, PHP_URL_HOST) ?: $brandDomain) !== false) {
            $isOwned = 1;
        }

        DB::insert(
            "INSERT INTO sources (answer_id, project_id, run_date, domain, url, is_owned)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $answer['id'],
                $projId,
                $answer['run_date'],
                mb_substr($domain, 0, 190),
                $url ? mb_substr($url, 0, 512) : null,
                $isOwned,
            ]
        );
    }

    DB::execute("UPDATE answers SET analyzed = 1 WHERE id = ?", [$answer['id']]);
    $done++;
}

logMsg("analyze.php: {$done} OK, {$errors} errores");
