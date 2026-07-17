-- migrate_v4.sql — Migración v3 → v4 para instalaciones existentes
-- Ejecutar UNA vez sobre la base de datos kraak_radar.
-- Si instalas desde cero, usa schema.sql directamente (no hace falta).

USE kraak_radar;

-- 1. Registros desde la landing pública
CREATE TABLE IF NOT EXISTS registrations (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(190) NOT NULL UNIQUE,
  plan       ENUM('starter','pro','agency') NOT NULL,
  domain     VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Lock con dueño en jobs (evita doble ejecución concurrente)
ALTER TABLE jobs
  ADD COLUMN lock_owner VARCHAR(40) NULL AFTER locked_at,
  ADD KEY idx_recovery (status, locked_at);

-- 3. Respuestas: unicidad por job + reintentos del analyzer
ALTER TABLE answers
  ADD UNIQUE KEY uq_answer_job (job_id),
  ADD COLUMN analyze_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER analyzed,
  ADD COLUMN analyze_error VARCHAR(255) NULL AFTER analyze_attempts;

-- 4. Precios por modelo (USD por 1M tokens)
ALTER TABLE models
  ADD COLUMN price_in_usd  DECIMAL(10,4) NOT NULL DEFAULT 0 AFTER has_web_search,
  ADD COLUMN price_out_usd DECIMAL(10,4) NOT NULL DEFAULT 0 AFTER price_in_usd;

UPDATE models SET price_in_usd = 2.5000, price_out_usd = 10.0000 WHERE slug = 'openai/gpt-4o';
UPDATE models SET price_in_usd = 0.1000, price_out_usd =  0.4000 WHERE slug = 'google/gemini-2.0-flash';
UPDATE models SET price_in_usd = 3.0000, price_out_usd = 15.0000 WHERE slug = 'anthropic/claude-sonnet-4';
UPDATE models SET price_in_usd = 3.0000, price_out_usd = 15.0000 WHERE slug = 'perplexity/sonar-pro';
UPDATE models SET price_in_usd = 0.2700, price_out_usd =  1.1000 WHERE slug = 'deepseek/deepseek-chat';
UPDATE models SET price_in_usd = 0.3500, price_out_usd =  0.3500 WHERE slug = 'qwen/qwen-2.5-72b-instruct';

-- 5. Índice extra en cost_log
ALTER TABLE cost_log ADD KEY idx_date (run_date);

-- 6. Limpiar snapshots globales duplicados (el viejo bug del NULL en UNIQUE)
DELETE s1 FROM daily_snapshots s1
JOIN daily_snapshots s2
  ON s1.project_id = s2.project_id
 AND s1.run_date = s2.run_date
 AND s1.entity_type = s2.entity_type
 AND s1.entity_name = s2.entity_name
 AND s1.model_id IS NULL AND s2.model_id IS NULL
 AND s1.id > s2.id;

-- 7. Versión
INSERT INTO settings (`key`, `value`) VALUES ('app_version', '4.0.0')
ON DUPLICATE KEY UPDATE `value` = '4.0.0';
