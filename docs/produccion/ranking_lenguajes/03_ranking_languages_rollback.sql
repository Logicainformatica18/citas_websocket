-- =====================================================================
-- 03_ranking_languages_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 01 y 02:
--     - Elimina las tablas language_rankings y language_ranking_meta
--       (solo contienen datos calculados; se regeneran con
--       `php artisan ranking:refresh-languages`).
--     - Elimina SOLO los índices creados por 02, buscándolos por nombre:
--         language_job.idx_lj_job_entity
--         job_offers.idx_jo_created_at
--         job_offers.idx_jo_source_created
--         scraper_runs.idx_scraper_runs_entity_finished
--
--   NO elimina:
--     - job_offers.idx_jo_published_at: ya existía antes de este cambio en
--       la base de desarrollo y lo usan otras consultas. Si en producción lo
--       creó el script 02 y se quiere quitar, ver el bloque comentado al final.
--     - cache, cache_locks, jobs, failed_jobs: son tablas generales de
--       Laravel que usa el resto de la aplicación.
--
--   IMPORTANTE: antes de ejecutar este script, despliega el código anterior
--   o la página del ranking fallará al no encontrar language_rankings.
--
-- REQUISITOS
--   MySQL 8.0 o superior. Ejecutar conectado a la base del observatorio.
--
-- TIEMPO ESTIMADO
--   ~7 segundos en local (403k filas en job_offers). Eliminar un índice
--   secundario en InnoDB no reconstruye la tabla, pero job_offers tiene un
--   índice FULLTEXT y la operación tarda algo más.
--
-- BLOQUEOS
--   DROP INDEX con ALGORITHM=INPLACE, LOCK=NONE: solo un bloqueo de
--   metadatos muy breve. Espera si hay transacciones largas en la tabla.
--
-- RE-EJECUCIÓN
--   Idempotente: lo que ya no existe se informa como "NO EXISTE".
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Tablas del ranking
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `language_ranking_meta`;
DROP TABLE IF EXISTS `language_rankings`;
SELECT 'Tablas language_rankings y language_ranking_meta eliminadas (si existían)' AS resultado;

-- ---------------------------------------------------------------------
-- 2. Índices (por nombre)
-- ---------------------------------------------------------------------
SET @tbl = 'language_job', @idx = 'idx_lj_job_entity';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @tbl = 'job_offers', @idx = 'idx_jo_created_at';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @tbl = 'job_offers', @idx = 'idx_jo_source_created';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @tbl = 'scraper_runs', @idx = 'idx_scraper_runs_entity_finished';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. (Opcional) job_offers.idx_jo_published_at
--    Descomentar SOLO si el script 02 lo creó en esta base (su salida decía
--    "Paso 2 - job_offers.idx_jo_published_at: CREADO").
-- ---------------------------------------------------------------------
-- SET @tbl = 'job_offers', @idx = 'idx_jo_published_at';
-- SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
--                 WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
-- SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
-- PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SELECT 'OK 03: rollback terminado' AS resultado;
