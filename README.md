# Kraak Radar

Tracker de visibilidad de marca en buscadores de IA (GEO / AEO).

Mide cómo aparece tu marca cuando los usuarios preguntan a los LLMs sobre tu sector. Visibilidad, posición, sentimiento, fuentes citadas y benchmark contra competidores.

**Diferencial vs competidores (Peec, Profound):**
- Modelos chinos: DeepSeek, Qwen, GLM, Kimi, MiniMax
- Multi-idioma real con español nativo
- BYOK (Bring Your Own Key) — usas tus propias APIs, con fallback a keys del servidor
- Código abierto (MIT no comercial)

## Stack

- PHP 8.3, MySQL 8 / MariaDB 10.6
- Sin Composer, sin frameworks
- PDO nativo, Vanilla JS + Chart.js
- Diseñado para hosting compartido (cron-only) y para Docker

## Quickstart con Docker (recomendado)

```bash
git clone https://github.com/rmickel81/kraak-radar.git
cd kraak-radar
docker compose up -d --build
```

Eso es todo. El entrypoint genera `config/config.php` desde las variables de entorno
del compose si no existe, y MySQL importa `schema.sql` en el primer arranque.

- App: http://localhost:8081
- MySQL: `localhost:3308` (user `radar`, pass `radar_test_2026`)

Para que los crons ejecuten consultas reales, pasa tus keys (o configúralas luego en la página **APIs** de la app, por usuario):

```bash
OPENROUTER_API_KEY=sk-or-... DEEPSEEK_API_KEY=sk-... docker compose up -d
```

Ejecutar los crons dentro del contenedor:

```bash
docker exec radar-web php /var/www/cron/plan.php
docker exec radar-web php /var/www/cron/run.php
docker exec radar-web php /var/www/cron/analyze.php
docker exec radar-web php /var/www/cron/snapshot.php
```

Test de integración (requiere el stack arriba y un usuario `test@kraak.app`):

```bash
./test_integration.sh
```

## Instalación manual (hosting compartido)

1. Crear base de datos e importar `schema.sql`
2. Copiar `config/config.sample.php` como `config/config.php` y rellenar credenciales
3. Subir `public/` al document root; `lib/`, `config/` y `cron/` un nivel por encima (fuera del docroot)
4. Configurar crons:

```
*/5 * * * *  php /path/cron/run.php
*/5 * * * *  php /path/cron/analyze.php
5   2 * * *  php /path/cron/plan.php
0   3 * * *  php /path/cron/snapshot.php
```

## Migración v3 → v4 (instalaciones existentes)

```bash
mysql -u user -p kraak_radar < migrate_v4.sql
```

Añade: tabla `registrations`, `jobs.lock_owner`, `UNIQUE(job_id)` en answers,
reintentos del analyzer y precios por modelo.

## Estructura

```
├── config/config.sample.php   # Configuración (copiar como config.php)
├── schema.sql                 # Base de datos (v4)
├── migrate_v4.sql             # Migración para instalaciones v3
├── lib/
│   ├── db.php                 # PDO singleton + helpers
│   ├── auth.php               # Sesiones, CSRF, rate limiting, safeRedirect
│   ├── openrouter.php         # Cliente OpenRouter (multi-modelo)
│   ├── deepseek.php           # Cliente DeepSeek (analyzer)
│   ├── analyzer.php           # Extracción de menciones/fuentes a JSON
│   └── aggregation.php        # Cálculo de snapshots (cron y seed)
├── cron/
│   ├── bootstrap.php          # Carga inicial para crons
│   ├── plan.php               # Crea jobs del día (idempotente)
│   ├── run.php                # Ejecuta jobs (lock con dueño, BYOK, costes)
│   ├── analyze.php            # Analiza respuestas (reintentos, BYOK, costes)
│   └── snapshot.php           # Agrega datos diarios
├── public/
│   ├── index.php              # Login / registro (pública)
│   ├── dashboard.php          # Dashboard principal
│   ├── prompts.php            # CRUD prompts + modelos activos
│   ├── competitors.php        # CRUD competidores
│   ├── sources.php            # Fuentes citadas
│   ├── costs.php              # Costes de API
│   ├── settings.php           # Claves BYOK por usuario
│   ├── export.php             # Export CSV
│   ├── seed.php               # Datos de prueba (POST + CSRF)
│   ├── api/register.php       # API pública de registro (landing)
│   └── assets/css/style.css   # Estilos
├── Dockerfile                 # PHP 8.3 + Apache
├── docker-entrypoint.sh       # Genera config.php desde env si falta
└── docker-compose.yml         # Stack completo (web + MySQL)
```

## Arquitectura

```
plan.php (diario)      → crea jobs prompt × modelo (UNIQUE por día)
run.php (5 min)        → lock con dueño → OpenRouter → answers + cost_log
analyze.php (5 min)    → DeepSeek → mentions + sources + cost_log
snapshot.php (diario)  → daily_snapshots (el dashboard NUNCA agrega al vuelo)
```

- **Idempotencia total**: re-ejecutar cualquier cron no duplica datos.
- **Jobs zombies**: los locks expiran a los `JOB_TIMEOUT_MIN` minutos y vuelven a `pending`.
- **Concurrencia segura**: cada runner bloquea su batch con un `lock_owner` único.
- **BYOK**: cada usuario puede tener sus propias keys (página APIs); si no, se usan las del servidor.

## Licencia

Kraak Radar -- Copyright (c) 2026 KraakVC OU

Lead Developer: Roberto Mickel Abrante

Uso no comercial: MIT-like, gratuito para educación, investigación y uso personal.
Uso comercial: requiere licencia separada. Contactar a KraakVC OU.
