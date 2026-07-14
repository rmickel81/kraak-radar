<?php
/**
 * Panel de fuentes citadas
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
$user = requireLogin();

$projects = DB::fetchAll('SELECT * FROM projects WHERE user_id = ? ORDER BY name', [$user['id']]);
$projectId = (int) ($_GET['project'] ?? ($projects[0]['id'] ?? 0));
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]);
$days = (int) ($_GET['days'] ?? 30);

$sources = [];
$models = [];
if ($project) {
    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
    $sources = DB::fetchAll(
        "SELECT s.domain, COUNT(*) as citations, SUM(s.is_owned) as owned_citations,
                MAX(s.run_date) as last_seen
         FROM sources s
         WHERE s.project_id = ? AND s.run_date >= ?
         GROUP BY s.domain
         ORDER BY citations DESC
         LIMIT 50",
        [$projectId, $dateFrom]
    );
    $models = DB::fetchAll(
        "SELECT m.display_name, COUNT(*) as total, SUM(s.is_owned) as owned
         FROM sources s
         JOIN answers a ON a.id = s.answer_id
         JOIN models m ON m.id = a.model_id
         WHERE s.project_id = ? AND s.run_date >= ?
         GROUP BY m.display_name
         ORDER BY total DESC",
        [$projectId, $dateFrom]
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Kraak Radar — Fuentes</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<div class="app-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>Fuentes Citadas</h1>
            <p class="page-desc">Los dominios y URLs que los asistentes de IA citan como referencia al responder sobre tu sector. Saber qué fuentes utilizan te permite entender y mejorar tu posicionamiento.</p>
        </div>
        <div class="controls">
            <select onchange="location='?project=<?=$projectId?>&days='+this.value">
                <option value="7" <?=$days==7?'selected':''?>>7 días</option>
                <option value="30" <?=$days==30?'selected':''?>>30 días</option>
                <option value="90" <?=$days==90?'selected':''?>>90 días</option>
            </select>
            <select onchange="location='?project='+this.value+'&days=<?=$days?>'">
                <?php foreach ($projects as $p): ?><option value="<?=$p['id']?>" <?=$p['id']==$projectId?'selected':''?>><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php if ($project): ?>
    <div class="grid-2">
        <div class="card">
            <h3>Dominios más citados</h3>
            <?php if ($sources): ?>
            <table class="data-table">
                <thead><tr><th>Dominio</th><th>Citas</th><th>Propias</th><th>Última vez</th></tr></thead>
                <tbody><?php foreach ($sources as $s): ?>
                    <tr><td><?=htmlspecialchars($s['domain'])?></td><td><?=$s['citations']?></td>
                    <td><?=$s['owned_citations'] > 0 ? 'Sí x'.$s['owned_citations'] : '-'?></td>
                    <td><?=$s['last_seen']?></td>
                </tr><?php endforeach; ?></tbody>
            </table>
            <?php else: ?><p class="note">Sin datos en el período seleccionado.</p><?php endif; ?>
        </div>
        <div class="card">
            <h3>Fuentes por Modelo</h3>
            <?php if ($models): ?>
            <table class="data-table">
                <thead><tr><th>Modelo</th><th>Fuentes totales</th><th>Fuentes propias</th></tr></thead>
                <tbody><?php foreach ($models as $m): ?>
                    <tr><td><?=htmlspecialchars($m['display_name'])?></td><td><?=$m['total']?></td>
                    <td><?=$m['owned']?></td>
                </tr><?php endforeach; ?></tbody>
            </table>
            <?php else: ?><p class="note">Sin datos en el período seleccionado.</p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>
</div>
</body>
</html>
