-- =====================================================================
-- 09_ranking_technologies_carreras_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Revierte 08: borra de market_entity_career SOLO los pares que agregó
--   (registrados en market_entity_career_migracion_cme) y elimina esa tabla.
--   No toca career_market_entity ni los pares que ya existían.
--
--   IMPORTANTE: despliega antes el código que vuelve a leer career_market_entity
--   para Tecnologías (config/entity_rankings.php → technologies.career_table).
--
-- TIEMPO / BLOQUEOS: menos de 1 s.   RE-EJECUCIÓN: idempotente.
-- =====================================================================

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_career_migracion_cme');

SET @sql = IF(@existe > 0,
  'DELETE m FROM market_entity_career m JOIN market_entity_career_migracion_cme t ON t.market_entity_id = m.market_entity_id AND t.career_id = m.career_id',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; SET @borrados = ROW_COUNT(); DEALLOCATE PREPARE stmt;
SELECT CONCAT('Pares borrados de market_entity_career: ', IF(@existe > 0, @borrados, 0)) AS resultado;

DROP TABLE IF EXISTS `market_entity_career_migracion_cme`;

SELECT 'OK 09: migración de carreras revertida' AS resultado;
