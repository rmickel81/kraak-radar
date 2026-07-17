#!/bin/bash
# Entrypoint de Kraak Radar
# Genera config/config.php desde variables de entorno si no existe,
# para que `docker compose up` funcione en un clone limpio.
set -e

CONFIG=/var/www/config/config.php

if [ ! -f "$CONFIG" ]; then
    echo "[entrypoint] Generando config.php desde variables de entorno..."
    cat > "$CONFIG" <<EOF
<?php
// Generado por docker-entrypoint.sh ($(date -u '+%Y-%m-%d %H:%M UTC'))
// Edita este archivo o define las variables de entorno y borra este fichero.
define('DB_HOST', '${DB_HOST:-db}');
define('DB_NAME', '${DB_NAME:-kraak_radar}');
define('DB_USER', '${DB_USER:-radar}');
define('DB_PASS', '${DB_PASS:-radar_test_2026}');
define('DB_CHARSET', 'utf8mb4');

define('APP_URL', '${APP_URL:-http://localhost:8081}');
define('APP_NAME', 'Kraak Radar');

define('OPENROUTER_API_KEY', '${OPENROUTER_API_KEY}');
define('OPENROUTER_BASE', 'https://openrouter.ai/api/v1');

define('DEEPSEEK_API_KEY', '${DEEPSEEK_API_KEY}');
define('DEEPSEEK_MODEL', 'deepseek-v4-flash');
define('DEEPSEEK_PRICE_IN', 0.14);
define('DEEPSEEK_PRICE_OUT', 0.28);

define('LANDING_ORIGIN', '${LANDING_ORIGIN:-https://rmickel81.github.io}');
define('STRIPE_PAYMENT_LINKS', ['starter' => '', 'pro' => '', 'agency' => '']);

define('SESSION_LIFETIME', 604800);
define('CRON_BATCH_RUN', 10);
define('CRON_BATCH_ANALYZE', 20);
define('JOB_TIMEOUT_MIN', 15);
define('MAX_RETRIES', 3);
define('BASE_PATH', '/var/www');
define('LOG_PATH', '/var/www/cron/logs');
define('TIMEZONE', 'UTC');

date_default_timezone_set(TIMEZONE);
EOF
    chown www-data:www-data "$CONFIG" 2>/dev/null || true
    echo "[entrypoint] config.php creado."
fi

mkdir -p /var/www/cron/logs
exec apache2-foreground
