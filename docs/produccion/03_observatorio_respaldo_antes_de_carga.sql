-- =====================================================================
-- 03_observatorio_respaldo_antes_de_carga.sql
-- BASE: la del observatorio (citas_websocket). En local, observatorio_2.
-- Selecciona la base como predeterminada; el script no usa USE.
--
-- CUÁNDO: después de 01 y 02, y ANTES de activar el envío y la carga
-- inicial. Es lo que permite volver atrás con
-- 03_observatorio_revertir_carga.sql.
--
-- Por qué: al procesar un sílabo de silabos_v2, el observatorio REEMPLAZA
-- los lenguajes, tecnologías y metodologías del curso (en local, la carga
-- completa tocaría 100 cursos), marca como reemplazados los PDF del mismo
-- curso (363 de 532 en local), agrega carreras al curso y puede asignar
-- categoría a tecnologías que no tenían. Sin este respaldo no se puede
-- recuperar el estado anterior.
--
-- Qué hace: CREA tablas nuevas con prefijo respaldo_carga_ y copia en ellas
-- los datos que la carga cambia. No modifica ninguna tabla existente.
-- Si las tablas ya existen (respaldo anterior), el primer CREATE falla y
-- no se sobrescribe nada: bórralas a mano solo si ya no las necesitas.
--
-- Usa CREATE TABLE ... LIKE / INSERT ... SELECT (no CREATE ... AS SELECT),
-- que funciona también con GTID activado.
--
-- Complementa, no reemplaza, un respaldo completo con mysqldump.
-- =====================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;


-- ---------------------------------------------------------------------
-- 1. Pivotes de los cursos (se regeneran al procesar cada sílabo).
-- ---------------------------------------------------------------------

CREATE TABLE respaldo_carga_course_language LIKE course_language;
INSERT INTO respaldo_carga_course_language SELECT * FROM course_language;

CREATE TABLE respaldo_carga_course_technology LIKE course_technology;
INSERT INTO respaldo_carga_course_technology SELECT * FROM course_technology;

CREATE TABLE respaldo_carga_course_methodology LIKE course_methodology;
INSERT INTO respaldo_carga_course_methodology SELECT * FROM course_methodology;

-- Carreras de cada curso (la carga solo agrega, nunca quita).
CREATE TABLE respaldo_carga_career_course LIKE career_course;
INSERT INTO respaldo_carga_career_course SELECT * FROM career_course;


-- ---------------------------------------------------------------------
-- 2. Categoría de cada tecnología (la carga asigna a las que no tienen).
-- ---------------------------------------------------------------------

CREATE TABLE respaldo_carga_technologies (
    id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    category_id BIGINT UNSIGNED NULL
) ENGINE=InnoDB;
INSERT INTO respaldo_carga_technologies (id, category_id) SELECT id, category_id FROM technologies;


-- ---------------------------------------------------------------------
-- 3. Estado de los sílabos subidos por PDF (la carga los marca como
--    reemplazados).
-- ---------------------------------------------------------------------

CREATE TABLE respaldo_carga_syllabus (
    id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    status VARCHAR(20) NOT NULL,
    course_id BIGINT UNSIGNED NULL,
    superseded_at TIMESTAMP NULL,
    superseded_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;
INSERT INTO respaldo_carga_syllabus (id, status, course_id, superseded_at, superseded_by, updated_at)
SELECT id, status, course_id, superseded_at, superseded_by, updated_at FROM syllabus;


-- ---------------------------------------------------------------------
-- 4. Ids máximos del catálogo: lo que tenga un id mayor lo creó la carga
--    (o se creó después por otro camino; la reversión lo comprueba).
-- ---------------------------------------------------------------------

CREATE TABLE respaldo_carga_maximos (
    tabla VARCHAR(64) NOT NULL PRIMARY KEY,
    max_id BIGINT UNSIGNED NOT NULL,
    respaldado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO respaldo_carga_maximos (tabla, max_id)
          SELECT 'languages', COALESCE(MAX(id), 0) FROM languages
UNION ALL SELECT 'technologies', COALESCE(MAX(id), 0) FROM technologies
UNION ALL SELECT 'methodologies', COALESCE(MAX(id), 0) FROM methodologies
UNION ALL SELECT 'syllabus', COALESCE(MAX(id), 0) FROM syllabus;


-- ---------------------------------------------------------------------
-- 5. Comprobación (solo lectura): cada respaldo con las mismas filas que
--    su tabla. Todas las filas deben decir OK.
-- ---------------------------------------------------------------------

SELECT 'course_language' AS tabla, (SELECT COUNT(*) FROM course_language) AS original,
       (SELECT COUNT(*) FROM respaldo_carga_course_language) AS respaldo
UNION ALL SELECT 'course_technology', (SELECT COUNT(*) FROM course_technology), (SELECT COUNT(*) FROM respaldo_carga_course_technology)
UNION ALL SELECT 'course_methodology', (SELECT COUNT(*) FROM course_methodology), (SELECT COUNT(*) FROM respaldo_carga_course_methodology)
UNION ALL SELECT 'career_course', (SELECT COUNT(*) FROM career_course), (SELECT COUNT(*) FROM respaldo_carga_career_course)
UNION ALL SELECT 'technologies', (SELECT COUNT(*) FROM technologies), (SELECT COUNT(*) FROM respaldo_carga_technologies)
UNION ALL SELECT 'syllabus', (SELECT COUNT(*) FROM syllabus), (SELECT COUNT(*) FROM respaldo_carga_syllabus);

SELECT * FROM respaldo_carga_maximos;
