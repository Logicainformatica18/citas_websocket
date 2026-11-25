-- =====================================================================
-- 03_reversion.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Revierte 01, 02 y 04: elimina indicator_facts e indicator_fact_runs (datos
--   derivados; se regeneran con indicators:refresh-all), la columna
--   job_offer_alignment.mentions y los índices idx_jo_region_country e
--   idx_jo_country_city. Desplegar antes el código anterior: con el
--   código nuevo y sin las tablas, las páginas calculan en vivo (más lentas).
--   Equivale al down() de las tres migraciones 2026_11_25_*.
--
-- TIEMPO
--   Menos de 1 s.
--
-- RE-EJECUCIÓN
--   Idempotente.
-- =====================================================================

DROP TABLE IF EXISTS `indicator_fact_runs`;
DROP TABLE IF EXISTS `indicator_facts`;

SET @col = (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'job_offer_alignment' AND column_name = 'mentions');
SET @sql = IF(@col > 0, 'ALTER TABLE `job_offer_alignment` DROP COLUMN `mentions`, ALGORITHM=INSTANT', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_region_country';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_country_city';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'OK: tablas resumen, columna e índices de los indicadores revertidos' AS resultado;
