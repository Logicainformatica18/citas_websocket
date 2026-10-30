-- =====================================================================
-- 01_observatorio_aplicar.sql
-- BASE: la del observatorio (citas_websocket). En local, observatorio_2.
--
-- Antes: 00_verificar_observatorio.sql sin ningún NO COINCIDE.
-- Selecciona la base como predeterminada (Workbench: doble clic en el
-- esquema) y ejecuta el archivo completo con Ctrl+Shift+Enter, con
-- auto-commit activado y "Stop on error" activo. No usa USE: si no hay
-- base seleccionada, el primer ALTER falla con "No database selected" y
-- no se aplica nada.
--
-- Cambios de esquema de la integración silabos_v2 → observatorio. Equivale
-- a las migraciones 2026_10_28_000001_add_integration_fields_to_syllabus_table
-- y 2026_10_28_000002_add_code_to_courses_table (que se mantienen solo para
-- los tests: no ejecutar `php artisan migrate` contra esta base).
--
-- Es aditivo: las filas existentes (sílabos subidos por PDF) quedan con
-- source = 'upload' y las columnas nuevas en NULL. No borra datos.
-- Cada ALTER TABLE es atómico: si uno falla, esa sentencia no aplica nada;
-- revisa el error antes de seguir (01_observatorio_revertir.sql deshace
-- los que sí se aplicaron).
-- =====================================================================

SET NAMES utf8mb4;

-- Base sobre la que se va a ejecutar (comprueba el nombre).
SELECT DATABASE() AS base_seleccionada;


-- ---------------------------------------------------------------------
-- 1. syllabus: filename y path aceptan NULL (los sílabos que llegan por
--    API no tienen PDF) y status agrega superseded, ignored y
--    pending_link, conservando los cuatro valores actuales.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    MODIFY filename VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    MODIFY path VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    MODIFY status ENUM('pending', 'processing', 'processed', 'failed', 'superseded', 'ignored', 'pending_link')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending';


-- ---------------------------------------------------------------------
-- 2. syllabus: las 18 columnas de la integración (origen, identificación
--    en silabos_v2, versión y periodo, curso, carreras, aprobación,
--    contenido y reemplazo).
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    ADD source VARCHAR(20) NOT NULL DEFAULT 'upload' AFTER id,
    ADD external_model VARCHAR(10) NULL AFTER source,
    ADD external_id BIGINT UNSIGNED NULL AFTER external_model,
    ADD codigo_familia VARCHAR(50) NULL AFTER external_id,
    ADD external_version INT UNSIGNED NULL AFTER codigo_familia,
    ADD periodo VARCHAR(20) NULL AFTER external_version,
    ADD periodo_orden INT UNSIGNED NULL AFTER periodo,
    ADD tipo_plantilla TINYINT UNSIGNED NULL AFTER periodo_orden,
    ADD course_code VARCHAR(50) NULL AFTER tipo_plantilla,
    ADD course_name VARCHAR(255) NULL AFTER course_code,
    ADD course_id BIGINT UNSIGNED NULL AFTER course_name,
    ADD careers JSON NULL AFTER course_id,
    ADD approved_at TIMESTAMP NULL AFTER careers,
    ADD approved_at_source VARCHAR(30) NULL AFTER approved_at,
    ADD content JSON NULL AFTER approved_at_source,
    ADD content_hash CHAR(64) NULL AFTER content,
    ADD superseded_at TIMESTAMP NULL AFTER content_hash,
    ADD superseded_by BIGINT UNSIGNED NULL AFTER superseded_at;


-- ---------------------------------------------------------------------
-- 3. syllabus: índices. Único por sílabo de silabos_v2 (idempotencia),
--    familia de versiones, filtro de vigentes y búsqueda por curso.
--    Sin clave foránea: al borrar un curso, la aplicación deja course_id
--    en NULL.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    ADD UNIQUE syllabus_external_unique (source, external_model, external_id),
    ADD INDEX syllabus_family_index (source, external_model, codigo_familia),
    ADD INDEX syllabus_superseded_at_index (superseded_at),
    ADD INDEX syllabus_course_id_index (course_id);


-- ---------------------------------------------------------------------
-- 4. courses: código del curso en silabos_v2, único y opcional.
-- ---------------------------------------------------------------------

ALTER TABLE courses
    ADD code VARCHAR(50) NULL AFTER id,
    ADD UNIQUE courses_code_unique (code);


-- ---------------------------------------------------------------------
-- 5. Registro en migrations con el batch siguiente al máximo, para que
--    `php artisan migrate` no las aplique de nuevo. No duplica.
--    Esperado: 2 filas afectadas.
-- ---------------------------------------------------------------------

INSERT INTO migrations (migration, batch)
SELECT m.migration, (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations)
FROM (
    SELECT '2026_10_28_000001_add_integration_fields_to_syllabus_table' AS migration
    UNION ALL
    SELECT '2026_10_28_000002_add_code_to_courses_table'
) AS m
WHERE NOT EXISTS (SELECT 1 FROM migrations x WHERE x.migration = m.migration);


-- ---------------------------------------------------------------------
-- 6. Comprobación final (solo lectura).
-- ---------------------------------------------------------------------

-- 2 filas con el mismo batch (el máximo).
SELECT migration, batch FROM migrations WHERE migration LIKE '2026\_10\_28\_%';
-- Todas las filas con source = 'upload' y los mismos estados que antes.
SELECT source, status, COUNT(*) AS filas FROM syllabus GROUP BY source, status;
-- 5 índices nuevos.
SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND INDEX_NAME IN ('syllabus_external_unique', 'syllabus_family_index', 'syllabus_superseded_at_index',
                     'syllabus_course_id_index', 'courses_code_unique')
GROUP BY TABLE_NAME, INDEX_NAME;
