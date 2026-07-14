<?php
/**
 * plan.php - Crea los jobs del día
 * 
 * Por cada proyecto activo, por cada prompt activo, por cada modelo activo:
 * crea un job si no existe ya para hoy (idempotente por UNIQUE KEY).
 * 
 * Ejecución: una vez al día (02:05)
 */

require_once __DIR__ . '/bootstrap.php';

$today = date('Y-m-d');
$count = 0;

$projects = DB::fetchAll(
    "SELECT id, user_id FROM projects WHERE is_active = 1"
);

foreach ($projects as $project) {
    // Prompts activos del proyecto
    $prompts = DB::fetchAll(
        "SELECT id FROM prompts WHERE project_id = ? AND is_active = 1",
        [$project['id']]
    );

    if (empty($prompts)) continue;

    // Modelos activos del proyecto
    $models = DB::fetchAll(
        "SELECT m.id FROM models m
         JOIN project_models pm ON pm.model_id = m.id
         WHERE pm.project_id = ? AND m.is_active = 1
         ORDER BY m.sort_order",
        [$project['id']]
    );

    if (empty($models)) continue;

    foreach ($prompts as $prompt) {
        foreach ($models as $model) {
            try {
                DB::insert(
                    "INSERT IGNORE INTO jobs (prompt_id, model_id, run_date, status, created_at)
                     VALUES (?, ?, ?, 'pending', NOW())",
                    [$prompt['id'], $model['id'], $today]
                );
                if (DB::get()->lastInsertId() > 0) $count++;
            } catch (Exception $e) {
                logMsg("ERROR plan: project={$project['id']} prompt={$prompt['id']} model={$model['id']} — " . $e->getMessage());
            }
        }
    }
}

logMsg("plan.php: {$count} jobs creados para {$today}");
