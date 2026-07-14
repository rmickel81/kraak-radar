-- Kraak Radar — Migración v2: APIs, costes y seguridad
-- Ejecutar después de schema.sql

-- API keys por usuario
ALTER TABLE users
  ADD COLUMN openrouter_key VARCHAR(255) NULL AFTER prompt_quota,
  ADD COLUMN deepseek_key VARCHAR(255) NULL AFTER openrouter_key,
  ADD COLUMN last_login_at DATETIME NULL AFTER deepseek_key,
  ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER plan,
  ADD INDEX idx_last_login (last_login_at);

-- Registro de gastos por API
CREATE TABLE cost_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  model_id    SMALLINT UNSIGNED NULL,
  run_date    DATE NOT NULL,
  tokens_in   INT UNSIGNED NOT NULL DEFAULT 0,
  tokens_out  INT UNSIGNED NOT NULL DEFAULT 0,
  cost_usd    DECIMAL(10,6) NOT NULL DEFAULT 0,
  source      ENUM('openrouter','deepseek_analyzer') NOT NULL DEFAULT 'openrouter',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_proj_date (project_id, run_date),
  KEY idx_date (run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vista mensual de costes agregados
CREATE TABLE cost_monthly (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  year_month  CHAR(7) NOT NULL,  -- '2026-07'
  tokens_in   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tokens_out  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  cost_usd    DECIMAL(12,6) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_cmonth (project_id, year_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configuración general del sistema
CREATE TABLE settings (
  `key`   VARCHAR(80) PRIMARY KEY,
  `value` TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, `value`) VALUES
('app_version', '2.0.0'),
('default_openrouter_key', ''),
('deepseek_default_model', 'deepseek-v4-flash'),
('tracking_enabled', '1'),
('max_prompts_per_project', '50');
