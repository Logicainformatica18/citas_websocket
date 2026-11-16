-- =====================================================================
-- 11_matcher_aliases_tecnologias_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 10: borra las reglas sembradas de tecnologías
--   (notes = '[seed tecnologias]') y vuelve a desactivar Transformers.
--   NO elimina market_entity_aliases (la comparten Lenguajes y Certificaciones;
--   para eliminarla, ranking_lenguajes/12).
--
-- TIEMPO / RE-EJECUCIÓN
--   Menos de 1 s. Idempotente.
-- =====================================================================

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_aliases');
SET @sql = IF(@existe > 0,
  'DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id WHERE me.entity_type = ''technology'' AND a.notes = ''[seed tecnologias]''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'Reglas sembradas de tecnologías borradas' AS resultado;

UPDATE market_entities
   SET is_active = 0, inactive_at = NOW(),
       inactive_reason = '[Tecnologías 2026-11] Ambiguo: se reactiva en la Fase 3 con alias estrictos'
 WHERE entity_type = 'technology' AND slug = 'transformers' AND is_active = 1;
SELECT CONCAT('Transformers desactivado: ', ROW_COUNT()) AS resultado;

SELECT 'OK 11: reversión de 10 terminada' AS resultado;
