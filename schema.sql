-- Kraak Radar — Esquema de base de datos
-- MySQL 8.0+ / MariaDB 10.6+

CREATE DATABASE IF NOT EXISTS kraak_radar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kraak_radar;

-- Cuentas
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(120) NOT NULL,
  plan          ENUM('free','starter','pro','agency') NOT NULL DEFAULT 'free',
  prompt_quota  SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  openrouter_key VARCHAR(255) NULL,
  deepseek_key   VARCHAR(255) NULL,
  last_login_at  DATETIME NULL,
  is_admin       TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Proyectos
CREATE TABLE projects (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  brand_name   VARCHAR(120) NOT NULL,
  brand_domain VARCHAR(190) NULL,
  aliases      JSON NULL,
  lang         CHAR(2) NOT NULL DEFAULT 'es',
  country      CHAR(2) NOT NULL DEFAULT 'ES',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (user_id),
  CONSTRAINT fk_proj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Competidores
CREATE TABLE competitors (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  name       VARCHAR(120) NOT NULL,
  domain     VARCHAR(190) NULL,
  aliases    JSON NULL,
  KEY (project_id),
  CONSTRAINT fk_comp_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modelos disponibles
CREATE TABLE models (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(120) NOT NULL UNIQUE,
  display_name  VARCHAR(80) NOT NULL,
  family        VARCHAR(40) NOT NULL,
  has_web_search TINYINT(1) NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modelos activos por proyecto
CREATE TABLE project_models (
  project_id INT UNSIGNED NOT NULL,
  model_id   SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, model_id),
  CONSTRAINT fk_pm_proj  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_model FOREIGN KEY (model_id)   REFERENCES models(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Topics
CREATE TABLE topics (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  name       VARCHAR(80) NOT NULL,
  KEY (project_id),
  CONSTRAINT fk_topic_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prompts
CREATE TABLE prompts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  topic_id   INT UNSIGNED NULL,
  text       VARCHAR(500) NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (project_id, is_active),
  CONSTRAINT fk_prompt_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cola de trabajos
CREATE TABLE jobs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prompt_id   INT UNSIGNED NOT NULL,
  model_id    SMALLINT UNSIGNED NOT NULL,
  run_date    DATE NOT NULL,
  status      ENUM('pending','running','done','error') NOT NULL DEFAULT 'pending',
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error  VARCHAR(255) NULL,
  locked_at   DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job (prompt_id, model_id, run_date),
  KEY idx_pick (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Respuestas crudas
CREATE TABLE answers (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id     BIGINT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  prompt_id  INT UNSIGNED NOT NULL,
  model_id   SMALLINT UNSIGNED NOT NULL,
  run_date   DATE NOT NULL,
  raw_text   MEDIUMTEXT NOT NULL,
  tokens_in  INT UNSIGNED NULL,
  tokens_out INT UNSIGNED NULL,
  cost_usd   DECIMAL(10,6) NULL,
  analyzed   TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_analyze (analyzed, id),
  KEY idx_proj_date (project_id, run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Menciones
CREATE TABLE mentions (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  answer_id      BIGINT UNSIGNED NOT NULL,
  project_id     INT UNSIGNED NOT NULL,
  run_date       DATE NOT NULL,
  model_id       SMALLINT UNSIGNED NOT NULL,
  entity_type    ENUM('brand','competitor') NOT NULL,
  entity_name    VARCHAR(120) NOT NULL,
  position       TINYINT UNSIGNED NULL,
  sentiment      ENUM('positive','neutral','negative') NOT NULL DEFAULT 'neutral',
  sentiment_score DECIMAL(3,2) NULL,
  KEY idx_proj_date (project_id, run_date, entity_type),
  CONSTRAINT fk_men_ans FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fuentes
CREATE TABLE sources (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  answer_id  BIGINT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  run_date   DATE NOT NULL,
  domain     VARCHAR(190) NOT NULL,
  url        VARCHAR(512) NULL,
  is_owned   TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_proj_domain (project_id, run_date, domain),
  CONSTRAINT fk_src_ans FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agregado diario
CREATE TABLE daily_snapshots (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  run_date        DATE NOT NULL,
  model_id        SMALLINT UNSIGNED NULL,
  entity_type     ENUM('brand','competitor') NOT NULL,
  entity_name     VARCHAR(120) NOT NULL,
  answers_total   SMALLINT UNSIGNED NOT NULL,
  mentions_count  SMALLINT UNSIGNED NOT NULL,
  visibility_pct  DECIMAL(5,2) NOT NULL,
  avg_position    DECIMAL(4,2) NULL,
  sentiment_avg   DECIMAL(3,2) NULL,
  UNIQUE KEY uq_snap (project_id, run_date, model_id, entity_type, entity_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modelos iniciales
INSERT INTO models (slug, display_name, family, has_web_search, is_active, sort_order) VALUES
('openai/gpt-4o',            'ChatGPT (GPT-4o)',        'openai',      1, 1, 10),
('google/gemini-2.0-flash', 'Gemini 2.0 Flash',        'google',      1, 1, 20),
('anthropic/claude-sonnet-4','Claude Sonnet 4',         'anthropic',   0, 1, 30),
('perplexity/sonar-pro',     'Perplexity Sonar Pro',    'perplexity',  1, 1, 40),
('deepseek/deepseek-chat',   'DeepSeek Chat',           'deepseek',    1, 1, 50),
('qwen/qwen-2.5-72b-instruct','Qwen 2.5 72B',          'qwen',        0, 1, 60),
('kimi/kimi-chat',           'Kimi Chat',               'moonshot',    0, 1, 70),
('glm/glm-4',                'GLM-4 (Zhipu)',           'glm',         0, 1, 80),
('minimax/minimax-chat',     'MiniMax Chat',            'minimax',     0, 1, 90);

-- Registro de costes
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
  KEY idx_proj_date (project_id, run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configuracion del sistema
CREATE TABLE settings (
  `key`   VARCHAR(80) PRIMARY KEY,
  `value` TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
