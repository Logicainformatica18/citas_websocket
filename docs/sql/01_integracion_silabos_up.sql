-- =====================================================================
-- Observatorio · 01_integracion_silabos_up.sql
-- Integración silabos_v2 → observatorio: cambios de esquema (MySQL 8).
--
-- Equivale a estas migraciones (que se mantienen solo para los tests):
--   2026_10_28_000001_add_integration_fields_to_syllabus_table
--   2026_10_28_000002_add_code_to_courses_table
--
-- Es aditivo: las filas existentes (sílabos subidos por PDF) quedan con
-- source = 'upload' y las columnas nuevas en NULL.
--
-- Ejecutar con el cliente mysql SIN --force, para que se detenga en el
-- primer error:
--   mysql -h HOST -P PUERTO -u USUARIO -p BASE < 01_integracion_silabos_up.sql
--
-- No es re-ejecutable: si ya se aplicó, se detiene con "Duplicate column
-- name 'source'" sin haber cambiado nada más.
-- =====================================================================

SET NAMES utf8mb4;

-- Modo estricto en esta sesión: ante un dato inválido, error en lugar de
-- truncar en silencio. Se restaura al final.
SET @sql_mode_anterior = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';


-- ---------------------------------------------------------------------
-- 0. Verificaciones previas.
--    Tablas de las que depende la integración y que ya existen en la base.
--    No se crean aquí: si alguna falta, la consulta falla con
--    "Table ... doesn't exist" y el script se detiene sin cambiar nada.
-- ---------------------------------------------------------------------

-- Tablas que se modifican
SELECT 'syllabus' AS tabla_requerida, COUNT(*) AS filas FROM syllabus;
SELECT 'courses' AS tabla_requerida, COUNT(*) AS filas FROM courses;
-- Cola de trabajos (QUEUE_CONNECTION=database): ProcessIngestedSyllabusJob
SELECT 'jobs' AS tabla_requerida, COUNT(*) AS filas FROM jobs;
SELECT 'failed_jobs' AS tabla_requerida, COUNT(*) AS filas FROM failed_jobs;
-- Carreras y relación curso-carrera (vinculación de carreras mapeadas)
SELECT 'careers' AS tabla_requerida, COUNT(*) AS filas FROM careers;
SELECT 'career_course' AS tabla_requerida, COUNT(*) AS filas FROM career_course;
-- Registro de migraciones de Laravel
SELECT 'migrations' AS tabla_requerida, MAX(batch) AS batch_actual FROM migrations;


-- ---------------------------------------------------------------------
-- 1. syllabus: columnas existentes que cambian.
--    - filename y path pasan a aceptar NULL: los sílabos que llegan por
--      API no tienen PDF.
--    - status agrega tres valores:
--        superseded   hay una versión vigente mayor; no se procesa
--        ignored      ninguna carrera mapeada, o "No vincular"
--        pending_link ningún curso tiene su código; espera vinculación
--      Los cuatro valores actuales se mantienen; no cambia ninguna fila.
-- ---------------------------------------------------------------------

ALTER TABLE `syllabus`
    MODIFY `filename` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    MODIFY `path` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    MODIFY `status` ENUM('pending', 'processing', 'processed', 'failed', 'superseded', 'ignored', 'pending_link')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending';


-- ---------------------------------------------------------------------
-- 2. syllabus: columnas nuevas de la integración.
--    source         'upload' (PDF, todas las filas actuales) o 'silabos_v2'
--    external_*     identificación del sílabo en silabos_v2 (modelo isil/scl, id, versión)
--    codigo_familia familia de versiones: (external_model, codigo_familia)
--    periodo / periodo_orden  periodo tal cual y normalizado para ordenar
--    course_code / course_name  curso según silabos_v2 (code = courses.code)
--    course_id      curso del observatorio al que quedó enlazado
--    careers        carreras de silabos_v2 tal como llegaron (JSON)
--    approved_at(_source)  fecha de aprobación (informativa) y de dónde salió
--    content / content_hash  contenido recibido y su hash (idempotencia)
--    superseded_at / superseded_by  reemplazado por una versión vigente
-- ---------------------------------------------------------------------

ALTER TABLE `syllabus`
    ADD `source` VARCHAR(20) NOT NULL DEFAULT 'upload' AFTER `id`,
    ADD `external_model` VARCHAR(10) NULL AFTER `source`,
    ADD `external_id` BIGINT UNSIGNED NULL AFTER `external_model`,
    ADD `codigo_familia` VARCHAR(50) NULL AFTER `external_id`,
    ADD `external_version` INT UNSIGNED NULL AFTER `codigo_familia`,
    ADD `periodo` VARCHAR(20) NULL AFTER `external_version`,
    ADD `periodo_orden` INT UNSIGNED NULL AFTER `periodo`,
    ADD `tipo_plantilla` TINYINT UNSIGNED NULL AFTER `periodo_orden`,
    ADD `course_code` VARCHAR(50) NULL AFTER `tipo_plantilla`,
    ADD `course_name` VARCHAR(255) NULL AFTER `course_code`,
    ADD `course_id` BIGINT UNSIGNED NULL AFTER `course_name`,
    ADD `careers` JSON NULL AFTER `course_id`,
    ADD `approved_at` TIMESTAMP NULL AFTER `careers`,
    ADD `approved_at_source` VARCHAR(30) NULL AFTER `approved_at`,
    ADD `content` JSON NULL AFTER `approved_at_source`,
    ADD `content_hash` CHAR(64) NULL AFTER `content`,
    ADD `superseded_at` TIMESTAMP NULL AFTER `content_hash`,
    ADD `superseded_by` BIGINT UNSIGNED NULL AFTER `superseded_at`;


-- ---------------------------------------------------------------------
-- 3. syllabus: clave foránea e índices.
--    - course_id → courses.id; si se borra el curso, queda en NULL.
--    - syllabus_external_unique: un sílabo de silabos_v2 se guarda una sola
--      vez (idempotencia). Las filas 'upload' tienen external_* en NULL y
--      no chocan entre sí.
--    - syllabus_family_index: búsqueda de la familia de versiones.
--    - superseded_at: filtro de sílabos vigentes.
-- ---------------------------------------------------------------------

ALTER TABLE `syllabus`
    ADD CONSTRAINT `syllabus_course_id_foreign`
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
    ADD UNIQUE `syllabus_external_unique` (`source`, `external_model`, `external_id`),
    ADD INDEX `syllabus_family_index` (`source`, `external_model`, `codigo_familia`),
    ADD INDEX `syllabus_superseded_at_index` (`superseded_at`);


-- ---------------------------------------------------------------------
-- 4. courses: código del curso en silabos_v2.
--    Único y opcional: los cursos quedan sin código hasta asignarlo con
--    02_asignar_codigos.sql o desde la página "Vinculación de cursos".
-- ---------------------------------------------------------------------

ALTER TABLE `courses`
    ADD `code` VARCHAR(50) NULL AFTER `id`,
    ADD UNIQUE `courses_code_unique` (`code`);


-- ---------------------------------------------------------------------
-- 5. Registro en migrations, con el batch siguiente al máximo actual,
--    para que `php artisan migrate` no intente aplicarlas de nuevo.
--    No duplica filas si ya estuvieran registradas.
-- ---------------------------------------------------------------------

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.migration, (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`)
FROM (
    SELECT '2026_10_28_000001_add_integration_fields_to_syllabus_table' AS migration
    UNION ALL
    SELECT '2026_10_28_000002_add_code_to_courses_table'
) AS m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.migration = m.migration);


-- ---------------------------------------------------------------------
-- 6. Comprobación final y restauración del modo SQL.
-- ---------------------------------------------------------------------

SELECT migration, batch FROM `migrations` WHERE migration LIKE '2026_10_28_%' ORDER BY migration;
SELECT source, status, COUNT(*) AS filas FROM `syllabus` GROUP BY source, status;

SET SESSION sql_mode = @sql_mode_anterior;
