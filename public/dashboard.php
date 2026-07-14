<?php
/**
 * Dashboard principal
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
$user = requireLogin();

// Proyectos del usuario
$projects = DB::fetchAll('SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC', [$user['id']]);

$projectId = (int) ($_GET['project'] ?? ($projects[0]['id'] ?? 0));
$days = (int) ($_GET['days'] ?? 30);

// Datos del proyecto seleccionado
$project = DB::fetchOne('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$projectId, $user['id']]) ?: null;

$snapshots = [];
$brandData = [];
$compData  = [];

if ($project) {
    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

    // Snapshot global (todos los modelos)
    $snapshots = DB::fetchAll(
        "SELECT run_date, visibility_pct, answers_total, mentions_count
         FROM daily_snapshots
         WHERE project_id = ? AND model_id IS NULL AND entity_name = 'total'
           AND run_date >= ?
         ORDER BY run_date ASC",
        [$projectId, $dateFrom]
    );

    // Últimos datos por modelo
    $brandData = DB::fetchAll(
        "SELECT m.display_name, s.visibility_pct, s.avg_position, s.sentiment_avg, s.mentions_count, s.answers_total
         FROM daily_snapshots s
         JOIN models m ON m.id = s.model_id
         WHERE s.project_id = ? AND s.entity_type = 'brand'
           AND s.run_date = (SELECT MAX(run_date) FROM daily_snapshots WHERE project_id = ? AND model_id = s.model_id AND entity_name = 'mi_marca')
         ORDER BY m.sort_order",
        [$projectId, $projectId]
    );

    // Competidores
    $compData = DB::fetchAll(
        "SELECT entity_name, visibility_pct, avg_position, sentiment_avg, mentions_count
         FROM daily_snapshots
         WHERE project_id = ? AND model_id IS NULL AND entity_type = 'competitor'
           AND run_date = (SELECT MAX(run_date) FROM daily_snapshots WHERE project_id = ? AND model_id IS NULL AND entity_type = 'competitor')
         ORDER BY visibility_pct DESC",
        [$projectId, $projectId]
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kraak Radar — Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="dashboard.php" class="nav-brand">Kraak Radar</a>
            <div class="nav-links">
                <a href="prompts.php">Prompts</a>
                <a href="competitors.php">Competidores</a>
                <a href="sources.php">Fuentes</a>
                <a href="export.php">Exportar</a>
                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                <a href="logout.php" class="btn-logout">Salir</a>
            </div>
        </div>
    </nav>

    <main class="main">
        <?php if (!$project): ?>
            <div class="empty-state">
                <h2>Bienvenido a Kraak Radar</h2>
                <p>Crea un proyecto para empezar a trackear tu marca en asistentes de IA.</p>
                <p>Necesitas configurar prompts y modelos activos para que el sistema comience a recolectar datos.</p>
                <p class="note">Los crons ejecutan consultas cada 5 minutos. Los primeros datos aparecerán en minutos.</p>
            </div>
        <?php else: ?>
            <div class="dash-header">
                <h2><?= htmlspecialchars($project['name']) ?></h2>
                <div class="controls">
                    <select onchange="location='?project=<?= $projectId ?>&days='+this.value">
                        <option value="7"  <?= $days==7 ?'selected':''?>>7 días</option>
                        <option value="30" <?= $days==30?'selected':''?>>30 días</option>
                        <option value="90" <?= $days==90?'selected':''?>>90 días</option>
                    </select>
                    <select onchange="location='?project='+this.value+'&days=<?= $days ?>'">
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id']==$projectId?'selected':'' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (!empty($snapshots)): ?>
            <div class="card">
                <h3>Evolución de Visibilidad</h3>
                <canvas id="visChart" height="80"></canvas>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3>Visibilidad por Modelo</h3>
                    <table class="data-table">
                        <thead><tr><th>Modelo</th><th>Visibilidad</th><th>Posición</th><th>Sentimiento</th></tr></thead>
                        <tbody>
                        <?php foreach ($brandData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['display_name']) ?></td>
                                <td><?= $row['visibility_pct'] ?>%</td>
                                <td><?= $row['avg_position'] ? '#' . round($row['avg_position'], 1) : '-' ?></td>
                                <td class="sent-<?= ($row['sentiment_avg'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
                                    <?= $row['sentiment_avg'] !== null ? round($row['sentiment_avg'], 2) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h3>Benchmark Competidores</h3>
                    <table class="data-table">
                        <thead><tr><th>Marca</th><th>Visibilidad</th><th>Posición</th><th>Sentimiento</th></tr></thead>
                        <tbody>
                        <?php foreach ($compData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['entity_name']) ?></td>
                                <td><?= $row['visibility_pct'] ?>%</td>
                                <td><?= $row['avg_position'] ? '#' . round($row['avg_position'], 1) : '-' ?></td>
                                <td class="sent-<?= ($row['sentiment_avg'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
                                    <?= $row['sentiment_avg'] !== null ? round($row['sentiment_avg'], 2) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <p class="note">No hay datos todavía. Los crons deben ejecutarse al menos una vez para que aparezcan las primeras métricas.</p>
                <p class="note">Verifica que:</p>
                <ul class="note-list">
                    <li>El proyecto tiene prompts activos</li>
                    <li>El proyecto tiene modelos asignados</li>
                    <li>Los crons están configurados en el servidor</li>
                    <li>La API key de OpenRouter es válida</li>
                </ul>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
    <?php if (!empty($snapshots)): ?>
    const labels = <?= json_encode(array_column($snapshots, 'run_date')) ?>;
    const values = <?= json_encode(array_map(fn($r) => (float) $r['visibility_pct'], $snapshots)) ?>;
    new Chart(document.getElementById('visChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Visibilidad (%)',
                data: values,
                borderColor: '#6c5ce7',
                backgroundColor: 'rgba(108,92,231,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#2a2a3a' } },
                x: { grid: { display: false } }
            }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>
