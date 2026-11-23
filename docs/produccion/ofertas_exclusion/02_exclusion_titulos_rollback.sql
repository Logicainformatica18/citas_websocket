-- =====================================================================
-- 02_exclusion_titulos_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Revierte 01_exclusion_titulos.sql: quita los 8 índices y la columna
--   job_offers.title_exclusion_id y elimina job_title_exclusion_keywords,
--   job_offer_processes y job_title_exclusion_candidates.
--
--   job_offer_deletion_backups SOLO se elimina si está vacía: si alguien usó
--   "Eliminar definitivamente", ahí está el único respaldo de esas ofertas
--   (restaurarlas antes con php artisan job-offers:restore-deleted <proceso>).
--
--   Las ofertas no se borran ni se modifican (solo se pierde la marca de
--   exclusión). Desplegar antes el código anterior, o con el código nuevo
--   el ranking sigue funcionando: el filtro se omite si falta la columna.
--   Después: php artisan ranking:refresh-languages / -technologies / -certifications
--
-- TIEMPO
--   Quitar la columna de job_offers puede reconstruir la tabla (~1-2 min con
--   ~400 000 ofertas). El resto, menos de 1 s.
--
-- RE-EJECUCIÓN
--   Idempotente.
-- =====================================================================

SET @idx = 'idx_jo_company';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_city';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_modality';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_job_type';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_remote_type';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_workload';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_pub_excl';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_jo_title_excl';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `job_offers` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND column_name = 'title_exclusion_id');
SET @sql = IF(@existe > 0, 'ALTER TABLE `job_offers` DROP COLUMN `title_exclusion_id`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `job_title_exclusion_candidates`;
DROP TABLE IF EXISTS `job_offer_processes`;
DROP TABLE IF EXISTS `job_title_exclusion_keywords`;

-- El respaldo de eliminaciones solo se quita si está vacío
SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'job_offer_deletion_backups');
SET @filas = 0;
SET @sql = IF(@existe > 0, 'SELECT COUNT(*) INTO @filas FROM `job_offer_deletion_backups`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@existe > 0 AND @filas = 0, 'DROP TABLE `job_offer_deletion_backups`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CONCAT('OK: exclusión por título revertida',
              IF(@filas > 0, CONCAT('. job_offer_deletion_backups se conserva (', @filas, ' filas de respaldo)'), '')) AS resultado;
