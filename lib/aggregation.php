<?php
/**
 * Agregación de snapshots diarios.
 *
 * Compartida por cron/snapshot.php y public/seed.php para que ambos
 * calculen EXACTAMENTE igual. El dashboard solo lee de daily_snapshots.
 *
 * Definiciones canónicas:
 * - answers_total:      nº de respuestas analizadas ese día (COUNT DISTINCT answers)
 * - mentions_count:     nº total de menciones de la entidad (volumen)
 * - visibility_pct:     % de respuestas con AL MENOS una mención de la entidad
 *                       (COUNT DISTINCT answers con mención / answers_total)
 * - avg_position/sent:  media sobre las menciones, no sobre respuestas
 * - entity_name:        brand_name real para 'brand'; nombre para 'competitor'
 * - model_id NULL:      agregado global de todos los modelos
 */

/**
 * Recalcula los snapshots de un proyecto para una fecha.
 * Idempotente: per-model usa upsert; globales (model_id NULL) delete+insert
 * (MySQL no deduplica NULLs en claves únicas).
 *
 * @return int nº de filas escritas
 */
function computeDailySnapshots(int $projectId, string $date, string $brandName): int {
    $written = 0;

    $modelIds = DB::fetchAll(
        "SELECT DISTINCT model_id FROM answers
         WHERE project_id = ? AND run_date = ? AND analyzed = 1",
        [$projectId, $date]
    );

    // ── Agregados por modelo ──
    foreach ($modelIds as $row) {
        $written += computeScope($projectId, $date, $brandName, $row['model_id']);
    }

    // ── Agregado global (todos los modelos, model_id NULL) ──
    // Delete + insert: las claves únicas de MySQL no colapsan valores NULL,
    // así que INSERT IGNORE/ON DUPLICATE no deduplicaría estas filas.
    DB::execute(
        "DELETE FROM daily_snapshots WHERE project_id = ? AND run_date = ? AND model_id IS NULL",
        [$projectId, $date]
    );
    $written += computeScope($projectId, $date, $brandName, null);

    return $written;
}

/**
 * Calcula e inserta los snapshots de un scope (un modelo o global).
 *
 * @param int|null $modelId NULL para el agregado global
 * @return int nº de filas escritas
 */
function computeScope(int $projectId, string $date, string $brandName, ?int $modelId): int {
    $written = 0;
    $isGlobal = ($modelId === null);

    // Filtro de modelo reutilizable (model_id nunca viene de usuario, es interno)
    $modelWhereAnswers  = $isGlobal ? '' : 'AND a.model_id = ' . $modelId;
    $paramsAnswers      = [$projectId, $date];

    // Total de respuestas analizadas en el scope (denominador común)
    $totalAnswers = (int) (DB::fetchOne(
        "SELECT COUNT(DISTINCT a.id) AS c
         FROM answers a
         WHERE a.project_id = ? AND a.run_date = ? AND a.analyzed = 1 {$modelWhereAnswers}",
        $paramsAnswers
    )['c'] ?? 0);

    if ($totalAnswers === 0) {
        return 0;
    }

    // ── Marca ──
    $brand = DB::fetchOne(
        "SELECT COUNT(DISTINCT CASE WHEN m.id IS NOT NULL THEN a.id END) AS with_mention,
                COUNT(m.id) AS mentions_count,
                AVG(m.position) AS avg_pos,
                AVG(m.sentiment_score) AS avg_sent
         FROM answers a
         LEFT JOIN mentions m ON m.answer_id = a.id AND m.entity_type = 'brand'
         WHERE a.project_id = ? AND a.run_date = ? AND a.analyzed = 1 {$modelWhereAnswers}",
        $paramsAnswers
    );

    if ($brand) {
        $vis = round($brand['with_mention'] / $totalAnswers * 100, 2);
        $written += upsertSnapshot(
            $projectId, $date, $modelId, 'brand', $brandName,
            $totalAnswers, (int) $brand['mentions_count'], $vis,
            $brand['avg_pos'], $brand['avg_sent']
        );
    }

    // ── Competidores (una fila por entidad mencionada) ──
    $comps = DB::fetchAll(
        "SELECT m.entity_name,
                COUNT(DISTINCT m.answer_id) AS with_mention,
                COUNT(m.id) AS mentions_count,
                AVG(m.position) AS avg_pos,
                AVG(m.sentiment_score) AS avg_sent
         FROM mentions m
         JOIN answers a ON a.id = m.answer_id
         WHERE a.project_id = ? AND a.run_date = ? AND a.analyzed = 1
               AND m.entity_type = 'competitor' {$modelWhereAnswers}
         GROUP BY m.entity_name",
        $paramsAnswers
    );

    foreach ($comps as $c) {
        $vis = round($c['with_mention'] / $totalAnswers * 100, 2);
        $written += upsertSnapshot(
            $projectId, $date, $modelId, 'competitor', $c['entity_name'],
            $totalAnswers, (int) $c['mentions_count'], $vis,
            $c['avg_pos'], $c['avg_sent']
        );
    }

    return $written;
}

/**
 * Inserta/actualiza una fila de snapshot. Devuelve 1 si escribió, 0 si no.
 */
function upsertSnapshot(
    int $projectId, string $date, ?int $modelId, string $entityType, string $entityName,
    int $answersTotal, int $mentionsCount, float $visPct, $avgPos, $avgSent
): int {
    $avgPos  = $avgPos !== null ? round((float) $avgPos, 2) : null;
    $avgSent = $avgSent !== null ? round((float) $avgSent, 2) : null;

    if ($modelId === null) {
        // Global: el DELETE previo en computeDailySnapshots garantiza unicidad
        DB::insert(
            "INSERT INTO daily_snapshots
             (project_id, run_date, model_id, entity_type, entity_name,
              answers_total, mentions_count, visibility_pct, avg_position, sentiment_avg)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)",
            [$projectId, $date, $entityType, $entityName,
             $answersTotal, $mentionsCount, $visPct, $avgPos, $avgSent]
        );
        return 1;
    }

    DB::execute(
        "INSERT INTO daily_snapshots
         (project_id, run_date, model_id, entity_type, entity_name,
          answers_total, mentions_count, visibility_pct, avg_position, sentiment_avg)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            answers_total = VALUES(answers_total),
            mentions_count = VALUES(mentions_count),
            visibility_pct = VALUES(visibility_pct),
            avg_position = VALUES(avg_position),
            sentiment_avg = VALUES(sentiment_avg)",
        [$projectId, $date, $modelId, $entityType, $entityName,
         $answersTotal, $mentionsCount, $visPct, $avgPos, $avgSent]
    );
    return 1;
}
