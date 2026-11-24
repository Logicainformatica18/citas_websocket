-- =====================================================================
-- 04_indices_indicadores_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Revierte 03_indices_indicadores.sql: quita idx_jo_source_created_excl e
--   idx_jo_created_excl y vuelve VISIBLE idx_jo_title_excl. No toca datos.
--   Los indicadores siguen funcionando igual (solo más lentos).
--
-- TIEMPO
--   Menos de 1 s por índice.
--
-- RE-EJECUCIÓN
--   Idempotente.
-- =====================================================================

SET @idx = 'idx_jo_source_created_excl';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_created_excl';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = 'idx_jo_title_excl');
SET @sql = IF(@existe > 0, 'ALTER TABLE `job_offers` ALTER INDEX `idx_jo_title_excl` VISIBLE', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'OK: índices de los indicadores revertidos' AS resultado;
