<?php
/**
 * Panel de gastos y costes
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
$user = requireLogin();

$projects = DB::fetchAll('SELECT id, name FROM projects WHERE user_id = ? ORDER BY name', [$user['id']]);
$projectId = (int) ($_GET['project'] ?? 0);
$days = (int) ($_GET['days'] ?? 30);
$dateFrom = date('Y-m-d', strtotime("-{$days} days"));

// Costes por día
$dailyCosts = [];
$monthlyTotal = 0;
$modelBreakdown = [];

if ($projectId) {
    $dailyCosts = DB::fetchAll(
        "SELECT run_date, 
                SUM(tokens_in) as tokens_in, SUM(tokens_out) as tokens_out, 
                SUM(cost_usd) as cost_usd
         FROM cost_log
         WHERE project_id = ? AND run_date >= ?
         GROUP BY run_date ORDER BY run_date ASC",
        [$projectId, $dateFrom]
    );
    
    $monthlyTotal = DB::fetchOne(
        "SELECT COALESCE(SUM(cost_usd),0) as total FROM cost_log 
         WHERE project_id = ? AND run_date >= ?",
        [$projectId, $dateFrom]
    )['total'] ?? 0;
    
    $modelBreakdown = DB::fetchAll(
        "SELECT COALESCE(m.display_name, 'DeepSeek Analyzer') as model,
                SUM(cl.tokens_in) as tokens_in, SUM(cl.tokens_out) as tokens_out,
                SUM(cl.cost_usd) as cost_usd
         FROM cost_log cl
         LEFT JOIN models m ON m.id = cl.model_id
         WHERE cl.project_id = ? AND cl.run_date >= ?
         GROUP BY cl.model_id
         ORDER BY cost_usd DESC",
        [$projectId, $dateFrom]
    );
} else {
    // Agregado global del usuario
    $monthlyTotal = DB::fetchOne(
        "SELECT COALESCE(SUM(cl.cost_usd),0) as total 
         FROM cost_log cl
         JOIN projects p ON p.id = cl.project_id
         WHERE p.user_id = ? AND cl.run_date >= ?",
        [$user['id'], $dateFrom]
    )['total'] ?? 0;
}

// Proyección mensual
$daysInPeriod = min($days, (int) date('d'));
$projectedMonth = $daysInPeriod > 0 ? ($monthlyTotal / $daysInPeriod) * 30 : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kraak Radar — Costes</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<div class="app-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>Costes y Consumo</h1>
            <p class="page-desc">Seguimiento de gastos por uso de APIs.</p>
        </div>

        <!-- KPIs -->
        <div class="kpi-row">
            <div class="kpi-card">
                <span class="kpi-label">Gasto período</span>
                <span class="kpi-value">$<?= number_format($monthlyTotal, 4) ?></span>
                <span class="kpi-sub">Últimos <?= $days ?> días</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Proyección mensual</span>
                <span class="kpi-value">$<?= number_format($projectedMonth, 4) ?></span>
                <span class="kpi-sub">Estimado 30 días</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Modelos activos</span>
                <span class="kpi-value"><?= count($modelBreakdown) ?></span>
                <span class="kpi-sub">En el período</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Coste / prompt</span>
                <span class="kpi-value">~
                    <?php
                    $totalAnswers = DB::fetchOne(
                        "SELECT COUNT(*) as c FROM answers a
                         JOIN projects p ON p.id = a.project_id
                         WHERE p.user_id = ? AND a.run_date >= ?",
                        [$user['id'], $dateFrom]
                    )['c'] ?? 0;
                    echo $totalAnswers > 0 ? '$' . number_format($monthlyTotal / $totalAnswers, 6) : '-';
                    ?>
                </span>
                <span class="kpi-sub"><?= number_format($totalAnswers) ?> respuestas</span>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <form class="filter-bar">
                <select name="project" onchange="this.form.submit()">
                    <option value="">Todos los proyectos</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id']==$projectId?'selected':'' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="days" onchange="this.form.submit()">
                    <option value="7" <?= $days==7?'selected':'' ?>>7 días</option>
                    <option value="30" <?= $days==30?'selected':'' ?>>30 días</option>
                    <option value="90" <?= $days==90?'selected':'' ?>>90 días</option>
                </select>
            </form>
        </div>

        <!-- Gráfica -->
        <?php if (!empty($dailyCosts)): ?>
        <div class="card">
            <h3>Evolución del gasto diario</h3>
            <canvas id="costChart" height="80"></canvas>
        </div>
        <?php endif; ?>

        <!-- Desglose por modelo -->
        <?php if (!empty($modelBreakdown)): ?>
        <div class="card">
            <h3>Desglose por modelo</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Modelo</th>
                        <th>Tokens in</th>
                        <th>Tokens out</th>
                        <th>Coste USD</th>
                        <th>% del total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modelBreakdown as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['model']) ?></strong></td>
                        <td><?= number_format($row['tokens_in']) ?></td>
                        <td><?= number_format($row['tokens_out']) ?></td>
                        <td class="cost-cell">$<?= number_format($row['cost_usd'], 6) ?></td>
                        <td><?= $monthlyTotal > 0 ? number_format($row['cost_usd'] / $monthlyTotal * 100, 1) : 0 ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td><strong><?= number_format(array_sum(array_column($modelBreakdown, 'tokens_in'))) ?></strong></td>
                        <td><strong><?= number_format(array_sum(array_column($modelBreakdown, 'tokens_out'))) ?></strong></td>
                        <td class="cost-cell"><strong>$<?= number_format($monthlyTotal, 4) ?></strong></td>
                        <td><strong>100%</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="card">
            <p class="text-muted">No hay datos de coste en el período seleccionado. Los costes se registran automáticamente cuando los crons ejecutan consultas.</p>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
<?php if (!empty($dailyCosts)): ?>
const labels = <?= json_encode(array_column($dailyCosts, 'run_date')) ?>;
const costData = <?= json_encode(array_map(fn($r) => round((float) $r['cost_usd'], 6), $dailyCosts)) ?>;
new Chart(document.getElementById('costChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Coste USD',
            data: costData,
            backgroundColor: 'rgba(88,166,255,0.5)',
            borderColor: '#58a6ff',
            borderWidth: 1,
            borderRadius: 3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#21262d' },
                ticks: { callback: v => '$' + v.toFixed(4) }
            },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>
