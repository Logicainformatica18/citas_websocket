-- =====================================================================
-- 02_alineamiento_menciones.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Agrega job_offer_alignment.mentions: cuántas entidades (tecnologías,
--   lenguajes o metodologías, según source) de los cursos de la carrera
--   menciona la oferta. El ranking de ciudades de Demanda geográfica suma
--   menciones. Las filas existentes quedan con 1 hasta que
--   `php artisan jobs:build-alignment` (o indicators:refresh-all) las recalcule.
--   Equivale a la migración 2026_11_25_000002_add_mentions_to_job_offer_alignment.php
--
-- TIEMPO
--   Instantáneo (ALGORITHM=INSTANT de MySQL 8: no reconstruye ni bloquea la tabla).
--
-- RE-EJECUCIÓN
--   Idempotente: solo agrega la columna si falta. Sin DELETE, TRUNCATE ni DROP.
--   Reversión: 03_reversion.sql
-- =====================================================================

SET @tabla = (SELECT COUNT(*) FROM information_schema.tables
               WHERE table_schema = DATABASE() AND table_name = 'job_offer_alignment');
SET @col = (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'job_offer_alignment' AND column_name = 'mentions');
SET @sql = IF(@tabla > 0 AND @col = 0,
    'ALTER TABLE `job_offer_alignment` ADD COLUMN `mentions` SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `source`, ALGORITHM=INSTANT',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verificación: cada línea indica el valor esperado entre corchetes
SELECT CONCAT('job_offer_alignment.mentions existe [1]: ', COUNT(*)) AS verificacion
  FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'job_offer_alignment' AND column_name = 'mentions';
