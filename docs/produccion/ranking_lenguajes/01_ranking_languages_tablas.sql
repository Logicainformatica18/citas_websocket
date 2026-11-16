-- =====================================================================
-- 01_ranking_languages_tablas.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Crea las tablas de resumen del Ranking de Lenguajes
--   (/dashboard/ranking/languages), que llena el comando
--   `php artisan ranking:refresh-languages`:
--     - language_rankings     : una fila por lenguaje y semestre.
--     - language_ranking_meta : una fila por semestre (totales y fecha
--                               del último cálculo).
--   Y, solo si no existen, las tablas que Laravel necesita con
--   CACHE_STORE=database y QUEUE_CONNECTION=database:
--     - cache, cache_locks    : Cache::remember (JobMarketStatus) y
--                               Cache::lock (evita dos recálculos a la vez).
--     - jobs, failed_jobs     : cola del botón "Recalcular ranking".
--   En una instalación normal de Laravel estas cuatro ya existen; el
--   CREATE TABLE IF NOT EXISTS no las toca.
--
-- REQUISITOS
--   MySQL 8.0 o superior (el ranking usa ROW_NUMBER() OVER (...)).
--   Ejecutar conectado a la base del observatorio (USE <base>;).
--
-- TIEMPO ESTIMADO
--   Menos de 1 segundo: son tablas nuevas y vacías.
--
-- BLOQUEOS
--   Ninguno sobre tablas existentes.
--
-- RE-EJECUCIÓN
--   Se puede ejecutar varias veces: todo es CREATE TABLE IF NOT EXISTS.
--   Si una tabla ya existe, MySQL muestra un warning (Note 1050), no un error.
--
-- WARNINGS ESPERADOS (no son errores)
--   - Note 1050 "Table '...' already exists": la tabla ya estaba; no se tocó.
--   - Warning 1681 "Integer display width is deprecated": por TINYINT(1)
--     en has_isil/has_trend. Es el tipo booleano que usa Laravel; se deja
--     así a propósito.
--
-- REVERSIÓN
--   03_ranking_languages_rollback.sql (elimina solo las dos tablas del ranking).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. language_rankings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `language_rankings` (
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
  UNIQUE KEY `uq_language_rankings_period_entity` (`year`, `period`, `market_entity_id`),
  KEY `idx_language_rankings_period_score` (`year`, `period`, `final_score`),
  KEY `idx_language_rankings_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. language_ranking_meta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `language_ranking_meta` (
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
  UNIQUE KEY `uq_language_ranking_meta_period` (`year`, `period`)
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

SELECT 'OK 01: tablas del ranking de lenguajes listas' AS resultado;
