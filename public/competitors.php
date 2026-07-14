<?php
/**
 * Gestión de Competidores
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
$user = requireLogin();

$projects = DB::fetchAll('SELECT * FROM projects WHERE user_id = ? ORDER BY name', [$user['id']]);
$projectId = (int) ($_GET['project'] ?? ($projects[0]['id'] ?? 0));
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project) {
    csrfVerify();
    if (isset($_POST['add'])) {
        $name = trim($_POST['name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $aliases = trim($_POST['aliases'] ?? '');
        if ($name) DB::insert('INSERT INTO competitors (project_id, name, domain, aliases) VALUES (?, ?, ?, ?)',
            [$projectId, $name, $domain ?: null, $aliases ? json_encode(explode(',', $aliases)) : null]);
    } elseif (isset($_POST['delete'])) {
        DB::execute('DELETE FROM competitors WHERE id = ? AND project_id = ?', [(int) $_POST['id'], $projectId]);
    }
    header('Location: competitors.php?project=' . $projectId);
    exit;
}

$competitors = $project ? DB::fetchAll('SELECT * FROM competitors WHERE project_id = ? ORDER BY name', [$projectId]) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Kraak Radar — Competidores</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<nav class="nav"><div class="nav-inner">
    <a href="dashboard.php" class="nav-brand">Kraak Radar</a>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a><a href="prompts.php">Prompts</a>
        <a href="competitors.php" class="active">Competidores</a><a href="sources.php">Fuentes</a>
        <a href="export.php">Exportar</a><a href="logout.php" class="btn-logout">Salir</a>
    </div>
</div></nav>
<main class="main">
    <div class="dash-header"><h2>Competidores</h2>
        <select onchange="location='?project='+this.value"><?php foreach ($projects as $p): ?><option value="<?=$p['id']?>" <?=$p['id']==$projectId?'selected':''?>><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?></select>
    </div>
    <?php if ($project): ?>
    <div class="card">
        <h3>Añadir Competidor</h3>
        <form method="post" class="form-inline">
            <input type="text" name="name" placeholder="Nombre de la marca" required>
            <input type="text" name="domain" placeholder="Dominio (opcional)">
            <input type="text" name="aliases" placeholder="Alias separados por coma (opcional)">
            <button type="submit" name="add">Añadir</button>
        </form>
    </div>
    <div class="card">
        <h3>Competidores detectados</h3>
        <?php if ($competitors): ?>
        <table class="data-table">
            <thead><tr><th>Marca</th><th>Dominio</th><th>Alias</th><th></th></tr></thead>
            <tbody><?php foreach ($competitors as $c): $als = json_decode($c['aliases'] ?? '[]', true); ?>
                <tr><td><?=htmlspecialchars($c['name'])?></td><td><?=htmlspecialchars($c['domain']??'-')?></td>
                <td><?= $als ? implode(', ', $als) : '-' ?></td>
                <td><form method="post" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="id" value="<?=$c['id']?>"><button type="submit" name="delete" class="btn-sm btn-danger">Eliminar</button></form></td>
            </tr><?php endforeach; ?></tbody>
        </table>
        <?php else: ?><p class="note">Añade competidores para trackear su visibilidad frente a la tuya.</p><?php endif; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
