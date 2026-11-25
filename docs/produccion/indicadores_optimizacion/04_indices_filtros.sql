-- =====================================================================
-- 04_indices_filtros.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Agrega a job_offers dos índices para los autocompletados de filtros que
--   siguen en vivo (Modalidad: regiones, países de una región, ciudades de un
--   país): idx_jo_region_country (region, country) e idx_jo_country_city
--   (country, city).
--   Equivale a la migración 2026_11_25_000003_add_filter_indexes_to_job_offers.php
--
-- TIEMPO
--   ~5-15 s por índice con ~400 000 ofertas (ALGORITHM=INPLACE, LOCK=NONE:
--   no bloquea lecturas ni escrituras).
--
-- RE-EJECUCIÓN
--   Idempotente: solo crea los que faltan. Sin DELETE, TRUNCATE ni DROP.
--   Reversión: 03_reversion.sql
-- =====================================================================

SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = 'idx_jo_region_country');
SET @sql = IF(@existe = 0,
    'ALTER TABLE `job_offers` ADD INDEX `idx_jo_region_country` (`region`, `country`), ALGORITHM=INPLACE, LOCK=NONE',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = 'idx_jo_country_city');
SET @sql = IF(@existe = 0,
    'ALTER TABLE `job_offers` ADD INDEX `idx_jo_country_city` (`country`, `city`), ALGORITHM=INPLACE, LOCK=NONE',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verificación: cada línea indica el valor esperado entre corchetes
SELECT CONCAT('índices de filtros presentes [2]: ', COUNT(DISTINCT index_name)) AS verificacion
  FROM information_schema.statistics
 WHERE table_schema = DATABASE() AND table_name = 'job_offers'
   AND index_name IN ('idx_jo_region_country', 'idx_jo_country_city');
