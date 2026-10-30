-- =====================================================================
-- 01_observatorio_revertir.sql
-- BASE: la del observatorio (citas_websocket). En local, observatorio_2.
--
-- Revierte 01_observatorio_aplicar.sql (esquema). Selecciona la base como
-- predeterminada y ejecuta con auto-commit y "Stop on error" activos.
--
-- ORDEN si ya hubo carga de sílabos:
--   1. Desactivar el envío (OBSERVATORIO_ENABLED=false en silabos_v2) y
--      detener el worker del observatorio.
--   2. 03_observatorio_revertir_carga.sql (deja la base sin sílabos de
--      silabos_v2 y con los pivotes de los cursos como antes de la carga).
--   3. Desplegar el código anterior del observatorio. Funciona con el
--      esquema nuevo (los cambios son aditivos); el código nuevo, en
--      cambio, falla sin las columnas que este script quita.
--   4. Este script. Su bloque 0 lo comprueba: si da más de 0, no seguir.
--
-- Pierde los códigos de curso (courses.code); el bloque 0 los lista y se
-- pueden volver a asignar con 02_observatorio_asignar_codigos.sql.
-- =====================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;


-- ---------------------------------------------------------------------
-- 0. Verificación (solo lectura). Debe dar 0. Si da más de 0, NO SEGUIR:
--    hay sílabos de silabos_v2 o con estados nuevos que no caben en el
--    esquema anterior. Ejecuta antes 03_observatorio_revertir_carga.sql.
-- ---------------------------------------------------------------------

SELECT COUNT(*) AS impiden_revertir FROM syllabus
WHERE source <> 'upload'
   OR status IN ('superseded', 'ignored', 'pending_link')
   OR filename IS NULL OR path IS NULL;

-- Códigos de curso que se perderán.
SELECT id, name, code FROM courses WHERE code IS NOT NULL ORDER BY id;


-- ---------------------------------------------------------------------
-- 1. courses: quitar el código.
-- ---------------------------------------------------------------------

ALTER TABLE courses
    DROP INDEX courses_code_unique,
    DROP COLUMN code;


-- ---------------------------------------------------------------------
-- 2. syllabus: quitar índices.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    DROP INDEX syllabus_external_unique,
    DROP INDEX syllabus_family_index,
    DROP INDEX syllabus_superseded_at_index,
    DROP INDEX syllabus_course_id_index;


-- ---------------------------------------------------------------------
-- 3. syllabus: quitar las 18 columnas.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    DROP COLUMN source,
    DROP COLUMN external_model,
    DROP COLUMN external_id,
    DROP COLUMN codigo_familia,
    DROP COLUMN external_version,
    DROP COLUMN periodo,
    DROP COLUMN periodo_orden,
    DROP COLUMN tipo_plantilla,
    DROP COLUMN course_code,
    DROP COLUMN course_name,
    DROP COLUMN course_id,
    DROP COLUMN careers,
    DROP COLUMN approved_at,
    DROP COLUMN approved_at_source,
    DROP COLUMN content,
    DROP COLUMN content_hash,
    DROP COLUMN superseded_at,
    DROP COLUMN superseded_by;


-- ---------------------------------------------------------------------
-- 4. syllabus: filename, path y status vuelven a su definición anterior.
--    El bloque 0 garantiza que ninguna fila lo impide.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    MODIFY filename VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY path VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY status ENUM('pending', 'processing', 'processed', 'failed')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending';


-- ---------------------------------------------------------------------
-- 5. Quitar el registro de migrations. Esperado: 2 filas.
-- ---------------------------------------------------------------------

DELETE FROM migrations
WHERE migration IN ('2026_10_28_000001_add_integration_fields_to_syllabus_table',
                    '2026_10_28_000002_add_code_to_courses_table');
