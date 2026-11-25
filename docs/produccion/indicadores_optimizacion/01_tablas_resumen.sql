-- =====================================================================
-- 01_tablas_resumen.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Crea las tablas resumen de los indicadores de análisis (todos menos los
--   rankings de Lenguajes, Tecnologías y Certificaciones):
--     indicator_facts      filas agregadas por indicador, año, periodo y las
--                          dimensiones de cada página (carrera, país, ciudad…)
--     indicator_fact_runs  último cálculo de cada indicador y periodo
--   Las llena `php artisan indicators:refresh-all`; las páginas solo leen.
--   Equivale a la migración 2026_11_25_000001_create_indicator_facts.php
--   (en producción se usa la migración; este script es la alternativa en SQL).
--
-- TIEMPO
--   Menos de 1 s (tablas nuevas y vacías).
--
-- RE-EJECUCIÓN
--   Idempotente (CREATE TABLE IF NOT EXISTS). Sin DELETE, TRUNCATE ni DROP.
--   Reversión: 03_reversion.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `indicator_facts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `indicator` VARCHAR(40) NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `period` CHAR(2) NOT NULL,
  `month` TINYINT UNSIGNED NULL,
  `career_id` BIGINT UNSIGNED NULL,
  `seniority` VARCHAR(30) NULL,
  `modality` VARCHAR(40) NULL,
  `region` VARCHAR(100) NULL,
  `country` VARCHAR(150) NULL,
  `city` VARCHAR(150) NULL,
  `source` VARCHAR(80) NULL,
  `company` VARCHAR(255) NULL,
  `n` INT UNSIGNED NOT NULL DEFAULT 0,
  `n2` INT UNSIGNED NULL,
  `lat_sum` DOUBLE NULL,
  `lng_sum` DOUBLE NULL,
  `calculated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_if_ind_period` (`indicator`, `year`, `period`, `career_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `indicator_fact_runs` (
  `indicator` VARCHAR(40) NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `period` CHAR(2) NOT NULL,
  `calculated_at` DATETIME NOT NULL,
  `rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `peak_mb` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`indicator`, `year`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación: cada línea indica el valor esperado entre corchetes
SELECT CONCAT('tablas resumen presentes [2]: ', COUNT(*)) AS verificacion
  FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name IN ('indicator_facts', 'indicator_fact_runs');
