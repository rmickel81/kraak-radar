# Kraak Radar

Tracker de visibilidad de marca en buscadores de IA (GEO / AEO).

Mide cómo aparece tu marca cuando los usuarios preguntan a los LLMs sobre tu sector. Visibilidad, posición, sentimiento, fuentes citadas y benchmark contra competidores.

**Diferencial vs competidores (Peec, Profound):**
- Modelos chinos: DeepSeek, Qwen, GLM, Kimi, MiniMax
- Multi-idioma real con español nativo
- BYOK (Bring Your Own Key) — usas tus propias APIs
- Código abierto (MIT)

## Stack

- PHP 8.3, MySQL / MariaDB
- Sin Composer, sin frameworks
- PDO nativo, Vanilla JS + Chart.js
- Diseñado para hosting compartido

## Estructura

```
├── config/config.sample.php   # Configuración (copiar como config.php)
├── schema.sql                 # Base de datos
├── lib/
│   ├── db.php                 # PDO singleton + helpers
│   ├── deepseek.php           # Cliente DeepSeek API
│   ├── openrouter.php         # Cliente OpenRouter API
│   └── analyzer.php           # Parseo de respuestas con DeepSeek
├── cron/
│   ├── bootstrap.php          # Carga inicial para crons
│   ├── plan.php               # Crea jobs del día
│   ├── run.php                # Ejecuta jobs contra modelos
│   ├── analyze.php            # Analiza respuestas (DeepSeek)
│   └── snapshot.php           # Agrega datos diarios
├── public/
│   ├── index.php              # Login
│   ├── dashboard.php          # Dashboard principal
│   ├── prompts.php            # CRUD prompts + modelos
│   ├── competitors.php        # CRUD competidores
│   ├── sources.php            # Fuentes citadas
│   ├── export.php             # Export CSV
│   ├── project_create.php     # Crear proyecto
│   └── assets/css/style.css   # Estilos
```

## Instalación

1. Crear base de datos e importar `schema.sql`
2. Copiar `config/config.sample.php` como `config/config.php`
3. Rellenar credenciales de BD y API keys
4. Subir `public/` a tu hosting
5. Configurar crons en el servidor:

```
*/5 * * * *  php /path/cron/run.php
*/5 * * * *  php /path/cron/analyze.php
5   2 * * *  php /path/cron/plan.php
0   3 * * *  php /path/cron/snapshot.php
```

## Licencia

MIT
