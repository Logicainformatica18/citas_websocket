-- =====================================================================
-- Observatorio · 01_integracion_silabos_down.sql
-- Revierte exactamente 01_integracion_silabos_up.sql (MySQL 8.0.16+).
--
-- Ejecutar con el cliente mysql SIN --force:
--   mysql -h HOST -P PUERTO -u USUARIO -p BASE < 01_integracion_silabos_down.sql
--
-- NO BORRA FILAS. Si existen sílabos recibidos de silabos_v2, se detiene
-- antes de cambiar nada (paso 0): esas filas no caben en el esquema
-- anterior (no tienen PDF y usan estados nuevos). Decidir qué hacer con
-- ellas es una acción aparte y manual.
--
-- Sí se pierden, al quitar las columnas, los códigos de courses.code y
-- los enlaces syllabus.course_id. Ver el respaldo opcional del paso 0.
-- =====================================================================

SET NAMES utf8mb4;

SET @sql_mode_anterior = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';


-- ---------------------------------------------------------------------
-- 0. Verificaciones previas (no cambian nada).
-- ---------------------------------------------------------------------

-- 0.a Filas que impiden volver al esquema anterior.
SELECT COUNT(*) AS silabos_que_impiden_revertir
FROM `syllabus`
WHERE `source` <> 'upload'
   OR `status` IN ('superseded', 'ignored', 'pending_link')
   OR `filename` IS NULL
   OR `path` IS NULL;

-- 0.b Si 0.a es mayor que 0, este bloque detiene el script con el error
--     "Check constraint 'hay_silabos_de_la_integracion' is violated".
--     La tabla es temporal: desaparece al cerrar la sesión.
CREATE TEMPORARY TABLE `_verificacion_down` (
    `n` INT NOT NULL,
    CONSTRAINT `hay_silabos_de_la_integracion` CHECK (`n` = 0)
);
INSERT INTO `_verificacion_down` (`n`)
SELECT COUNT(*)
FROM `syllabus`
WHERE `source` <> 'upload'
   OR `status` IN ('superseded', 'ignored', 'pending_link')
   OR `filename` IS NULL
   OR `path` IS NULL;
DROP TEMPORARY TABLE `_verificacion_down`;

-- 0.c Códigos de curso que se perderán. Para conservarlos, guardar antes
--     esta consulta (o descomentar el respaldo de abajo).
SELECT `id`, `name`, `code` FROM `courses` WHERE `code` IS NOT NULL ORDER BY `id`;
-- CREATE TABLE `courses_code_respaldo` AS SELECT `id`, `code` FROM `courses` WHERE `code` IS NOT NULL;


-- ---------------------------------------------------------------------
-- 1. courses: quitar el código (inverso del paso 4 del up).
-- ---------------------------------------------------------------------

ALTER TABLE `courses`
    DROP INDEX `courses_code_unique`,
    DROP COLUMN `code`;


-- ---------------------------------------------------------------------
-- 2. syllabus: quitar clave foránea e índices (inverso del paso 3).
-- ---------------------------------------------------------------------

ALTER TABLE `syllabus`
    DROP FOREIGN KEY `syllabus_course_id_foreign`,
    DROP INDEX `syllabus_external_unique`,
    DROP INDEX `syllabus_family_index`,
    DROP INDEX `syllabus_superseded_at_index`;


-- ---------------------------------------------------------------------
-- 3. syllabus: quitar las columnas nuevas (inverso del paso 2).
--    Al quitar course_id también desaparece el índice que MySQL creó
--    para la clave foránea.
-- ---------------------------------------------------------------------

ALTER TABLE `syllabus`
    DROP COLUMN `source`,
    DROP COLUMN `external_model`,
    DROP COLUMN `external_id`,
    DROP COLUMN `codigo_familia`,
    DROP COLUMN `external_version`,
    DROP COLUMN `periodo`,
    DROP COLUMN `periodo_orden`,
    DROP COLUMN `tipo_plantilla`,
    DROP COLUMN `course_code`,
    DROP COLUMN `course_name`,
    DROP COLUMN `course_id`,
    DROP COLUMN `careers`,
    DROP COLUMN `approved_at`,
    DROP COLUMN `approved_at_source`,
    DROP COLUMN `content`,
    DROP COLUMN `content_hash`,
    DROP COLUMN `superseded_at`,
    DROP COLUMN `superseded_by`;


-- ---------------------------------------------------------------------
-- 4. syllabus: devolver las columnas existentes a su definición original
--    (inverso del paso 1). El paso 0 garantiza que no hay filas que lo
--    impidan; en modo estricto, si las hubiera, fallaría sin truncar.
-- ---------------------------------------------------------------------

ALTER TABLE `syllabus`
    MODIFY `filename` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY `path` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY `status` ENUM('pending', 'processing', 'processed', 'failed')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending';


-- ---------------------------------------------------------------------
-- 5. Quitar el registro de las migraciones (inverso del paso 5).
-- ---------------------------------------------------------------------

DELETE FROM `migrations`
WHERE `migration` IN (
    '2026_10_28_000001_add_integration_fields_to_syllabus_table',
    '2026_10_28_000002_add_code_to_courses_table'
);


-- ---------------------------------------------------------------------
-- 6. Comprobación final y restauración del modo SQL.
-- ---------------------------------------------------------------------

SELECT COUNT(*) AS migraciones_restantes FROM `migrations` WHERE migration LIKE '2026_10_28_%';
SHOW CREATE TABLE `syllabus`;
SHOW CREATE TABLE `courses`;

SET SESSION sql_mode = @sql_mode_anterior;
