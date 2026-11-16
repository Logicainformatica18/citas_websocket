-- =====================================================================
-- 14_limpieza_aplicar.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Aplica la limpieza preparada en language_job_limpieza, en lotes de 5 000 filas, cada
--   lote en su propia transacción:
--     1. copia la fila original a language_job_respaldo_limpieza;
--     2. accion = 'borrar'    → DELETE de language_job;
--        accion = 'reasignar' → UPDATE de language_id y market_entity_id a la entidad
--                               principal (UPDATE IGNORE: si la oferta ya la tenía, la
--                               fila queda como estaba y se informa al final);
--     3. marca el lote como aplicado.
--   NUNCA borra una fila sin haberla respaldado antes en la misma transacción.
--
-- REQUISITOS
--   13_limpieza_preparar.sql aplicado y la tabla de trabajo llena por
--   php artisan entities:clean-associations --type=languages
--   Ejecutar con el cliente mysql (usa DELIMITER) y cuando no corra jobs:run-all.
--
-- TIEMPO / BLOQUEOS
--   Bloqueos de fila breves por lote. Lenguajes en local: ~230 000 filas en
--   menos de 1 minuto.
--
-- RE-EJECUCIÓN
--   Idempotente: continúa con los lotes no aplicados (aplicado = 0).
--
-- DESPUÉS
--   php artisan ranking:refresh-languages
--
-- REVERSIÓN
--   15_limpieza_rollback.sql
-- =====================================================================

SELECT accion, COUNT(*) AS filas, SUM(aplicado = 0) AS pendientes FROM `language_job_limpieza` GROUP BY accion;

DROP PROCEDURE IF EXISTS `proc_limpieza_language_job`;
DELIMITER //
CREATE PROCEDURE `proc_limpieza_language_job`()
BEGIN
  DECLARE n INT DEFAULT 1;
  WHILE n > 0 DO
    START TRANSACTION;
    DROP TEMPORARY TABLE IF EXISTS `_lote_limpieza`;
    CREATE TEMPORARY TABLE `_lote_limpieza` (PRIMARY KEY (pivot_id))
      SELECT pivot_id FROM `language_job_limpieza` WHERE aplicado = 0 ORDER BY pivot_id LIMIT 5000;
    SET n = (SELECT COUNT(*) FROM `_lote_limpieza`);

    INSERT INTO `language_job_respaldo_limpieza`
          (id, `language_id`, market_entity_id, job_offer_id, created_at, updated_at,
           accion, nuevo_catalog_id, nuevo_market_entity_id, motivo, respaldado_en)
    SELECT p.id, p.`language_id`, p.market_entity_id, p.job_offer_id, p.created_at, p.updated_at,
           w.accion, w.nuevo_catalog_id, w.nuevo_market_entity_id, w.motivo, NOW()
      FROM `_lote_limpieza` l
      JOIN `language_job_limpieza` w ON w.pivot_id = l.pivot_id
      JOIN `language_job` p ON p.id = l.pivot_id
    ON DUPLICATE KEY UPDATE                        -- re-aplicación después de un rollback
           restaurado_en = NULL, respaldado_en = NOW(), accion = w.accion, motivo = w.motivo,
           nuevo_catalog_id = w.nuevo_catalog_id, nuevo_market_entity_id = w.nuevo_market_entity_id;

    DELETE p FROM `language_job` p
      JOIN `_lote_limpieza` l ON l.pivot_id = p.id
      JOIN `language_job_limpieza` w ON w.pivot_id = l.pivot_id
      JOIN `language_job_respaldo_limpieza` b ON b.id = p.id
     WHERE w.accion = 'borrar';

    UPDATE IGNORE `language_job` p
      JOIN `_lote_limpieza` l ON l.pivot_id = p.id
      JOIN `language_job_limpieza` w ON w.pivot_id = l.pivot_id
      JOIN `language_job_respaldo_limpieza` b ON b.id = p.id
       SET p.`language_id` = w.nuevo_catalog_id, p.market_entity_id = w.nuevo_market_entity_id, p.updated_at = NOW()
     WHERE w.accion = 'reasignar';

    UPDATE `language_job_limpieza` w JOIN `_lote_limpieza` l ON l.pivot_id = w.pivot_id SET w.aplicado = 1;
    COMMIT;
  END WHILE;
  DROP TEMPORARY TABLE IF EXISTS `_lote_limpieza`;
END //
DELIMITER ;

CALL `proc_limpieza_language_job`();
DROP PROCEDURE IF EXISTS `proc_limpieza_language_job`;

-- Verificación
SELECT accion, COUNT(*) AS filas, SUM(aplicado = 1) AS aplicadas FROM `language_job_limpieza` GROUP BY accion;
SELECT COUNT(*) AS filas_respaldadas FROM `language_job_respaldo_limpieza` WHERE restaurado_en IS NULL;
SELECT COUNT(*) AS borradas_que_siguen_en_el_pivote
  FROM `language_job_limpieza` w JOIN `language_job` p ON p.id = w.pivot_id WHERE w.accion = 'borrar';
SELECT COUNT(*) AS reasignaciones_no_aplicadas_por_duplicado
  FROM `language_job_limpieza` w JOIN `language_job` p ON p.id = w.pivot_id
 WHERE w.accion = 'reasignar' AND p.market_entity_id <> w.nuevo_market_entity_id;
SELECT 'OK 14: limpieza aplicada; ahora php artisan ranking:refresh-languages' AS resultado;
