<?php
/**
 * Export CSV
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
$user = requireLogin();

$projects = DB::fetchAll('SELECT * FROM projects WHERE user_id = ? ORDER BY name', [$user['id']]);
$projectId = (int) ($_GET['project'] ?? ($projects[0]['id'] ?? 0));
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project && isset($_POST['export'])) {
    $days = (int) ($_POST['days'] ?? 30);
    $type = $_POST['type'] ?? 'visibility';
    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kraak-radar-' . $type . '-' . date('Ymd') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8

    if ($type === 'visibility') {
        fputcsv($out, ['Fecha', 'Modelo', 'Entidad', 'Tipo', 'Visibilidad(%)', 'Posición', 'Sentimiento']);
        $rows = DB::fetchAll(
            "SELECT s.run_date, COALESCE(m.display_name,'todos') as model, s.entity_name, s.entity_type,
                    s.visibility_pct, s.avg_position, s.sentiment_avg
             FROM daily_snapshots s
             LEFT JOIN models m ON m.id = s.model_id
             WHERE s.project_id = ? AND s.run_date >= ?
             ORDER BY s.run_date, s.entity_name",
            [$projectId, $dateFrom]
        );
        foreach ($rows as $r) fputcsv($out, $r);
    } elseif ($type === 'sources') {
        fputcsv($out, ['Dominio', 'Citas', 'Propias', 'Última aparición']);
        $rows = DB::fetchAll(
            "SELECT domain, COUNT(*) as citations, SUM(is_owned) as owned, MAX(run_date) as last_seen
             FROM sources WHERE project_id = ? AND run_date >= ? GROUP BY domain ORDER BY citations DESC",
            [$projectId, $dateFrom]
        );
        foreach ($rows as $r) fputcsv($out, [$r['domain'], $r['citations'], $r['owned'], $r['last_seen']]);
    } else {
        fputcsv($out, ['Fecha', 'Modelo', 'Prompt', 'Marca', 'Posición', 'Sentimiento', 'Fuente']);
        $rows = DB::fetchAll(
            "SELECT a.run_date, m.display_name, p.text as prompt, me.entity_name, me.position, me.sentiment,
                    s.domain as source_domain
             FROM answers a
             JOIN models m ON m.id = a.model_id
             JOIN prompts p ON p.id = a.prompt_id
             LEFT JOIN mentions me ON me.answer_id = a.id
             LEFT JOIN sources s ON s.answer_id = a.id
             WHERE a.project_id = ? AND a.run_date >= ?
             ORDER BY a.run_date, m.display_name",
            [$projectId, $dateFrom]
        );
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Kraak Radar — Exportar</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<div class="app-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>Exportar datos</h1>
            <p class="page-desc">Descarga los datos de visibilidad en formato CSV para tus informes, presentaciones o analisis externos.</p>
        </div>
    <div class="card" style="max-width:500px;margin:40px auto;">
        <h2>Exportar datos</h2>
        <?php if ($project): ?>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Proyecto: <strong><?=htmlspecialchars($project['name'])?></strong></label>
            </div>
            <div class="form-group">
                <label for="type">Tipo de exportación</label>
                <select name="type" id="type">
                    <option value="visibility">Visibilidad (snapshots)</option>
                    <option value="sources">Fuentes citadas</option>
                    <option value="full">Datos completos</option>
                </select>
            </div>
            <div class="form-group">
                <label for="days">Período</label>
                <select name="days" id="days">
                    <option value="7">Últimos 7 días</option>
                    <option value="30" selected>Últimos 30 días</option>
                    <option value="90">Últimos 90 días</option>
                </select>
            </div>
            <button type="submit" name="export" class="btn-full">Descargar CSV</button>
        </form>
        <?php else: ?><p class="note">Selecciona un proyecto para exportar sus datos.</p><?php endif; ?>
    </div>
</main>
</div>
</body>
</html>
