-- =====================================================================
-- 03_ranking_technologies_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 01 y 02:
--     - Elimina technology_rankings, technology_ranking_meta,
--       technology_ranking_weekly y technology_ranking_weekly_meta (solo datos
--       calculados; se regeneran con ranking:refresh-technologies).
--     - Elimina el índice technology_job.idx_tj_job_entity.
--   NO toca: los índices de job_offers / scraper_runs (los comparte con el
--   ranking de Lenguajes; su rollback está en ranking_lenguajes/03), ni
--   technology_evolution_cache, ni cache / cache_locks / jobs / failed_jobs.
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

DROP TABLE IF EXISTS `technology_ranking_weekly_meta`;
DROP TABLE IF EXISTS `technology_ranking_weekly`;
DROP TABLE IF EXISTS `technology_ranking_meta`;
DROP TABLE IF EXISTS `technology_rankings`;
SELECT 'Tablas del ranking de tecnologías eliminadas (si existían)' AS resultado;

SET @tbl = 'technology_job', @idx = 'idx_tj_job_entity';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SELECT 'OK 03: rollback del ranking de tecnologías terminado' AS resultado;
