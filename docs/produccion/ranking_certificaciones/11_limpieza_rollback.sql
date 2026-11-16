-- =====================================================================
-- 11_limpieza_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 10_limpieza_aplicar.sql desde certification_job_respaldo_limpieza:
--     - vuelve a insertar las filas borradas (con su id original);
--     - devuelve las reasignadas a su certification_id y market_entity_id originales.
--   Marca restaurado_en en el respaldo (no lo borra) y pone aplicado = 0 en la
--   tabla de trabajo. Si una oferta se borró después, su fila no se puede
--   restaurar (INSERT IGNORE la omite) y se informa.
--
-- TIEMPO / BLOQUEOS
--   Lenguajes en local: menos de 1 minuto. Bloqueos de fila.
--
-- RE-EJECUCIÓN
--   Idempotente (solo filas con restaurado_en vacío).
--
-- DESPUÉS
--   php artisan ranking:refresh-certifications
-- =====================================================================

SET @existe = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'certification_job_respaldo_limpieza');
SELECT IF(@existe > 0, 'Respaldo encontrado', 'Sin respaldo: nada que revertir') AS resultado;

CREATE TABLE IF NOT EXISTS `certification_job_respaldo_limpieza` (`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY, `certification_id` BIGINT UNSIGNED NULL,
  `market_entity_id` BIGINT UNSIGNED NULL, `job_offer_id` BIGINT UNSIGNED NOT NULL, `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL, `accion` ENUM('borrar','reasignar') NOT NULL, `nuevo_catalog_id` BIGINT UNSIGNED NULL,
  `nuevo_market_entity_id` BIGINT UNSIGNED NULL, `motivo` VARCHAR(100) NULL,
  `respaldado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `restaurado_en` TIMESTAMP NULL)
  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT IGNORE INTO `certification_job` (id, `certification_id`, market_entity_id, job_offer_id, created_at, updated_at)
SELECT b.id, b.`certification_id`, b.market_entity_id, b.job_offer_id, b.created_at, b.updated_at
  FROM `certification_job_respaldo_limpieza` b
 WHERE b.accion = 'borrar' AND b.restaurado_en IS NULL;
SELECT CONCAT('Filas borradas restauradas: ', ROW_COUNT()) AS resultado;

UPDATE IGNORE `certification_job` p
  JOIN `certification_job_respaldo_limpieza` b ON b.id = p.id AND b.accion = 'reasignar' AND b.restaurado_en IS NULL
   SET p.`certification_id` = b.`certification_id`, p.market_entity_id = b.market_entity_id, p.updated_at = NOW();
SELECT CONCAT('Filas reasignadas devueltas: ', ROW_COUNT()) AS resultado;

SELECT COUNT(*) AS no_restauradas_oferta_borrada
  FROM `certification_job_respaldo_limpieza` b LEFT JOIN `certification_job` p ON p.id = b.id
 WHERE b.restaurado_en IS NULL AND p.id IS NULL;

UPDATE `certification_job_respaldo_limpieza` b JOIN `certification_job` p ON p.id = b.id SET b.restaurado_en = NOW() WHERE b.restaurado_en IS NULL;

SET @hay_trabajo = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'certification_job_limpieza');
SET @sql = IF(@hay_trabajo > 0, 'UPDATE `certification_job_limpieza` SET aplicado = 0', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT 'OK 11: limpieza revertida; ahora php artisan ranking:refresh-certifications' AS resultado;
