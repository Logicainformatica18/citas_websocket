-- =====================================================================
-- 03_observatorio_revertir_carga.sql
-- BASE: la del observatorio (citas_websocket). En local, observatorio_2.
-- Selecciona la base como predeterminada; el script no usa USE.
--
-- Deshace en el observatorio lo que hizo la carga de sílabos de silabos_v2,
-- usando las tablas respaldo_carga_* de
-- 03_observatorio_respaldo_antes_de_carga.sql. No toca el esquema (para
-- eso, después, 01_observatorio_revertir.sql).
--
-- ANTES:
--   - OBSERVATORIO_ENABLED=false en silabos_v2 (y config:cache si aplica):
--     que no lleguen sílabos nuevos mientras se revierte.
--   - Detener el worker del observatorio y vaciar los jobs de sílabos que
--     queden en cola (bloque 0 los cuenta).
--
-- CUIDADO: los pivotes de los cursos vuelven EXACTAMENTE al momento del
-- respaldo. Cualquier cambio manual hecho después en los lenguajes,
-- tecnologías o metodologías de un curso se pierde.
--
-- Los bloques 1 y 2 van dentro de una transacción. Si una sentencia falla,
-- Workbench se detiene (con "Stop on error") y la transacción queda
-- abierta: ejecuta ROLLBACK; y no se habrá cambiado nada.
-- =====================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;


-- ---------------------------------------------------------------------
-- 0. Comprobación previa (solo lectura). Si falla porque una tabla
--    respaldo_carga_* no existe, NO SEGUIR: no hay respaldo.
-- ---------------------------------------------------------------------

SELECT (SELECT COUNT(*) FROM respaldo_carga_course_language) AS r_course_language,
       (SELECT COUNT(*) FROM respaldo_carga_course_technology) AS r_course_technology,
       (SELECT COUNT(*) FROM respaldo_carga_course_methodology) AS r_course_methodology,
       (SELECT COUNT(*) FROM respaldo_carga_career_course) AS r_career_course,
       (SELECT COUNT(*) FROM respaldo_carga_technologies) AS r_technologies,
       (SELECT COUNT(*) FROM respaldo_carga_syllabus) AS r_syllabus,
       (SELECT MIN(respaldado_at) FROM respaldo_carga_maximos) AS respaldado_at;

-- Lo que se va a deshacer.
SELECT source, status, COUNT(*) AS filas FROM syllabus GROUP BY source, status ORDER BY source, status;
SELECT COUNT(*) AS jobs_de_silabos_en_cola_deben_ser_0 FROM jobs
WHERE payload LIKE '%ProcessIngestedSyllabusJob%' OR payload LIKE '%ProcessSyllabusJob%';


-- ---------------------------------------------------------------------
-- 1. Pivotes, carreras, categorías y sílabos (una sola transacción).
-- ---------------------------------------------------------------------

START TRANSACTION;

-- 1.1 Lenguajes, tecnologías y metodologías de los cursos: como en el
--     respaldo. Los JOIN saltan filas de cursos o entidades que ya no existen.
DELETE FROM course_language;
INSERT INTO course_language
SELECT r.* FROM respaldo_carga_course_language r
JOIN courses c ON c.id = r.course_id JOIN languages x ON x.id = r.language_id;

DELETE FROM course_technology;
INSERT INTO course_technology
SELECT r.* FROM respaldo_carga_course_technology r
JOIN courses c ON c.id = r.course_id JOIN technologies x ON x.id = r.technology_id;

DELETE FROM course_methodology;
INSERT INTO course_methodology
SELECT r.* FROM respaldo_carga_course_methodology r
JOIN courses c ON c.id = r.course_id JOIN methodologies x ON x.id = r.methodology_id;

-- 1.2 Carreras: quitar las que se agregaron después del respaldo.
DELETE cc FROM career_course cc
LEFT JOIN respaldo_carga_career_course r ON r.id = cc.id
WHERE r.id IS NULL;

-- 1.3 Categoría de las tecnologías que existían: como en el respaldo.
UPDATE technologies t
JOIN respaldo_carga_technologies r ON r.id = t.id
SET t.category_id = r.category_id
WHERE NOT (t.category_id <=> r.category_id);

-- 1.4 PDF que existían: estado, curso y reemplazo como en el respaldo.
UPDATE syllabus s
JOIN respaldo_carga_syllabus r ON r.id = s.id
SET s.status = r.status, s.course_id = r.course_id,
    s.superseded_at = r.superseded_at, s.superseded_by = r.superseded_by, s.updated_at = r.updated_at
WHERE s.source = 'upload';

-- 1.5 PDF subidos después del respaldo que quedaron reemplazados por un
--     sílabo de silabos_v2: vuelven a estar vigentes.
UPDATE syllabus s
JOIN syllabus v ON v.id = s.superseded_by AND v.source = 'silabos_v2'
SET s.superseded_at = NULL, s.superseded_by = NULL
WHERE s.source = 'upload';

-- 1.6 Sílabos recibidos de silabos_v2.
DELETE FROM syllabus WHERE source = 'silabos_v2';

COMMIT;


-- ---------------------------------------------------------------------
-- 2. Entidades del catálogo creadas después del respaldo y que ya nadie
--    usa (ni cursos, ni ofertas, ni métricas). Las que sí se usan se
--    quedan; la consulta 3 las lista.
-- ---------------------------------------------------------------------

START TRANSACTION;

DELETE x FROM technologies x
JOIN respaldo_carga_maximos m ON m.tabla = 'technologies'
WHERE x.id > m.max_id
  AND NOT EXISTS (SELECT 1 FROM course_technology p WHERE p.technology_id = x.id)
  AND NOT EXISTS (SELECT 1 FROM technology_job p WHERE p.technology_id = x.id)
  AND NOT EXISTS (SELECT 1 FROM technology_metrics p WHERE p.technology_id = x.id)
  AND NOT EXISTS (SELECT 1 FROM technology_trend_technology p WHERE p.technology_id = x.id);

DELETE x FROM languages x
JOIN respaldo_carga_maximos m ON m.tabla = 'languages'
WHERE x.id > m.max_id
  AND NOT EXISTS (SELECT 1 FROM course_language p WHERE p.language_id = x.id)
  AND NOT EXISTS (SELECT 1 FROM language_job p WHERE p.language_id = x.id)
  AND NOT EXISTS (SELECT 1 FROM language_metrics p WHERE p.language_id = x.id);

DELETE x FROM methodologies x
JOIN respaldo_carga_maximos m ON m.tabla = 'methodologies'
WHERE x.id > m.max_id
  AND NOT EXISTS (SELECT 1 FROM course_methodology p WHERE p.methodology_id = x.id)
  AND NOT EXISTS (SELECT 1 FROM methodology_job p WHERE p.methodology_id = x.id)
  AND NOT EXISTS (SELECT 1 FROM methodology_metrics p WHERE p.methodology_id = x.id);

COMMIT;


-- ---------------------------------------------------------------------
-- 3. Comprobación final (solo lectura).
-- ---------------------------------------------------------------------

-- Esperado: 0 sílabos de silabos_v2 y 0 PDF reemplazados por ellos.
SELECT (SELECT COUNT(*) FROM syllabus WHERE source = 'silabos_v2') AS silabos_v2,
       (SELECT COUNT(*) FROM syllabus s LEFT JOIN syllabus v ON v.id = s.superseded_by
        WHERE s.superseded_by IS NOT NULL AND v.id IS NULL) AS reemplazados_por_un_silabo_inexistente;

-- Pivotes iguales al respaldo: diferencia 0 en las tres (salvo filas de
-- cursos o entidades borrados después del respaldo).
SELECT (SELECT COUNT(*) FROM course_language) - (SELECT COUNT(*) FROM respaldo_carga_course_language) AS dif_course_language,
       (SELECT COUNT(*) FROM course_technology) - (SELECT COUNT(*) FROM respaldo_carga_course_technology) AS dif_course_technology,
       (SELECT COUNT(*) FROM course_methodology) - (SELECT COUNT(*) FROM respaldo_carga_course_methodology) AS dif_course_methodology;

-- Entidades nuevas que se quedaron porque alguien las usa (revisar a mano).
SELECT 'technologies' AS tabla, x.id, x.name FROM technologies x
JOIN respaldo_carga_maximos m ON m.tabla = 'technologies' WHERE x.id > m.max_id
UNION ALL
SELECT 'languages', x.id, x.name FROM languages x
JOIN respaldo_carga_maximos m ON m.tabla = 'languages' WHERE x.id > m.max_id
UNION ALL
SELECT 'methodologies', x.id, x.name FROM methodologies x
JOIN respaldo_carga_maximos m ON m.tabla = 'methodologies' WHERE x.id > m.max_id;

-- Las tablas respaldo_carga_* se conservan. Cuando ya no se necesiten:
--   DROP TABLE respaldo_carga_course_language, respaldo_carga_course_technology,
--              respaldo_carga_course_methodology, respaldo_carga_career_course,
--              respaldo_carga_technologies, respaldo_carga_syllabus, respaldo_carga_maximos;
--
-- En silabos_v2, observatorio_envios sigue marcando esos envíos como
-- 'enviado': para volver a enviar, observatorio:carga-inicial --forzar
-- (o 01_silabos_revertir.sql si se quita la integración).
