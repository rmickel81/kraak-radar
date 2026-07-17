<?php
/**
 * Dashboard principal
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
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
         WHERE project_id = ? AND model_id IS NULL AND entity_type = 'brand'
           AND run_date >= ?
         ORDER BY run_date ASC",
        [$projectId, $dateFrom]
    );

    // Últimos datos por modelo
    $brandData = DB::fetchAll(
        "SELECT m.display_name, s.visibility_pct, s.avg_position, s.sentiment_avg, s.mentions_count, s.answers_total
         FROM daily_snapshots s
         JOIN models m ON m.id = s.model_id
         WHERE s.project_id = ? AND s.entity_type = 'brand' AND s.model_id IS NOT NULL
           AND s.run_date = (SELECT MAX(run_date) FROM daily_snapshots
                             WHERE project_id = ? AND model_id = s.model_id AND entity_type = 'brand')
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<div class="app-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p class="page-desc">Visibilidad de tu marca en asistentes de IA.</p>
        </div>
        <?php if (!$project): ?>
            <div class="onboarding">
                <div class="onboard-hero">
                    <h2>Bienvenido a Kraak Radar</h2>
                    <p class="onboard-lead">Monitoriza la visibilidad de tu marca en asistentes de IA. Descubre que dicen de ti ChatGPT, Gemini, DeepSeek y otros LLMs cuando la gente pregunta sobre tu sector.</p>
                </div>
                
                <div class="onboard-steps">
                    <div class="onboard-step">
                        <div class="on-step-num">1</div>
                        <div class="on-step-body">
                            <h4>Crea un proyecto</h4>
                            <p>Define el nombre de tu marca, tu dominio web, el idioma y el pais en el que operas.</p>
                            <form method="post" action="project_create.php" class="onboard-form">
                                <?= csrfField() ?>
                                <input type="text" name="name" placeholder="Nombre del proyecto" required>
                                <input type="text" name="brand_name" placeholder="Nombre de tu marca" required>
                                <input type="text" name="brand_domain" placeholder="Dominio (ej: kraak.app)">
                                <button type="submit" class="btn-primary">Crear proyecto</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="onboard-divider"></div>
                    
                    <div class="onboard-step">
                        <div class="on-step-num">2</div>
                        <div class="on-step-body">
                            <h4>Configura prompts</h4>
                            <p>Define las preguntas conversacionales que se lanzaran contra los modelos cada dia. Ejemplos: "cual es la mejor plataforma de tracking IA?", "que herramienta recomiendas para monitorizar marca en LLMs?".</p>
                        </div>
                    </div>
                    
                    <div class="onboard-divider"></div>
                    
                    <div class="onboard-step">
                        <div class="on-step-num">3</div>
                        <div class="on-step-body">
                            <h4>Activa modelos y APIs</h4>
                            <p>Selecciona los modelos que quieres trackear en Prompts > Modelos Activos. Luego configura tus claves de API en la seccion APIs.</p>
                        </div>
                    </div>
                    
                    <div class="onboard-divider"></div>
                    
                    <div class="onboard-step">
                        <div class="on-step-num">4</div>
                        <div class="on-step-body">
                            <h4>Ejecuta los crons</h4>
                            <p>Los crons lanzan preguntas contra cada modelo, analizan las respuestas y agregan los resultados automaticamente. Configuralos en tu servidor y los datos empezaran a aparecer en minutos.</p>
                            <p class="note">Si estas en modo pruebas, usa el boton "Generar datos de prueba" despues de crear el proyecto para ver el dashboard con datos simulados.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($project): ?>
        <!-- Definición de métricas -->
        <div class="metrics-guide">
            <div class="metric-def">
                <span class="md-icon"><?php $_s = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'; echo $_s; ?></span>
                <div class="md-body">
                    <strong>Visibilidad</strong>
                    <span>Porcentaje de veces que los asistentes de IA mencionan tu marca respecto al total de consultas. Si tienes 10 prompts contra 5 modelos (50 consultas/dia) y te mencionan en 25, tu visibilidad es del 50%.</span>
                </div>
            </div>
            <div class="metric-def">
                <span class="md-icon"><?php echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'; ?></span>
                <div class="md-body">
                    <strong>Posicion</strong>
                    <span>Orden medio en el que apareces cuando los modelos listan varias marcas. Posicion 1 = siempre te mencionan primero. Se calcula solo sobre respuestas donde hubo mencion.</span>
                </div>
            </div>
            <div class="metric-def">
                <span class="md-icon"><?php echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'; ?></span>
                <div class="md-body">
                    <strong>Sentimiento</strong>
                    <span>Valor entre -1.0 y 1.0 que refleja el tono de la mencion. Positivo = hablan bien de tu marca. Negativo = criticas. Neutro = mencion sin carga emocional.</span>
                </div>
            </div>
            <div class="metric-def">
                <span class="md-icon"><?php echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'; ?></span>
                <div class="md-body">
                    <strong>Fuentes</strong>
                    <span>Dominios y URLs que los modelos citan como referencia al hablar de tu sector. Saber que fuentes alimentan a los LLMs te permite optimizar tu presencia en ellas.</span>
                </div>
            </div>
        </div>

            <div class="dash-header">
                <h2><?= htmlspecialchars($project['name']) ?></h2>
                <div class="controls">
                    <a href="seed.php?project=<?= $projectId ?>" class="btn-seed">Generar datos de prueba</a>
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
                borderColor: '#58a6ff',
                backgroundColor: 'rgba(88,166,255,0.12)',
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
</main>
</div>
</body>
</html>
