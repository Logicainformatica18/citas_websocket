-- =====================================================================
-- 08_ranking_languages_verificacion_fase_a.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Solo lectura. Confirma que 05 y 06 quedaron aplicados y, después de
--   `php artisan ranking:refresh-languages`, que IDC/OECD no aparecen y que
--   las semanas tienen 7 días.
--
-- REQUISITOS / TIEMPO / BLOQUEOS
--   MySQL 8.0+. Menos de 1 segundo. No bloquea nada.
-- =====================================================================

-- 1. Columnas e índice nuevos. Esperado: 3 columnas + 1 índice.
SELECT column_name AS objeto, column_type AS tipo, is_nullable, column_default
  FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'market_entities'
   AND column_name IN ('is_active', 'inactive_reason', 'inactive_at')
UNION ALL
SELECT DISTINCT index_name, 'INDEX', NULL, NULL
  FROM information_schema.statistics
 WHERE table_schema = DATABASE() AND table_name = 'market_entities'
   AND index_name = 'idx_market_entities_type_active';

-- 2. Entidades de tipo language inactivas. Esperado: al menos IDC y OECD.
SELECT id, name, slug, is_active, inactive_reason, inactive_at
  FROM market_entities
 WHERE entity_type = 'language' AND is_active = 0
 ORDER BY name;

-- 3. Tablas de evolución. Esperado: 2 filas con existe = 'SI'.
SELECT t.tabla, IF(i.table_name IS NULL, 'NO', 'SI') AS existe, i.table_collation
  FROM (SELECT 'language_ranking_weekly' AS tabla UNION ALL SELECT 'language_ranking_weekly_meta') t
  LEFT JOIN information_schema.tables i ON i.table_schema = DATABASE() AND i.table_name = t.tabla;

SHOW CREATE TABLE `language_ranking_weekly`;
SHOW CREATE TABLE `language_ranking_weekly_meta`;

-- 4. (Tras la carga) Ninguna entidad inactiva en los resúmenes. Esperado: 0 filas.
SELECT 'language_rankings' AS tabla, lr.year, lr.period AS ventana, me.name
  FROM language_rankings lr JOIN market_entities me ON me.id = lr.market_entity_id
 WHERE me.is_active = 0
UNION ALL
SELECT 'language_ranking_weekly', w.year, w.period_key, me.name
  FROM language_ranking_weekly w JOIN market_entities me ON me.id = w.market_entity_id
 WHERE me.is_active = 0;

-- 5. (Tras la carga) Ventanas por tipo. Esperado: weekly con dias = 7;
--    biweekly 13-16; monthly 28-31; como mucho 1 ventana en curso por tipo;
--    las de inicio de semestre (el acumulado se reinicia) en enero y julio.
SELECT period_type, COUNT(*) AS ventanas, MIN(DATEDIFF(end_date, start_date) + 1) AS dias_min,
       MAX(DATEDIFF(end_date, start_date) + 1) AS dias_max, SUM(is_partial) AS en_curso,
       SUM(is_semester_start) AS inicios_semestre, MIN(start_date) AS desde, MAX(cutoff_date) AS ultimo_corte
  FROM language_ranking_weekly_meta
 GROUP BY period_type;

-- 6. (Tras la carga) Últimas 5 semanas: corte, semestre, vacantes únicas
--    acumuladas / nuevas y top 3 (posición de referencia, pesos del cálculo).
SELECT m.period_key, m.label, m.cutoff_date, m.semester, m.is_semester_start AS inicio_sem,
       m.unique_offers AS unicas_acum, m.new_unique_offers AS unicas_nuevas,
       (SELECT GROUP_CONCAT(CONCAT(w.position, '. ', w.name, ' (', w.jobs, ')') ORDER BY w.position SEPARATOR ' | ')
          FROM language_ranking_weekly w
         WHERE w.period_type = m.period_type AND w.period_key = m.period_key AND w.position <= 3) AS top3
  FROM language_ranking_weekly_meta m
 WHERE m.period_type = 'weekly'
 ORDER BY m.start_date DESC
 LIMIT 5;

-- 7. (Tras la carga) La última foto debe coincidir con el ranking semestral
--    vigente (language_rankings). Esperado: 0 filas con diferencias.
SELECT w.name, w.jobs AS foto_vacantes, lr.total_jobs AS ranking_vacantes,
       w.trend_reports AS foto_reportes, lr.trend_reports AS ranking_reportes,
       w.labor_score AS foto_laboral, lr.labor_score AS ranking_laboral,
       w.trend_score AS foto_tend, lr.trend_score AS ranking_tend
  FROM language_ranking_weekly w
  JOIN (SELECT period_key FROM language_ranking_weekly_meta
         WHERE period_type = 'weekly' ORDER BY start_date DESC LIMIT 1) ult ON ult.period_key = w.period_key
  JOIN language_rankings lr
    ON lr.market_entity_id = w.market_entity_id
   AND lr.year = CAST(LEFT(w.semester, 4) AS UNSIGNED) AND lr.period = RIGHT(w.semester, 2)
 WHERE w.period_type = 'weekly'
   AND (w.jobs <> lr.total_jobs OR w.trend_reports <> lr.trend_reports
        OR ABS(w.labor_score - lr.labor_score) > 0.0001 OR ABS(w.trend_score - lr.trend_score) > 0.0001);
