-- =====================================================================
-- 04_ranking_technologies_verificacion.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Solo lectura. Confirma que 01, 02 y 05 quedaron aplicados y, después de
--   `php artisan ranking:refresh-technologies`, que los datos son coherentes.
--
-- REQUISITOS / TIEMPO / BLOQUEOS
--   MySQL 8.0+. 1-3 segundos (el EXPLAIN no ejecuta la consulta). No bloquea.
-- =====================================================================

-- 1. Tablas. Esperado: las 8 con existe = 'SI'.
SELECT t.tabla, IF(i.table_name IS NULL, 'NO', 'SI') AS existe, i.engine, i.table_collation
  FROM (SELECT 'technology_rankings' AS tabla UNION ALL SELECT 'technology_ranking_meta'
        UNION ALL SELECT 'technology_ranking_weekly' UNION ALL SELECT 'technology_ranking_weekly_meta'
        UNION ALL SELECT 'cache' UNION ALL SELECT 'cache_locks'
        UNION ALL SELECT 'jobs' UNION ALL SELECT 'failed_jobs') t
  LEFT JOIN information_schema.tables i ON i.table_schema = DATABASE() AND i.table_name = t.tabla;

-- 2. Índice de technology_job y columnas de activos. Esperado: todo 'SI'.
SELECT 'technology_job.idx_tj_job_entity' AS objeto,
       IF(COUNT(*) > 0, 'SI', 'NO') AS existe
  FROM information_schema.statistics
 WHERE table_schema = DATABASE() AND table_name = 'technology_job' AND index_name = 'idx_tj_job_entity'
UNION ALL
SELECT CONCAT('market_entities.', c.col), IF(i.column_name IS NULL, 'NO', 'SI')
  FROM (SELECT 'is_active' AS col UNION ALL SELECT 'inactive_reason' UNION ALL SELECT 'inactive_at') c
  LEFT JOIN information_schema.columns i
         ON i.table_schema = DATABASE() AND i.table_name = 'market_entities' AND i.column_name = c.col;

SHOW CREATE TABLE `technology_rankings`;
SHOW CREATE TABLE `technology_ranking_weekly_meta`;
SHOW INDEX FROM `technology_job`;

-- 3. El cálculo usa el índice. Esperado: fila tj con key = idx_tj_job_entity y
--    Extra "Using index" (sin leer las filas de technology_job).
EXPLAIN
SELECT STRAIGHT_JOIN tj.market_entity_id, COUNT(DISTINCT tj.job_offer_id) AS offers
  FROM job_offers j
  JOIN technology_job tj ON tj.job_offer_id = j.id
 WHERE j.published_at >= '2026-07-01' AND j.published_at < '2027-01-01'
 GROUP BY tj.market_entity_id;

-- 4. (Tras la carga) Semestres calculados.
SELECT year, period, rows_count, vacantes_analizadas, reportes_analizados, max_labor, max_trend, calculated_at
  FROM technology_ranking_meta ORDER BY year, period;

-- 5. (Tras la carga) Ninguna entidad inactiva en los resúmenes. Esperado: 0 filas.
SELECT 'technology_rankings' AS tabla, CONCAT(r.year, '-', r.period) AS ventana, me.name
  FROM technology_rankings r JOIN market_entities me ON me.id = r.market_entity_id WHERE me.is_active = 0
UNION ALL
SELECT 'technology_ranking_weekly', w.period_key, me.name
  FROM technology_ranking_weekly w JOIN market_entities me ON me.id = w.market_entity_id WHERE me.is_active = 0;

-- 6. (Tras la carga) Ventanas de la evolución. Esperado: weekly 7 días; biweekly 13-16;
--    monthly 28-31; una en curso por tipo.
SELECT period_type, COUNT(*) AS ventanas, MIN(DATEDIFF(end_date, start_date) + 1) AS dias_min,
       MAX(DATEDIFF(end_date, start_date) + 1) AS dias_max, SUM(is_partial) AS en_curso,
       SUM(is_semester_start) AS inicios_semestre, MAX(cutoff_date) AS ultimo_corte
  FROM technology_ranking_weekly_meta GROUP BY period_type;

-- 7. (Tras la carga) La última foto semanal coincide con el ranking del semestre.
--    Esperado: 0 filas.
SELECT w.name, w.jobs, r.total_jobs, w.trend_reports, r.trend_reports, w.labor_score, r.labor_score
  FROM technology_ranking_weekly w
  JOIN (SELECT period_key FROM technology_ranking_weekly_meta
         WHERE period_type = 'weekly' ORDER BY start_date DESC LIMIT 1) u ON u.period_key = w.period_key
  JOIN technology_rankings r
    ON r.market_entity_id = w.market_entity_id
   AND r.year = CAST(LEFT(w.semester, 4) AS UNSIGNED) AND r.period = RIGHT(w.semester, 2)
 WHERE w.period_type = 'weekly'
   AND (w.jobs <> r.total_jobs OR w.trend_reports <> r.trend_reports
        OR ABS(w.labor_score - r.labor_score) > 0.0001 OR ABS(w.trend_score - r.trend_score) > 0.0001);
