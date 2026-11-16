-- =====================================================================
-- 06_ranking_certifications_entidades_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 05 con _respaldo_certificaciones_2026_11:
--     - reactiva los duplicados;
--     - borra las asociaciones copiadas desde technology_job;
--     - deshabilita (no borra) las filas de certifications creadas y la
--       entidad CompTIA A+ (pueden tener ofertas asociadas después).
--   Al final elimina la tabla de respaldo.
--
-- TIEMPO / RE-EJECUCIÓN
--   Menos de 2 s. Idempotente.
--
-- DESPUÉS
--   php artisan ranking:refresh-certifications
-- =====================================================================

CREATE TABLE IF NOT EXISTS `_respaldo_certificaciones_2026_11` (
  `tabla` VARCHAR(64) NOT NULL, `fila_id` BIGINT UNSIGNED NOT NULL, `columna` VARCHAR(64) NOT NULL,
  `valor_anterior` VARCHAR(255) NULL, `respaldado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabla`, `fila_id`, `columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

UPDATE market_entities me
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'is_active'
   SET me.is_active = r.valor_anterior, me.inactive_reason = NULL, me.inactive_at = NULL
 WHERE me.is_active = 0 AND me.inactive_reason LIKE '[Certificaciones 2026-11]%';
SELECT CONCAT('Duplicados reactivados: ', ROW_COUNT()) AS resultado;

DELETE FROM certification_job WHERE certification_name_raw = '[migrado de technology_job 2026-11]';
SELECT CONCAT('Asociaciones copiadas borradas: ', ROW_COUNT()) AS resultado;

UPDATE certifications c
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'certifications' AND r.fila_id = c.id AND r.columna = 'creada'
   SET c.enabled = 0, c.updated_at = NOW();
SELECT CONCAT('Filas de certifications deshabilitadas: ', ROW_COUNT()) AS resultado;

UPDATE certifications c
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'certifications' AND r.fila_id = c.id AND r.columna = 'enabled'
   SET c.enabled = r.valor_anterior, c.updated_at = NOW();
SELECT CONCAT('Filas de certifications devueltas a su estado anterior: ', ROW_COUNT()) AS resultado;

UPDATE certifications c
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'certifications' AND r.fila_id = c.id AND r.columna = 'keywords'
   SET c.keywords = NULL, c.updated_at = NOW();
SELECT CONCAT('keywords devueltas a NULL: ', ROW_COUNT()) AS resultado;

UPDATE market_entities me
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'creada'
   SET me.is_active = 0, me.inactive_at = NOW(), me.inactive_reason = 'Revertido por 06_ranking_certifications_entidades_rollback'
 WHERE me.is_active = 1;
SELECT CONCAT('CompTIA A+ desactivada: ', ROW_COUNT()) AS resultado;

COMMIT;

DROP TABLE IF EXISTS `_respaldo_certificaciones_2026_11`;
SELECT 'OK 06: reversión de 05 terminada' AS resultado;
