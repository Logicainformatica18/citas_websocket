-- =====================================================================
-- 00_verificar_silabos.sql · SOLO LECTURA
-- BASE: la de silabos_v2. En local se llama silabo_2; en producción puede
-- llamarse distinto.
--
-- Selecciona esa base como predeterminada antes de ejecutar (en MySQL
-- Workbench: doble clic en el esquema, queda en negrita) y ejecuta el
-- archivo completo con Ctrl+Shift+Enter. No usa USE ni modifica nada.
--
-- Comprueba, ANTES de aplicar 01_silabos_aplicar.sql:
--   - que existen las tablas y columnas que lee el envío al observatorio
--     (ObservatorioPayloadBuilder y observatorio:carga-inicial);
--   - que nada de lo que se va a crear existe ya (observatorio_envios y su
--     registro en migrations);
--   - cuántos jobs hay en la cola (el worker que se levante los ejecutará);
--   - cuántas familias aprobadas entrarían en la carga inicial.
--
-- Resultados (una pestaña por consulta en Workbench):
--   1. Tablas o columnas que faltan (vacío = bien).
--   2. Jobs pendientes en la cola, por clase.
--   3. RESUMEN (la última): OK, NO COINCIDE (no seguir) o AVISO (revisar).
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Tablas y columnas requeridas que no existen. Esperado: ninguna fila.
-- ---------------------------------------------------------------------

SELECT req.t AS tabla, req.c AS columna_que_falta
FROM (
              SELECT 'silabos' AS t, 'id' AS c
    UNION ALL SELECT 'silabos', 'codigo'
    UNION ALL SELECT 'silabos', 'codigo_familia'
    UNION ALL SELECT 'silabos', 'nombre'
    UNION ALL SELECT 'silabos', 'version'
    UNION ALL SELECT 'silabos', 'periodo'
    UNION ALL SELECT 'silabos', 'estado_id'
    UNION ALL SELECT 'silabos', 'tipo_plantilla'
    UNION ALL SELECT 'silabos', 'creditos'
    UNION ALL SELECT 'silabos', 'unidad_didactica'
    UNION ALL SELECT 'silabos', 'departamento'
    UNION ALL SELECT 'silabos', 'total_horas_periodo'
    UNION ALL SELECT 'silabos', 'total_horas_semana'
    UNION ALL SELECT 'silabos', 'deleted_at'
    UNION ALL SELECT 'silabos', 'updated_at'
    UNION ALL SELECT 'silabo_detalles', 'silabo_id'
    UNION ALL SELECT 'silabo_detalles', 'variable'
    UNION ALL SELECT 'silabo_detalles', 'valor'
    UNION ALL SELECT 'silabo_detalles', 'estado'
    UNION ALL SELECT 'careers', 'id'
    UNION ALL SELECT 'careers', 'codigo'
    UNION ALL SELECT 'careers', 'nombre'
    UNION ALL SELECT 'career_silabo_details', 'silabo_id'
    UNION ALL SELECT 'career_silabo_details', 'career_id'
    UNION ALL SELECT 'silabo_estados', 'silabo_id'
    UNION ALL SELECT 'silabo_estados', 'estado_id'
    UNION ALL SELECT 'silabo_estados', 'created_at'
    UNION ALL SELECT 'jobs', 'queue'
    UNION ALL SELECT 'jobs', 'payload'
    UNION ALL SELECT 'failed_jobs', 'uuid'
    UNION ALL SELECT 'migrations', 'migration'
    UNION ALL SELECT 'migrations', 'batch'
) req
LEFT JOIN information_schema.COLUMNS col
       ON col.TABLE_SCHEMA = DATABASE() AND col.TABLE_NAME = req.t AND col.COLUMN_NAME = req.c
WHERE col.COLUMN_NAME IS NULL
ORDER BY req.t, req.c;


-- ---------------------------------------------------------------------
-- 2. Jobs pendientes en la cola, por clase. Un worker los ejecutará al
--    levantarse (en local había 2 AnalizarRepeticiones antiguos, que
--    generan embeddings con OpenAI). Decide qué hacer con ellos antes de
--    levantar el worker (ver PASOS_PRODUCCION.md).
-- ---------------------------------------------------------------------

SELECT queue,
       JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) AS clase,
       COUNT(*) AS jobs,
       FROM_UNIXTIME(MIN(created_at)) AS el_mas_antiguo
FROM jobs
GROUP BY queue, clase
ORDER BY jobs DESC;


-- ---------------------------------------------------------------------
-- 3. RESUMEN. resultado: OK / NO COINCIDE / AVISO.
-- ---------------------------------------------------------------------

SELECT r.n, r.comprobacion, r.esperado, r.encontrado,
       CASE WHEN r.ok THEN 'OK' WHEN r.bloquea THEN 'NO COINCIDE' ELSE 'AVISO' END AS resultado
FROM (
    SELECT 1 AS n, 'Base seleccionada' AS comprobacion, 'una base' AS esperado,
           COALESCE(DATABASE(), '(ninguna: selecciónala y vuelve a ejecutar)') AS encontrado,
           DATABASE() IS NOT NULL AS ok, 1 AS bloquea
    UNION ALL
    SELECT 2, 'Servidor MySQL 8 (probado en 8.0.46)', 'MySQL 8.x', VERSION(),
           VERSION() NOT LIKE '%MariaDB%' AND CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED) >= 8, 0
    UNION ALL
    SELECT 3, 'sql_mode con STRICT_TRANS_TABLES', 'contiene STRICT_TRANS_TABLES', @@SESSION.sql_mode,
           FIND_IN_SET('STRICT_TRANS_TABLES', @@SESSION.sql_mode) > 0, 0
    UNION ALL
    SELECT 4, 'Tablas requeridas (silabos, silabo_detalles, careers, career_silabo_details, silabo_estados, jobs, failed_jobs, migrations)',
           '8 de 8',
           CONCAT((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
                   ('silabos', 'silabo_detalles', 'careers', 'career_silabo_details', 'silabo_estados', 'jobs', 'failed_jobs', 'migrations')), ' de 8'),
           (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
            ('silabos', 'silabo_detalles', 'careers', 'career_silabo_details', 'silabo_estados', 'jobs', 'failed_jobs', 'migrations')) = 8, 1
    UNION ALL
    SELECT 5, 'Columnas que lee el envío (detalle en la consulta 1)', '32 de 32',
           CONCAT((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND (
                   (TABLE_NAME = 'silabos' AND COLUMN_NAME IN ('id', 'codigo', 'codigo_familia', 'nombre', 'version', 'periodo', 'estado_id',
                        'tipo_plantilla', 'creditos', 'unidad_didactica', 'departamento', 'total_horas_periodo', 'total_horas_semana', 'deleted_at', 'updated_at'))
                OR (TABLE_NAME = 'silabo_detalles' AND COLUMN_NAME IN ('silabo_id', 'variable', 'valor', 'estado'))
                OR (TABLE_NAME = 'careers' AND COLUMN_NAME IN ('id', 'codigo', 'nombre'))
                OR (TABLE_NAME = 'career_silabo_details' AND COLUMN_NAME IN ('silabo_id', 'career_id'))
                OR (TABLE_NAME = 'silabo_estados' AND COLUMN_NAME IN ('silabo_id', 'estado_id', 'created_at'))
                OR (TABLE_NAME = 'jobs' AND COLUMN_NAME IN ('queue', 'payload'))
                OR (TABLE_NAME = 'failed_jobs' AND COLUMN_NAME = 'uuid')
                OR (TABLE_NAME = 'migrations' AND COLUMN_NAME IN ('migration', 'batch')))), ' de 32'),
           (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND (
                   (TABLE_NAME = 'silabos' AND COLUMN_NAME IN ('id', 'codigo', 'codigo_familia', 'nombre', 'version', 'periodo', 'estado_id',
                        'tipo_plantilla', 'creditos', 'unidad_didactica', 'departamento', 'total_horas_periodo', 'total_horas_semana', 'deleted_at', 'updated_at'))
                OR (TABLE_NAME = 'silabo_detalles' AND COLUMN_NAME IN ('silabo_id', 'variable', 'valor', 'estado'))
                OR (TABLE_NAME = 'careers' AND COLUMN_NAME IN ('id', 'codigo', 'nombre'))
                OR (TABLE_NAME = 'career_silabo_details' AND COLUMN_NAME IN ('silabo_id', 'career_id'))
                OR (TABLE_NAME = 'silabo_estados' AND COLUMN_NAME IN ('silabo_id', 'estado_id', 'created_at'))
                OR (TABLE_NAME = 'jobs' AND COLUMN_NAME IN ('queue', 'payload'))
                OR (TABLE_NAME = 'failed_jobs' AND COLUMN_NAME = 'uuid')
                OR (TABLE_NAME = 'migrations' AND COLUMN_NAME IN ('migration', 'batch')))) = 32, 1
    UNION ALL
    SELECT 6, 'observatorio_envios todavía no existe', '0',
           CAST((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'observatorio_envios') AS CHAR),
           (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'observatorio_envios') = 0, 1
    UNION ALL
    SELECT 7, 'Migración de la integración todavía no registrada', '0',
           CAST((SELECT COUNT(*) FROM migrations WHERE migration = '2026_10_28_000001_create_observatorio_envios_table') AS CHAR),
           (SELECT COUNT(*) FROM migrations WHERE migration = '2026_10_28_000001_create_observatorio_envios_table') = 0, 1
    UNION ALL
    SELECT 8, 'Jobs pendientes en la cola (detalle en la consulta 2; el worker los ejecutará)', '0',
           CAST((SELECT COUNT(*) FROM jobs) AS CHAR), (SELECT COUNT(*) FROM jobs) = 0, 0
    UNION ALL
    SELECT 9, 'Sílabos aprobados en el alcance (isil, tipo_plantilla 1 y 2, estados 5, 6, 18, 19)', 'más de 0 (en local: 644 familias)',
           CONCAT((SELECT COUNT(*) FROM silabos WHERE deleted_at IS NULL AND estado_id IN (5, 6, 18, 19) AND tipo_plantilla IN (1, 2)), ' sílabos, ',
                  (SELECT COUNT(DISTINCT COALESCE(NULLIF(codigo_familia, ''), codigo)) FROM silabos
                   WHERE deleted_at IS NULL AND estado_id IN (5, 6, 18, 19) AND tipo_plantilla IN (1, 2)), ' familias'),
           (SELECT COUNT(*) FROM silabos WHERE deleted_at IS NULL AND estado_id IN (5, 6, 18, 19) AND tipo_plantilla IN (1, 2)) > 0, 0
) r
ORDER BY r.n;
