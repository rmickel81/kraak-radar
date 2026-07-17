<?php
/**
 * Bootstrap para scripts de cron
 * Carga configuración y librerías
 */

// Buscar config en orden
$paths = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../../config/config.php',
];

$loaded = false;
foreach ($paths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    echo "ERROR: config.php no encontrado. Copia config.sample.php como config.php\n";
    exit(1);
}

// Crear logs dir
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

require_once BASE_PATH . '/lib/db.php';
require_once BASE_PATH . '/lib/deepseek.php';
require_once BASE_PATH . '/lib/analyzer.php';
require_once BASE_PATH . '/lib/aggregation.php';

function logMsg(string $msg): void {
    $ts = date('Y-m-d H:i:s');
    file_put_contents(LOG_PATH . '/cron.log', "[{$ts}] {$msg}\n", FILE_APPEND);
    echo "[{$ts}] {$msg}\n";
}
