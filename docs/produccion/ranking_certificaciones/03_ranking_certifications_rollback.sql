-- =====================================================================
-- 03_ranking_certifications_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 01 y 02:
--     - Elimina certification_rankings, certification_ranking_meta,
--       certification_ranking_weekly y certification_ranking_weekly_meta (solo datos
--       calculados; se regeneran con ranking:refresh-certifications).
--     - Elimina el índice certification_job.idx_cj_job_entity.
--   NO toca: los índices de job_offers / scraper_runs (los comparte con el
--   ranking de Lenguajes; su rollback está en ranking_lenguajes/03), ni
--   certification_evolution_cache, ni cache / cache_locks / jobs / failed_jobs.
--
--   IMPORTANTE: despliega antes el código anterior; el nuevo lee estas tablas.
--
-- REQUISITOS
--   MySQL 8.0 o superior.
--
-- TIEMPO ESTIMADO / BLOQUEOS
--   1-3 s. DROP INDEX en línea (LOCK=NONE): bloqueo de metadatos muy breve.
--
-- RE-EJECUCIÓN
--   Idempotente.
-- =====================================================================

DROP TABLE IF EXISTS `certification_ranking_weekly_meta`;
DROP TABLE IF EXISTS `certification_ranking_weekly`;
DROP TABLE IF EXISTS `certification_ranking_meta`;
DROP TABLE IF EXISTS `certification_rankings`;
SELECT 'Tablas del ranking de certificaciones eliminadas (si existían)' AS resultado;

SET @tbl = 'certification_job', @idx = 'idx_cj_job_entity';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SELECT 'OK 03: rollback del ranking de certificaciones terminado' AS resultado;
