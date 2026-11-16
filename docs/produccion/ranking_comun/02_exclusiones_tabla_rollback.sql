-- =====================================================================
-- 02_exclusiones_tabla_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Elimina entity_association_exclusions (datos derivados: los regenera
--   php artisan entities:exclude-associations) y entity_association_exclusion_settings.
--   Las tablas pivote no se tocan.
--   Con el código nuevo desplegado, el ranking sigue funcionando sin la tabla
--   (el filtro se omite si no existe); después: php artisan ranking:refresh-*.
--
-- TIEMPO / RE-EJECUCIÓN
--   Menos de 1 s. Idempotente.
-- =====================================================================

DROP TABLE IF EXISTS `entity_association_exclusions`;
DROP TABLE IF EXISTS `entity_association_exclusion_settings`;
SELECT 'OK: tablas de exclusiones eliminadas (si existían)' AS resultado;
