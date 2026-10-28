-- =====================================================================
-- Observatorio · 01_integracion_silabos_down.sql  (MySQL 8)
-- Revierte exactamente 01_integracion_silabos_up.sql. No borra filas.
-- Ejecutar los bloques en orden y detenerse ante un error.
-- =====================================================================


-- ---------------------------------------------------------------------
-- R0. Verificación (solo lectura). Debe dar 0. Si da más de 0, NO SEGUIR:
--     hay sílabos recibidos de silabos_v2 que no caben en el esquema
--     anterior (sin PDF o con estados nuevos). Qué hacer con ellos es una
--     decisión aparte.
-- ---------------------------------------------------------------------

SELECT COUNT(*) AS impiden_revertir FROM syllabus
WHERE source <> 'upload'
   OR status IN ('superseded', 'ignored', 'pending_link')
   OR filename IS NULL OR path IS NULL;

-- Códigos de curso que se perderán al quitar courses.code
-- (se pueden volver a asignar con 02_asignar_codigos.sql).
SELECT id, name, code FROM courses WHERE code IS NOT NULL ORDER BY id;


-- ---------------------------------------------------------------------
-- R1. courses: quitar el código (inverso de B4).
-- ---------------------------------------------------------------------

ALTER TABLE courses
    DROP INDEX courses_code_unique,
    DROP COLUMN code;


-- ---------------------------------------------------------------------
-- R2. syllabus: quitar índices (inverso de B3).
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    DROP INDEX syllabus_external_unique,
    DROP INDEX syllabus_family_index,
    DROP INDEX syllabus_superseded_at_index,
    DROP INDEX syllabus_course_id_index;


-- ---------------------------------------------------------------------
-- R3. syllabus: quitar las 18 columnas (inverso de B2).
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
-- R4. syllabus: filename, path y status vuelven a su definición actual
--     (inverso de B1). R0 garantiza que ninguna fila lo impide.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    MODIFY filename VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY path VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY status ENUM('pending', 'processing', 'processed', 'failed')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending';


-- ---------------------------------------------------------------------
-- R5. Quitar el registro de migrations (inverso de C1). Esperado: 2 filas.
-- ---------------------------------------------------------------------

DELETE FROM migrations
WHERE migration IN ('2026_10_28_000001_add_integration_fields_to_syllabus_table',
                    '2026_10_28_000002_add_code_to_courses_table');
