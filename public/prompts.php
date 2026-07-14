<?php
/**
 * Gestión de Prompts
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
$user = requireLogin();

$projects = DB::fetchAll('SELECT * FROM projects WHERE user_id = ? ORDER BY name', [$user['id']]);
$projectId = (int) ($_GET['project'] ?? ($projects[0]['id'] ?? 0));
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]);

// CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project) {
    if (isset($_POST['add'])) {
        $text = trim($_POST['text'] ?? '');
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        if ($text) {
            DB::insert('INSERT INTO prompts (project_id, topic_id, text) VALUES (?, ?, ?)',
                [$projectId, $topicId ?: null, $text]);
        }
    } elseif (isset($_POST['toggle'])) {
        $id = (int) $_POST['id'];
        $p = DB::fetchOne('SELECT is_active FROM prompts WHERE id = ? AND project_id = ?', [$id, $projectId]);
        if ($p) DB::execute('UPDATE prompts SET is_active = ? WHERE id = ?', [$p['is_active'] ? 0 : 1, $id]);
    } elseif (isset($_POST['delete'])) {
        DB::execute('DELETE FROM prompts WHERE id = ? AND project_id = ?', [(int) $_POST['id'], $projectId]);
    } elseif (isset($_POST['topic_add'])) {
        $name = trim($_POST['topic_name'] ?? '');
        if ($name) DB::insert('INSERT INTO topics (project_id, name) VALUES (?, ?)', [$projectId, $name]);
    }
    header('Location: prompts.php?project=' . $projectId);
    exit;
}

$prompts = $project ? DB::fetchAll(
    'SELECT p.*, t.name as topic_name FROM prompts p LEFT JOIN topics t ON t.id = p.topic_id WHERE p.project_id = ? ORDER BY p.created_at DESC', [$projectId]) : [];
$topics = $project ? DB::fetchAll('SELECT * FROM topics WHERE project_id = ? ORDER BY name', [$projectId]) : [];
$models = DB::fetchAll('SELECT * FROM models WHERE is_active = 1 ORDER BY sort_order');
$projectModels = $project ? DB::fetchAll('SELECT model_id FROM project_models WHERE project_id = ?', [$projectId]) : [];
$pmIds = array_column($projectModels, 'model_id');
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Kraak Radar — Prompts</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<nav class="nav"><div class="nav-inner">
    <a href="dashboard.php" class="nav-brand">Kraak Radar</a>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="prompts.php" class="active">Prompts</a>
        <a href="competitors.php">Competidores</a>
        <a href="sources.php">Fuentes</a>
        <a href="export.php">Exportar</a>
        <a href="logout.php" class="btn-logout">Salir</a>
    </div>
</div></nav>
<main class="main">
    <div class="dash-header">
        <h2>Prompts</h2>
        <select onchange="location='?project='+this.value">
            <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id']==$projectId?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($project): ?>
    <div class="grid-2">
        <div class="card">
            <h3>Añadir Prompt</h3>
            <form method="post" class="form-inline">
                <select name="topic_id"><option value="">Sin tema</option><?php foreach ($topics as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select>
                <input type="text" name="text" placeholder='Ej: "¿Cuál es el mejor CRM para pymes?"' required>
                <button type="submit" name="add">Añadir</button>
            </form>
        </div>
        <div class="card">
            <h3>Temas</h3>
            <form method="post" class="form-inline">
                <input type="text" name="topic_name" placeholder="Nombre del tema">
                <button type="submit" name="topic_add">Crear</button>
            </form>
            <?php if ($topics): ?><ul class="list"><?php foreach ($topics as $t): ?><li><?=htmlspecialchars($t['name'])?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3>Modelos Activos</h3>
        <form method="post" action="save_models.php" class="form-inline">
            <input type="hidden" name="project_id" value="<?=$projectId?>">
            <div class="model-grid">
            <?php foreach ($models as $m): ?>
                <label class="model-check">
                    <input type="checkbox" name="models[]" value="<?=$m['id']?>" <?=in_array($m['id'], $pmIds)?'checked':''?>>
                    <span class="model-name"><?=htmlspecialchars($m['display_name'])?></span>
                    <span class="model-family"><?=$m['family']?></span>
                </label>
            <?php endforeach; ?>
            </div>
            <button type="submit" name="save_models">Guardar modelos</button>
        </form>
    </div>

    <div class="card">
        <h3>Prompts (<?= count($prompts) ?>)</h3>
        <?php if ($prompts): ?>
        <table class="data-table">
            <thead><tr><th>Tema</th><th>Prompt</th><th>Estado</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($prompts as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['topic_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['text']) ?></td>
                    <td><?= $p['is_active'] ? 'Activo' : 'Inactivo' ?></td>
                    <td class="actions">
                        <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=$p['id']?>"><button type="submit" name="toggle" class="btn-sm"><?=$p['is_active']?'Pausar':'Activar'?></button></form>
                        <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="id" value="<?=$p['id']?>"><button type="submit" name="delete" class="btn-sm btn-danger">Eliminar</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="note">No hay prompts. Añade preguntas conversacionales que los usuarios harían a asistentes de IA sobre tu sector.</p><?php endif; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
