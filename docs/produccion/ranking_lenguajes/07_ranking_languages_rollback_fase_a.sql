-- =====================================================================
-- 07_ranking_languages_rollback_fase_a.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 05 y 06:
--     - Elimina language_ranking_weekly y language_ranking_weekly_meta
--       (datos calculados; se regeneran con ranking:refresh-languages).
--     - Elimina el índice idx_market_entities_type_active y las columnas
--       is_active, inactive_reason, inactive_at de market_entities.
--       ATENCIÓN: se pierde qué entidades estaban desactivadas y por qué
--       (IDC, OECD y las que se desactiven desde la administración).
--       La consulta del paso 0 deja ese listado en pantalla antes de borrar.
--   No toca: language_evolution_cache, languages.enabled, ni 01-04.
--
--   IMPORTANTE: despliega antes el código anterior a la Fase A; el código
--   nuevo usa market_entities.is_active y fallaría sin la columna.
--
-- REQUISITOS
--   MySQL 8.0 o superior.
--
-- TIEMPO ESTIMADO / BLOQUEOS
--   1-2 segundos. DROP COLUMN en MySQL 8.0.29+ es INSTANT; en versiones
--   anteriores reconstruye market_entities (~600 filas, instantáneo).
--
-- RE-EJECUCIÓN
--   Idempotente.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. Respaldo en pantalla de las entidades desactivadas (si la columna existe)
-- ---------------------------------------------------------------------
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = 'is_active');
SET @sql = IF(@existe > 0,
  'SELECT id, entity_type, name, slug, inactive_reason, inactive_at FROM market_entities WHERE is_active = 0 ORDER BY entity_type, name',
  'SELECT ''market_entities.is_active no existe'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 1. Tablas de evolución
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `language_ranking_weekly_meta`;
DROP TABLE IF EXISTS `language_ranking_weekly`;
SELECT 'Tablas language_ranking_weekly(_meta) eliminadas (si existían)' AS resultado;

-- ---------------------------------------------------------------------
-- 2. Índice y columnas de market_entities
-- ---------------------------------------------------------------------
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND index_name = 'idx_market_entities_type_active');
SET @sql = IF(@existe > 0, 'ALTER TABLE `market_entities` DROP INDEX `idx_market_entities_type_active`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('idx_market_entities_type_active: ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @col = 'inactive_at';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = @col);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `market_entities` DROP COLUMN `', @col, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe > 0, 'ELIMINADA', 'NO EXISTE')) AS resultado;

SET @col = 'inactive_reason';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = @col);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `market_entities` DROP COLUMN `', @col, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe > 0, 'ELIMINADA', 'NO EXISTE')) AS resultado;

SET @col = 'is_active';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = @col);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `market_entities` DROP COLUMN `', @col, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe > 0, 'ELIMINADA', 'NO EXISTE')) AS resultado;

SELECT 'OK 07: rollback de la Fase A terminado' AS resultado;
