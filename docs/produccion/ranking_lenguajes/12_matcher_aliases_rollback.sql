-- =====================================================================
-- 12_matcher_aliases_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 11_matcher_aliases.sql: elimina market_entity_aliases.
--   ATENCIÓN: se pierden también las reglas creadas a mano (la consulta del
--   paso 0 las deja en pantalla antes de borrar).
--   Desplegar antes el código anterior a la Fase B (el matcher sin la tabla
--   usa solo el nombre de cada entidad, así que no falla, pero pierde precisión).
--
-- REQUISITOS / TIEMPO
--   MySQL 8.0+. Menos de 1 s.
--
-- RE-EJECUCIÓN
--   Idempotente.
-- =====================================================================

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_aliases');
SET @sql = IF(@existe > 0,
  'SELECT a.id, me.entity_type, me.name, a.kind, a.pattern, a.case_sensitive, a.is_weak, a.window_chars, a.notes FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id WHERE a.notes IS NULL OR a.notes NOT LIKE ''[seed %'' ORDER BY me.entity_type, me.name',
  'SELECT ''market_entity_aliases no existe'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `market_entity_aliases`;
SELECT 'OK 12: market_entity_aliases eliminada (si existía)' AS resultado;
