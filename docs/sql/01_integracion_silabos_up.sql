-- =====================================================================
-- Observatorio · 01_integracion_silabos_up.sql  (MySQL 8)
-- Integración silabos_v2 → observatorio: cambios de esquema.
--
-- Equivale a estas migraciones (que se mantienen solo para los tests):
--   2026_10_28_000001_add_integration_fields_to_syllabus_table
--   2026_10_28_000002_add_code_to_courses_table
--
-- Es aditivo: las filas existentes (sílabos subidos por PDF) quedan con
-- source = 'upload' y las columnas nuevas en NULL. No borra datos.
-- Cada ALTER TABLE es atómico en MySQL 8: si falla, no aplica nada de esa
-- sentencia. Ejecutar los bloques en orden y detenerse ante un error.
-- =====================================================================


-- ---------------------------------------------------------------------
-- A. Verificaciones (solo lectura). Resultado esperado en cada comentario.
-- ---------------------------------------------------------------------

-- A1. Modo SQL: debe contener STRICT_TRANS_TABLES (un dato inválido da
--     error en vez de truncarse en silencio).
SELECT @@SESSION.sql_mode;

-- A2. Valores actuales de status: solo pending, processing, processed o
--     failed (hoy: processed 528, failed 4).
SELECT status, COUNT(*) AS filas FROM syllabus GROUP BY status;

-- A3. Definición actual de status:
--     enum('pending','processing','processed','failed').
SHOW COLUMNS FROM syllabus LIKE 'status';

-- A4. Tablas de las que depende la integración: 7 filas.
--     No se crean aquí; si falta alguna, no seguir.
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('syllabus', 'courses', 'careers', 'career_course', 'jobs', 'failed_jobs', 'migrations');

-- A5. Columnas que se van a crear: 0 filas (ninguna existe todavía).
--     Incluye syllabus.course_id: como todavía no existe, no puede haber
--     sílabos que apunten a un curso inexistente (ver C2 después del cambio).
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME = 'syllabus' AND COLUMN_NAME IN (
          'source', 'external_model', 'external_id', 'codigo_familia', 'external_version',
          'periodo', 'periodo_orden', 'tipo_plantilla', 'course_code', 'course_name',
          'course_id', 'careers', 'approved_at', 'approved_at_source', 'content',
          'content_hash', 'superseded_at', 'superseded_by'))
    OR (TABLE_NAME = 'courses' AND COLUMN_NAME = 'code'));

-- A6. Índices que se van a crear: 0 filas.
SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND INDEX_NAME IN ('syllabus_external_unique', 'syllabus_family_index',
                     'syllabus_superseded_at_index', 'syllabus_course_id_index', 'courses_code_unique');

-- A7. Claves foráneas en syllabus: 0 filas (no hay y no se crea ninguna).
SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus' AND CONSTRAINT_TYPE = 'FOREIGN KEY';

-- A8. Migraciones: batch máximo 11 y 0 migraciones de la integración registradas.
SELECT MAX(batch) AS batch_maximo,
       SUM(migration LIKE '2026_10_28_%') AS ya_registradas
FROM migrations;


-- ---------------------------------------------------------------------
-- B1. syllabus: filename y path aceptan NULL (los sílabos que llegan por
--     API no tienen PDF) y status agrega superseded, ignored y
--     pending_link, conservando los cuatro valores actuales.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    MODIFY filename VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    MODIFY path VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    MODIFY status ENUM('pending', 'processing', 'processed', 'failed', 'superseded', 'ignored', 'pending_link')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending';


-- ---------------------------------------------------------------------
-- B2. syllabus: las 18 columnas de la integración (origen, identificación
--     en silabos_v2, versión y periodo, curso, carreras, aprobación,
--     contenido y reemplazo). Las filas actuales quedan con source = 'upload'.
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
-- B3. syllabus: índices. Único por sílabo de silabos_v2 (idempotencia),
--     búsqueda de la familia de versiones, filtro de vigentes y búsqueda
--     por curso. Sin clave foránea: al borrar un curso, la aplicación deja
--     course_id en NULL.
-- ---------------------------------------------------------------------

ALTER TABLE syllabus
    ADD UNIQUE syllabus_external_unique (source, external_model, external_id),
    ADD INDEX syllabus_family_index (source, external_model, codigo_familia),
    ADD INDEX syllabus_superseded_at_index (superseded_at),
    ADD INDEX syllabus_course_id_index (course_id);


-- ---------------------------------------------------------------------
-- B4. courses: código del curso en silabos_v2, único y opcional.
-- ---------------------------------------------------------------------

ALTER TABLE courses
    ADD code VARCHAR(50) NULL AFTER id,
    ADD UNIQUE courses_code_unique (code);


-- ---------------------------------------------------------------------
-- C1. Registro en migrations con el batch siguiente al máximo (12 hoy),
--     para que `php artisan migrate` no las aplique de nuevo. No duplica.
--     Esperado: 2 filas afectadas.
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
-- C2. Comprobación final (solo lectura).
-- ---------------------------------------------------------------------

-- 2 filas, ambas con batch 12.
SELECT migration, batch FROM migrations WHERE migration LIKE '2026_10_28_%';
-- Una fila: upload / processed 528 y otra: upload / failed 4.
SELECT source, status, COUNT(*) AS filas FROM syllabus GROUP BY source, status;
-- Sílabos con course_id que apunta a un curso inexistente: 0.
SELECT COUNT(*) AS huerfanos FROM syllabus s LEFT JOIN courses c ON c.id = s.course_id
WHERE s.course_id IS NOT NULL AND c.id IS NULL;
