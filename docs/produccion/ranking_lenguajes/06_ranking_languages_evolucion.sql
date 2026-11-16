-- =====================================================================
-- 06_ranking_languages_evolucion.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Crea las tablas de resumen del modal "Evolución" del Ranking de
--   Lenguajes. Las RECONSTRUYE `php artisan ranking:refresh-languages` en
--   cada ejecución. Reemplazan a language_evolution_cache (que NO se toca).
--
--     language_ranking_weekly       una fila por ventana y lenguaje
--     language_ranking_weekly_meta  una fila por ventana
--
--   Cada ventana es una FOTO del ranking semestral en su fecha de corte
--   (último día de la ventana, o hoy si está en curso): vacantes y reportes
--   ACUMULADOS desde el inicio del semestre del corte hasta el corte.
--   final_score y position se calculan al leer con los pesos vigentes; las
--   columnas final_score/position guardadas son solo referencia.
--
--   Ventanas (period_type):
--     weekly    semana ISO, lunes a domingo      period_key = '2026-W45'
--     biweekly  días 1-15 y 16-fin de mes        period_key = '2026-10-Q2'
--     monthly   mes calendario                   period_key = '2026-10'
--
--   VERSIÓN ANTERIOR: si las tablas existen con la estructura previa (sin la
--   columna cutoff_date), se ELIMINAN y se recrean. Solo contienen datos
--   calculados: se regeneran con ranking:refresh-languages.
--
-- REQUISITOS
--   MySQL 8.0 o superior (la lectura usa ROW_NUMBER() OVER).
--
-- TIEMPO ESTIMADO / BLOQUEOS
--   Menos de 1 segundo. Tablas nuevas; no bloquea tablas existentes.
--
-- RE-EJECUCIÓN
--   Idempotente (CREATE TABLE IF NOT EXISTS; Note 1050 si ya existen).
--
-- REVERSIÓN
--   07_ranking_languages_rollback_fase_a.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. Estructura anterior (sin cutoff_date): eliminar para recrear
-- ---------------------------------------------------------------------
SET @vieja = (SELECT COUNT(*) FROM information_schema.tables t
               WHERE t.table_schema = DATABASE() AND t.table_name = 'language_ranking_weekly')
           - (SELECT COUNT(*) FROM information_schema.columns c
               WHERE c.table_schema = DATABASE() AND c.table_name = 'language_ranking_weekly' AND c.column_name = 'cutoff_date');
SET @sql = IF(@vieja > 0, 'DROP TABLE `language_ranking_weekly`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('language_ranking_weekly: ', IF(@vieja > 0, 'estructura anterior ELIMINADA, se recrea', 'sin versión anterior')) AS resultado;

SET @vieja = (SELECT COUNT(*) FROM information_schema.tables t
               WHERE t.table_schema = DATABASE() AND t.table_name = 'language_ranking_weekly_meta')
           - (SELECT COUNT(*) FROM information_schema.columns c
               WHERE c.table_schema = DATABASE() AND c.table_name = 'language_ranking_weekly_meta' AND c.column_name = 'cutoff_date');
SET @sql = IF(@vieja > 0, 'DROP TABLE `language_ranking_weekly_meta`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('language_ranking_weekly_meta: ', IF(@vieja > 0, 'estructura anterior ELIMINADA, se recrea', 'sin versión anterior')) AS resultado;

-- ---------------------------------------------------------------------
-- 1. Fotos por ventana y lenguaje
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `language_ranking_weekly` (
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
  UNIQUE KEY `uq_lrw_window_entity` (`period_type`, `period_key`, `market_entity_id`),
  KEY `idx_lrw_type_year` (`period_type`, `year`),
  KEY `idx_lrw_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Una fila por ventana
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `language_ranking_weekly_meta` (
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
  `languages_count`   INT UNSIGNED      NOT NULL DEFAULT 0,
  `calculated_at`     TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lrwm_window` (`period_type`, `period_key`),
  KEY `idx_lrwm_type_year_start` (`period_type`, `year`, `start_date`),
  KEY `idx_lrwm_type_start` (`period_type`, `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK 06: tablas de evolución listas (fotos por fecha de corte)' AS resultado;
