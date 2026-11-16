-- =====================================================================
-- 01_ranking_technologies_tablas.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Crea las tablas de resumen del Ranking de Tecnologías
--   (/dashboard/ranking/technologies), que llena
--   `php artisan ranking:refresh-technologies`:
--     - technology_rankings            una fila por tecnología y semestre
--     - technology_ranking_meta        una fila por semestre (totales y fecha)
--     - technology_ranking_weekly      evolución: foto del ranking semestral en el
--                                      corte de cada semana ISO / quincena / mes
--     - technology_ranking_weekly_meta una fila por ventana de la evolución
--   Y, solo si no existen, cache / cache_locks / jobs / failed_jobs (Laravel:
--   Cache::lock, caché de JobMarketStatus y cola del botón "Recalcular").
--   Misma estructura que las tablas de Lenguajes (docs/produccion/ranking_lenguajes),
--   que ya están probadas.
--
-- REQUISITOS
--   MySQL 8.0 o superior (la lectura usa ROW_NUMBER() OVER).
--
-- TIEMPO ESTIMADO / BLOQUEOS
--   Menos de 1 segundo. Tablas nuevas; no bloquea tablas existentes.
--
-- RE-EJECUCIÓN
--   Idempotente (CREATE TABLE IF NOT EXISTS; Note 1050 si ya existen).
--   Warning 1681 (TINYINT(1)) es esperado: es el booleano de Laravel.
--
-- REVERSIÓN
--   03_ranking_technologies_rollback.sql
-- =====================================================================

-- 1. technology_rankings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technology_rankings` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`             SMALLINT UNSIGNED NOT NULL,
  `period`           ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,

  -- Copia de market_entities al momento del cálculo
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_isil`         TINYINT(1)        NOT NULL DEFAULT 0,
  `has_trend`        TINYINT(1)        NOT NULL DEFAULT 0,
  `classification`   VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  -- Métricas crudas
  `total_jobs`       INT UNSIGNED      NOT NULL DEFAULT 0,
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,

  -- Puntajes 0-100 sin redondear. final_score y position se recalculan al
  -- leer con los pesos vigentes; aquí quedan como referencia del cálculo.
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technology_rankings_period_entity` (`year`, `period`, `market_entity_id`),
  KEY `idx_technology_rankings_period_score` (`year`, `period`, `final_score`),
  KEY `idx_technology_rankings_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. technology_ranking_meta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technology_ranking_meta` (
  `id`                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `period`              ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  `vacantes_analizadas` INT UNSIGNED      NOT NULL DEFAULT 0,
  `reportes_analizados` INT UNSIGNED      NOT NULL DEFAULT 0,
  `max_labor`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `labor_weight`        DECIMAL(4,3)      NOT NULL,
  `trend_weight`        DECIMAL(4,3)      NOT NULL,
  `rows_count`          INT UNSIGNED      NOT NULL DEFAULT 0,
  `duration_ms`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`       TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technology_ranking_meta_period` (`year`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Tablas de Laravel para caché y colas (solo si faltan)
--    Mismo esquema que las migraciones 0001_01_01_000001/000002.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value`      MEDIUMTEXT   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner`      VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `queue`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`      LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL,
  `reserved_at`  INT UNSIGNED     DEFAULT NULL,
  `available_at` INT UNSIGNED     NOT NULL,
  `created_at`   INT UNSIGNED     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue`      TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`    LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception`  LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Evolución (fotos por fecha de corte)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technology_ranking_weekly` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`      ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`       VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  -- Año ISO para weekly (la semana 1 de 2027 puede empezar el 28-dic-2026); año calendario para el resto
  `year`             SMALLINT UNSIGNED NOT NULL,
  `cutoff_date`      DATE              NOT NULL,              -- fecha de la foto
  `semester`         CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,  -- '2026-s2'
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jobs`             INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes acumuladas del semestre al corte
  `new_jobs`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes publicadas dentro de la ventana
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,      -- reportes del semestre disponibles al corte
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trw_window_entity` (`period_type`, `period_key`, `market_entity_id`),
  KEY `idx_trw_type_year` (`period_type`, `year`),
  KEY `idx_trw_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `technology_ranking_weekly_meta` (
  `id`                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`       ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`        VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year`              SMALLINT UNSIGNED NOT NULL,
  `label`             VARCHAR(80)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date`        DATE              NOT NULL,
  `end_date`          DATE              NOT NULL,
  `cutoff_date`       DATE              NOT NULL,             -- end_date, o hoy si está en curso
  `semester`          CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_semester_start` TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = primera foto de un semestre (el acumulado se reinicia)
  `is_partial`        TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = ventana en curso
  `unique_offers`     INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas acumuladas del semestre al corte
  `new_unique_offers` INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas publicadas dentro de la ventana
  `max_labor`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `entities_count`    INT UNSIGNED      NOT NULL DEFAULT 0,
  `calculated_at`     TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trwm_window` (`period_type`, `period_key`),
  KEY `idx_trwm_type_year_start` (`period_type`, `year`, `start_date`),
  KEY `idx_trwm_type_start` (`period_type`, `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK 01: tablas del ranking de tecnologías listas' AS resultado;
