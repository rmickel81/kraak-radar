<?php
/**
 * snapshot.php - Agrega datos del día anterior a daily_snapshots
 * 
 * El dashboard SOLO lee de esta tabla. Nunca agrega al vuelo.
 * 
 * Ejecución: una vez al día (03:00)
 */

require_once __DIR__ . '/bootstrap.php';

$date = date('Y-m-d', strtotime('-1 day'));
$count = 0;

$projects = DB::fetchAll("SELECT id FROM projects WHERE is_active = 1");

foreach ($projects as $project) {
    $projId = $project['id'];

    // Agregado por modelo
    $models = DB::fetchAll(
        "SELECT DISTINCT model_id FROM answers WHERE project_id = ? AND run_date = ? AND analyzed = 1",
        [$projId, $date]
    );

    foreach ($models as $m) {
        $modelId = $m['model_id'];

        // Brand
        $brandData = DB::fetchOne(
            "SELECT
                COUNT(*) as answers_total,
                (SELECT COUNT(*) FROM mentions m2
                 WHERE m2.answer_id IN (SELECT id FROM answers WHERE project_id = ? AND run_date = ? AND model_id = ?)
                 AND m2.entity_type = 'brand') as mentions_count,
                AVG(m3.position) as avg_pos,
                AVG(m3.sentiment_score) as avg_sent
             FROM answers a
             LEFT JOIN mentions m3 ON m3.answer_id = a.id AND m3.entity_type = 'brand'
             WHERE a.project_id = ? AND a.run_date = ? AND a.model_id = ? AND a.analyzed = 1
             LIMIT 1",
            [$projId, $date, $modelId, $projId, $date, $modelId]
        );

        if ($brandData && $brandData['answers_total'] > 0) {
            $visPct = $brandData['answers_total'] > 0
                ? round($brandData['mentions_count'] / $brandData['answers_total'] * 100, 2)
                : 0;

            DB::insert(
                "INSERT IGNORE INTO daily_snapshots
                 (project_id, run_date, model_id, entity_type, entity_name,
                  answers_total, mentions_count, visibility_pct, avg_position, sentiment_avg)
                 VALUES (?, ?, ?, 'brand', ?, ?, ?, ?, ?, ?)",
                [
                    $projId, $date, $modelId,
                    'mi_marca', // será reemplazado por el brand_name real
                    $brandData['answers_total'],
                    $brandData['mentions_count'],
                    $visPct,
                    $brandData['avg_pos'] ? round($brandData['avg_pos'], 2) : null,
                    $brandData['avg_sent'] ? round($brandData['avg_sent'], 2) : null,
                ]
            );
            $count++;
        }

        // Competidores
        $comps = DB::fetchAll(
            "SELECT DISTINCT entity_name
             FROM mentions m
             JOIN answers a ON a.id = m.answer_id
             WHERE a.project_id = ? AND a.run_date = ? AND a.model_id = ? AND m.entity_type = 'competitor'",
            [$projId, $date, $modelId]
        );

        foreach ($comps as $comp) {
            $compData = DB::fetchOne(
                "SELECT
                    COUNT(*) as answers_total,
                    COUNT(m.id) as mentions_count,
                    AVG(m.position) as avg_pos,
                    AVG(m.sentiment_score) as avg_sent
                 FROM answers a
                 LEFT JOIN mentions m ON m.answer_id = a.id AND m.entity_name = ? AND m.entity_type = 'competitor'
                 WHERE a.project_id = ? AND a.run_date = ? AND a.model_id = ? AND a.analyzed = 1",
                [$comp['entity_name'], $projId, $date, $modelId]
            );

            if ($compData && $compData['answers_total'] > 0) {
                $visPct = $compData['answers_total'] > 0
                    ? round($compData['mentions_count'] / $compData['answers_total'] * 100, 2)
                    : 0;

                DB::insert(
                    "INSERT IGNORE INTO daily_snapshots
                     (project_id, run_date, model_id, entity_type, entity_name,
                      answers_total, mentions_count, visibility_pct, avg_position, sentiment_avg)
                     VALUES (?, ?, ?, 'competitor', ?, ?, ?, ?, ?, ?)",
                    [
                        $projId, $date, $modelId,
                        $comp['entity_name'],
                        $compData['answers_total'],
                        $compData['mentions_count'],
                        $visPct,
                        $compData['avg_pos'] ? round($compData['avg_pos'], 2) : null,
                        $compData['avg_sent'] ? round($compData['avg_sent'], 2) : null,
                    ]
                );
                $count++;
            }
        }
    }

    // Agregado global (todos los modelos)
    $globalBrand = DB::fetchOne(
        "SELECT
            COUNT(*) as answers_total,
            (SELECT COUNT(*) FROM mentions m2
             WHERE m2.answer_id IN (SELECT id FROM answers WHERE project_id = ? AND run_date = ?)
             AND m2.entity_type = 'brand') as mentions_count
         FROM answers
         WHERE project_id = ? AND run_date = ? AND analyzed = 1
         LIMIT 1",
        [$projId, $date, $projId, $date]
    );

    if ($globalBrand && $globalBrand['answers_total'] > 0) {
        $visPct = round($globalBrand['mentions_count'] / $globalBrand['answers_total'] * 100, 2);
        DB::insert(
            "INSERT IGNORE INTO daily_snapshots
             (project_id, run_date, model_id, entity_type, entity_name,
              answers_total, mentions_count, visibility_pct, avg_position, sentiment_avg)
             VALUES (?, ?, NULL, 'brand', 'total', ?, ?, ?, NULL, NULL)",
            [$projId, $date, $globalBrand['answers_total'], $globalBrand['mentions_count'], $visPct]
        );
        $count++;
    }
}

logMsg("snapshot.php: {$count} snapshots guardados para {$date}");
