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

// OpenRouter para queries a modelos (fallback si el usuario no tiene BYOK)
define('OPENROUTER_API_KEY', '');
define('OPENROUTER_BASE', 'https://openrouter.ai/api/v1');

// DeepSeek directo para analyzer (fallback si el usuario no tiene BYOK)
define('DEEPSEEK_API_KEY', '');
define('DEEPSEEK_MODEL', 'deepseek-v4-flash');
// Precio del analyzer en USD por 1M tokens (entrada/salida)
define('DEEPSEEK_PRICE_IN', 0.14);
define('DEEPSEEK_PRICE_OUT', 0.28);

// API pública de registro (formulario de la landing)
define('LANDING_ORIGIN', 'https://rmickel81.github.io');
// Enlaces de pago por plan (vacío = flujo de contacto, no pago directo)
define('STRIPE_PAYMENT_LINKS', [
    'starter' => '',
    'pro'     => '',
    'agency'  => '',
]);

define('SESSION_LIFETIME', 604800);
define('CRON_BATCH_RUN', 10);
define('CRON_BATCH_ANALYZE', 20);
define('JOB_TIMEOUT_MIN', 15);
define('MAX_RETRIES', 3);
define('BASE_PATH', dirname(__DIR__));
define('LOG_PATH', BASE_PATH . '/cron/logs');
define('TIMEZONE', 'UTC');

date_default_timezone_set(TIMEZONE);
