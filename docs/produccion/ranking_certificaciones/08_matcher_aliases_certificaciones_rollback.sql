-- =====================================================================
-- 08_matcher_aliases_certificaciones_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 07: borra las reglas sembradas de certificaciones
--   (notes = '[seed certificaciones]').
--   NO elimina market_entity_aliases (la comparten Lenguajes y Tecnologías;
--   para eliminarla, ranking_lenguajes/12).
--
-- TIEMPO / RE-EJECUCIÓN
--   Menos de 1 s. Idempotente.
-- =====================================================================

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_aliases');
SET @sql = IF(@existe > 0,
  'DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id WHERE me.entity_type = ''certification'' AND a.notes = ''[seed certificaciones]''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'Reglas sembradas de certificaciones borradas' AS resultado;

SELECT 'OK 08: reversión de 07 terminada' AS resultado;
