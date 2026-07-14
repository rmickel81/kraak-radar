<?php
/**
 * Guarda modelos activos por proyecto
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
$user = requireLogin();

$projectId = (int) ($_POST['project_id'] ?? 0);
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]);

if ($project && isset($_POST['save_models'])) {
    $models = array_map('intval', $_POST['models'] ?? []);
    DB::execute('DELETE FROM project_models WHERE project_id = ?', [$projectId]);
    foreach ($models as $mId) {
        DB::insert('INSERT INTO project_models (project_id, model_id) VALUES (?, ?)', [$projectId, $mId]);
    }
}

header('Location: prompts.php?project=' . $projectId);
exit;
