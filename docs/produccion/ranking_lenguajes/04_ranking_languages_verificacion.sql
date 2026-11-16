-- =====================================================================
-- 04_ranking_languages_verificacion.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Solo lectura. Confirma que 01 y 02 quedaron aplicados.
--   Cada consulta indica qué resultado se espera.
--
-- REQUISITOS
--   MySQL 8.0 o superior. Ejecutar conectado a la base del observatorio.
--
-- TIEMPO ESTIMADO / BLOQUEOS
--   Menos de 1 segundo. No bloquea nada.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Versión de MySQL. Esperado: 8.0.x o superior.
-- ---------------------------------------------------------------------
SELECT VERSION() AS mysql_version;

-- ---------------------------------------------------------------------
-- 2. Resumen: tablas requeridas. Esperado: las 6 con existe = 'SI'.
-- ---------------------------------------------------------------------
SELECT t.tabla,
       IF(i.table_name IS NULL, 'NO', 'SI') AS existe,
       i.engine,
       i.table_collation
  FROM (SELECT 'language_rankings' AS tabla UNION ALL SELECT 'language_ranking_meta'
        UNION ALL SELECT 'cache' UNION ALL SELECT 'cache_locks'
        UNION ALL SELECT 'jobs' UNION ALL SELECT 'failed_jobs') t
  LEFT JOIN information_schema.tables i
         ON i.table_schema = DATABASE() AND i.table_name = t.tabla;

-- ---------------------------------------------------------------------
-- 3. Resumen: índices nuevos. Esperado: los 5 con existe = 'SI'
--    (idx_jo_published_at puede aparecer 'NO' si ya había otro índice
--    sobre published_at con otro nombre; la columna "equivalente" lo muestra).
-- ---------------------------------------------------------------------
SELECT e.tabla, e.indice, e.columnas,
       IF(s.idx_name IS NULL, 'NO', 'SI') AS existe,
       (SELECT GROUP_CONCAT(DISTINCT x.idx_name)
          FROM (SELECT table_name AS tbl, index_name AS idx_name,
                       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
                  FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                 GROUP BY table_name, index_name) x
         WHERE x.tbl = e.tabla
           AND (x.idx_cols = e.columnas OR x.idx_cols LIKE CONCAT(e.columnas, ',%'))) AS equivalente
  FROM (SELECT 'language_job' AS tabla, 'idx_lj_job_entity' AS indice, 'job_offer_id,market_entity_id' AS columnas
        UNION ALL SELECT 'job_offers',   'idx_jo_published_at',              'published_at'
        UNION ALL SELECT 'job_offers',   'idx_jo_created_at',                'created_at'
        UNION ALL SELECT 'job_offers',   'idx_jo_source_created',            'source,created_at'
        UNION ALL SELECT 'scraper_runs', 'idx_scraper_runs_entity_finished', 'entity,finished_at') e
  LEFT JOIN (SELECT DISTINCT table_name AS tbl, index_name AS idx_name
               FROM information_schema.statistics
              WHERE table_schema = DATABASE()) s
         ON s.tbl = e.tabla AND s.idx_name = e.indice;

-- ---------------------------------------------------------------------
-- 4. Definición completa de las tablas nuevas
-- ---------------------------------------------------------------------
SHOW CREATE TABLE `language_rankings`;
SHOW CREATE TABLE `language_ranking_meta`;

-- ---------------------------------------------------------------------
-- 5. Índices de cada tabla afectada
-- ---------------------------------------------------------------------
SHOW INDEX FROM `language_rankings`;
SHOW INDEX FROM `language_ranking_meta`;
SHOW INDEX FROM `language_job`;
SHOW INDEX FROM `job_offers`;
SHOW INDEX FROM `scraper_runs`;

-- ---------------------------------------------------------------------
-- 6. El cálculo del ranking usa los índices.
--    Esperado: fila j con key = idx_jo_published_at (o el índice sobre
--    published_at) y type = range; fila lj con key = idx_lj_job_entity y
--    Extra "Using index". NO debe aparecer type = ALL en lj.
-- ---------------------------------------------------------------------
EXPLAIN
SELECT STRAIGHT_JOIN lj.market_entity_id, COUNT(DISTINCT lj.job_offer_id) AS offers
  FROM job_offers j
  JOIN language_job lj ON lj.job_offer_id = j.id
 WHERE j.published_at BETWEEN '2026-01-01' AND '2026-06-30'
 GROUP BY lj.market_entity_id;

-- ---------------------------------------------------------------------
-- 7. Datos del ranking (después de `php artisan ranking:refresh-languages`).
--    Antes de la primera carga ambas consultas devuelven 0 filas: es normal.
-- ---------------------------------------------------------------------
SELECT year, period, rows_count, vacantes_analizadas, reportes_analizados,
       duration_ms, calculated_at
  FROM language_ranking_meta
 ORDER BY year, period;

SELECT year, period, COUNT(*) AS lenguajes, MAX(calculated_at) AS calculated_at
  FROM language_rankings
 GROUP BY year, period
 ORDER BY year, period;
