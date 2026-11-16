-- =====================================================================
-- 07_ranking_technologies_entidades_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Revierte 06: reactiva las entidades de tecnologías cuyo inactive_reason
--   empieza con "[Tecnologías 2026-11]".
--   No toca las desactivadas por otros motivos (p. ej. desde la administración).
--
-- TIEMPO / BLOQUEOS: menos de 1 s.   RE-EJECUCIÓN: idempotente.
-- DESPUÉS: php artisan ranking:refresh-technologies
-- =====================================================================

UPDATE market_entities
   SET is_active = 1, inactive_reason = NULL, inactive_at = NULL
 WHERE entity_type = 'technology' AND is_active = 0
   AND inactive_reason LIKE '[Tecnologías 2026-11]%';
SELECT CONCAT('Entidades reactivadas: ', ROW_COUNT()) AS resultado;

SELECT 'OK 07: reclasificación de entidades de tecnologías revertida' AS resultado;
