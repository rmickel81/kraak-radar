<?php
/**
 * Kraak Radar — AI Visibility Tracker
 * 
 * Configuración. Copia como config.php y rellena credenciales.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'kraak_radar');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_URL', 'https://radar.kraak.app');
define('APP_NAME', 'Kraak Radar');

// OpenRouter para queries a modelos
define('OPENROUTER_API_KEY', '');
define('OPENROUTER_BASE', 'https://openrouter.ai/api/v1');

// DeepSeek directo para analyzer
define('DEEPSEEK_API_KEY', '');
define('DEEPSEEK_MODEL', 'deepseek-v4-flash');

define('SESSION_LIFETIME', 604800);
define('CRON_BATCH_RUN', 10);
define('CRON_BATCH_ANALYZE', 20);
define('JOB_TIMEOUT_MIN', 15);
define('MAX_RETRIES', 3);
define('BASE_PATH', dirname(__DIR__));
define('LOG_PATH', BASE_PATH . '/cron/logs');
define('TIMEZONE', 'UTC');

date_default_timezone_set(TIMEZONE);
