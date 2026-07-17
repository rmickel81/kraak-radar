<?php
/**
 * snapshot.php - Agrega datos del día anterior a daily_snapshots
 * 
 * El dashboard SOLO lee de esta tabla. Nunca agrega al vuelo.
 * Usa lib/aggregation.php (compartida con seed.php).
 * 
 * Ejecución: una vez al día (03:00)
 */

require_once __DIR__ . '/bootstrap.php';

$date = date('Y-m-d', strtotime('-1 day'));
$total = 0;

$projects = DB::fetchAll(
    "SELECT id, brand_name FROM projects WHERE is_active = 1"
);

foreach ($projects as $project) {
    try {
        $n = computeDailySnapshots((int) $project['id'], $date, $project['brand_name']);
        $total += $n;
    } catch (Exception $e) {
        logMsg("ERROR snapshot: project={$project['id']} — " . $e->getMessage());
    }
}

logMsg("snapshot.php: {$total} snapshots guardados para {$date}");
