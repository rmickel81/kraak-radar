<?php
/**
 * Crear / Editar proyecto
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $brandName = trim($_POST['brand_name'] ?? '');
    $brandDomain = trim($_POST['brand_domain'] ?? '');
    $lang = $_POST['lang'] ?? 'es';
    $country = $_POST['country'] ?? 'ES';

    if ($name && $brandName) {
        $projId = DB::insert(
            'INSERT INTO projects (user_id, name, brand_name, brand_domain, lang, country) VALUES (?, ?, ?, ?, ?, ?)',
            [$user['id'], $name, $brandName, $brandDomain ?: null, $lang, $country]
        );
        // Asignar modelos por defecto (todos)
        $models = DB::fetchAll('SELECT id FROM models WHERE is_active = 1');
        foreach ($models as $m) {
            DB::execute('INSERT INTO project_models (project_id, model_id) VALUES (?, ?)', [$projId, $m['id']]);
        }
        header('Location: prompts.php?project=' . $projId);
        exit;
    }
}

header('Location: dashboard.php');
exit;
