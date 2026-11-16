"""
Genera docs/produccion/DESPLIEGUE_RANKINGS_PRODUCCION.sql y DESPLIEGUE_RANKINGS_ROLLBACK.sql
a partir de los scripts por indicador (fuente única: las carpetas ranking_*).

Uso, desde la raíz del proyecto:  python docs/produccion/herramientas/generar_despliegue_consolidado.py

Reglas del archivo de despliegue (lo exige producción):
  - sin limpieza de asociaciones (ni sus tablas de trabajo);
  - sin DELETE, TRUNCATE ni DROP: las partes que los usaban se transforman
      * Lenguajes 06: la tabla de evolución con estructura antigua se RENOMBRA (no se borra);
      * alias del matcher: sin el DELETE previo (INSERT IGNORE ya es idempotente);
      * Certificaciones 05 y 12: tablas derivadas en lugar de tablas temporales; sin el paso 4
        (copia de asociaciones desde tecnologías, solo tiene sentido con limpieza);
  - sin DELIMITER ni procedimientos (PREPARE/EXECUTE con information_schema);
  - idempotente.
"""
import os
import re
import sys

ROOT = 'docs/produccion/'
L, T, C = ROOT + 'ranking_lenguajes/', ROOT + 'ranking_tecnologias/', ROOT + 'ranking_certificaciones/'

DEPLOY = [
    ('Lenguajes 01 — tablas de resumen', L + '01_ranking_languages_tablas.sql'),
    ('Lenguajes 02 — índices', L + '02_ranking_languages_indices.sql'),
    ('Lenguajes 05 — market_entities.is_active (fuente única de entidad activa)', L + '05_ranking_languages_activos.sql'),
    ('Lenguajes 06 — tablas de evolución (fotos por semana/quincena/mes)', L + '06_ranking_languages_evolucion.sql'),
    ('Lenguajes 09 — entidades (Shell/Ada inactivos, slug de F#, Go/Rust/Scala, GNU/Linux y CLI)', L + '09_ranking_languages_entidades.sql'),
    ('Lenguajes 11 — matcher: tabla market_entity_aliases, reglas de lenguajes y filas nuevas de languages', L + '11_matcher_aliases.sql'),
    ('Tecnologías 05 — is_active (repaso idempotente)', T + '05_ranking_technologies_activos.sql'),
    ('Tecnologías 01 — tablas de resumen', T + '01_ranking_technologies_tablas.sql'),
    ('Tecnologías 02 — índices', T + '02_ranking_technologies_indices.sql'),
    ('Tecnologías 08 — carreras en market_entity_career', T + '08_ranking_technologies_carreras.sql'),
    ('Tecnologías 06 — entidades (140 desactivadas, incluidos 41 duplicados)', T + '06_ranking_technologies_entidades.sql'),
    ('Tecnologías 10 — reglas del matcher y Transformers', T + '10_matcher_aliases_tecnologias.sql'),
    ('Certificaciones 01 — tablas de resumen', C + '01_ranking_certifications_tablas.sql'),
    ('Certificaciones 02 — índices', C + '02_ranking_certifications_indices.sql'),
    ('Certificaciones 05 — entidades (duplicados, CompTIA A+, catálogo)', C + '05_ranking_certifications_entidades.sql'),
    ('Certificaciones 07 — reglas del matcher', C + '07_matcher_aliases_certificaciones.sql'),
    ('Certificaciones 12 — términos de búsqueda (keywords)', C + '12_keywords_busqueda_certificaciones.sql'),
]

ROLLBACK = [
    ('Certificaciones 13 — keywords', C + '13_keywords_busqueda_certificaciones_rollback.sql'),
    ('Certificaciones 08 — reglas del matcher', C + '08_matcher_aliases_certificaciones_rollback.sql'),
    ('Certificaciones 06 — entidades', C + '06_ranking_certifications_entidades_rollback.sql'),
    ('Certificaciones 03 — tablas e índice', C + '03_ranking_certifications_rollback.sql'),
    ('Tecnologías 11 — reglas del matcher y Transformers', T + '11_matcher_aliases_tecnologias_rollback.sql'),
    ('Tecnologías 07 — entidades', T + '07_ranking_technologies_entidades_rollback.sql'),
    ('Tecnologías 09 — carreras', T + '09_ranking_technologies_carreras_rollback.sql'),
    ('Tecnologías 03 — tablas e índice', T + '03_ranking_technologies_rollback.sql'),
    ('Lenguajes 12 — matcher (tabla compartida) y filas nuevas de languages', L + '12_matcher_aliases_rollback.sql'),
    ('Lenguajes 10 — entidades', L + '10_ranking_languages_entidades_rollback.sql'),
    ('Lenguajes 07 — evolución e is_active', L + '07_ranking_languages_rollback_fase_a.sql'),
    ('Lenguajes 03 — tablas e índices', L + '03_ranking_languages_rollback.sql'),
]


def read(path):
    return open(path, encoding='utf-8').read()


def body(sql):
    """Quita la cabecera de comentarios inicial (el bloque explicativo de cada script)."""
    lines = sql.split('\n')
    i = 0
    while i < len(lines) and (lines[i].startswith('--') or lines[i].strip() == ''):
        i += 1
    return '\n'.join(lines[i:]).strip() + '\n'


def must(s, old, new, count=1):
    if s.count(old) != count:
        sys.exit(f'ERROR: {s.count(old)} coincidencias (esperadas {count}) de: {old[:80]!r}')
    return s.replace(old, new)


def lit(v):
    return "'" + v.replace("\\", "\\\\").replace("'", "''") + "'"


def derived(rows, cols):
    """Tabla derivada con literales en utf8mb4_unicode_ci (sin tablas temporales)."""
    first = ', '.join(f"_utf8mb4{lit(v)} COLLATE utf8mb4_unicode_ci AS {c}" for v, c in zip(rows[0], cols))
    rest = '\n'.join('    UNION ALL SELECT ' + ', '.join(lit(v) for v in r) for r in rows[1:])
    return f"(\n    SELECT {first}\n{rest}\n  )"


def transform(path, sql):
    name = os.path.basename(path)
    if name == '06_ranking_languages_evolucion.sql':
        # Estructura antigua (sin cutoff_date): se renombra en lugar de borrarse
        for t in ('language_ranking_weekly', 'language_ranking_weekly_meta'):
            sql = must(sql, f"SET @sql = IF(@vieja > 0, 'DROP TABLE `{t}`', 'DO 0');",
                       f"SET @sql = IF(@vieja > 0, 'RENAME TABLE `{t}` TO `{t}_anterior_2026_11`', 'DO 0');")
            sql = must(sql, f"SELECT CONCAT('{t}: ', IF(@vieja > 0, 'estructura anterior ELIMINADA, se recrea', 'sin versión anterior')) AS resultado;",
                       f"SELECT CONCAT('{t}: ', IF(@vieja > 0, 'estructura anterior RENOMBRADA a {t}_anterior_2026_11, se crea la nueva', 'sin versión anterior')) AS resultado;")
    if 'matcher_aliases' in name:
        sql, n = re.subn(r"DELETE a FROM market_entity_aliases a JOIN market_entities me ON me\.id = a\.market_entity_id\n WHERE [^;]*;\nSELECT CONCAT\('Reglas sembradas anteriores borradas: ', ROW_COUNT\(\)\) AS resultado;\n",
                         "-- (Sin borrado previo: INSERT IGNORE no duplica reglas al ejecutar dos veces)\n", sql)
        if n != 1:
            sys.exit(f'ERROR {name}: DELETE de reglas sembradas no encontrado ({n})')
    if name == '06_ranking_technologies_entidades.sql':
        # Transformers: el despliegue lo deja activo (Tecnologías 10 le da reglas estrictas). Sin esta
        # desactivación la segunda ejecución no cambia nada (antes: se desactivaba y se reactivaba).
        sql, n = re.subn(r"-- Ambiguo: se reactiva en la Fase 3 con alias estrictos \(1\)\nUPDATE market_entities\n.*?\);\nSELECT CONCAT\('Ambiguo: [^;]*;\n",
                         "-- (Transformers no se desactiva: queda activo con las reglas estrictas de la parte de Tecnologías 10)\n", sql, count=1, flags=re.S)
        if n != 1:
            sys.exit('ERROR: bloque Ambiguo (Transformers) no encontrado')
    if name == '05_ranking_certifications_entidades.sql':
        m = re.search(r"INSERT INTO `_dup_cert` VALUES\n(.*?);\n", sql, re.S)
        pairs = re.findall(r"\('([^']*)', '([^']*)'\)", m.group(1))
        sql = re.sub(r"DROP TEMPORARY TABLE IF EXISTS `_dup_cert`;\nCREATE TEMPORARY TABLE `_dup_cert` .*?;\n.*?INSERT INTO `_dup_cert` VALUES\n.*?;\n", '', sql, count=1, flags=re.S)
        sql = sql.replace('`_dup_cert` x', derived(pairs, ['dup', 'principal']) + ' x')
        # Paso 4 (copia desde tecnologías): solo tiene sentido con la limpieza → fuera
        a = sql.index('-- ---------------------------------------------------------------------\n-- 4. Asociaciones')
        b = sql.index('COMMIT;', a)
        sql = sql[:a] + '-- (Paso 4, copia de asociaciones desde tecnologías: excluido; solo tiene sentido con la limpieza)\n\n' + sql[b:]
        sql = re.sub(r"DROP TEMPORARY TABLE IF EXISTS `_[a-z_]+`;\n", '', sql)
        if '_dup_cert' in sql:
            sys.exit('ERROR: quedó una referencia a _dup_cert')
    if name == '12_keywords_busqueda_certificaciones.sql':
        m = re.search(r"INSERT INTO `_kw_cert` \(slug, terms\) VALUES\n(.*?);\n", sql, re.S)
        rows = re.findall(r"\('((?:[^']|'')*)', '((?:[^']|'')*)'\)", m.group(1))
        rows = [(a.replace("''", "'"), b.replace("''", "'")) for a, b in rows]
        sql = re.sub(r"DROP TEMPORARY TABLE IF EXISTS `_kw_cert`;\nCREATE TEMPORARY TABLE `_kw_cert` \(.*?\) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\nINSERT INTO `_kw_cert` \(slug, terms\) VALUES\n.*?;\n", '', sql, count=1, flags=re.S)
        sql = sql.replace('JOIN `_kw_cert` k', 'JOIN ' + derived(rows, ['slug', 'terms']) + ' k')
        sql = re.sub(r"DROP TEMPORARY TABLE IF EXISTS `_kw_cert`;\n", '', sql)
    return sql


DEPLOY_HEADER = """-- =====================================================================
-- DESPLIEGUE_RANKINGS_PRODUCCION.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Prepara la base para los rankings de Lenguajes, Tecnologías y
--   Certificaciones del Observatorio (archivo único para el DBA):
--     - tablas de resumen y de evolución de los tres rankings;
--     - índices para calcularlos (en línea, ALGORITHM=INPLACE, LOCK=NONE);
--     - market_entities.is_active / inactive_reason / inactive_at (entidad activa);
--     - ajuste de entidades: desactiva organizaciones, conceptos, duplicados, etc.
--       (UPDATE is_active = 0; no se borra ninguna entidad);
--     - carreras de tecnologías en market_entity_career;
--     - tabla market_entity_aliases con las reglas del matcher (1 607 reglas);
--     - filas nuevas de catálogo (languages, certifications) y keywords de búsqueda.
--   Tablas de respaldo que crea (para la reversión): _respaldo_lenguajes_2026_11,
--   _respaldo_certificaciones_2026_11, _respaldo_keywords_cert_2026_11.
--
-- QUÉ NO HACE
--   - NO borra datos: no contiene DELETE, TRUNCATE ni DROP. Si existiera la versión
--     antigua de language_ranking_weekly(_meta), se RENOMBRA a *_anterior_2026_11.
--   - NO incluye la limpieza de asociaciones (language_job, technology_job,
--     certification_job quedan como están). Por eso tampoco reasigna las ofertas
--     de las entidades duplicadas a su principal: siguen asociadas al duplicado,
--     que queda inactivo y no cuenta en el ranking.
--
-- REQUISITOS
--   MySQL 8.0.13 o superior (ALTER ... ALGORITHM=INPLACE, LOCK=NONE; JSON; ROW_NUMBER
--   en la lectura de la aplicación). Ejecutar sobre la base del Observatorio.
--   Funciona en el cliente mysql, MySQL Workbench y HeidiSQL: no usa DELIMITER ni
--   procedimientos almacenados.
--
-- ANTES DE EJECUTAR
--   - Respaldo de la base (recomendado aunque el script no borra datos).
--   - NO ejecutar mientras corra `php artisan jobs:run-all` ni ningún scraper: los
--     ALTER de índices esperan a las transacciones largas.
--
-- TIEMPO ESTIMADO
--   1-3 minutos (en local, con 2,2 M filas en technology_job: ~1 min, casi todo en
--   los índices). Crece de forma lineal con el tamaño de language_job / technology_job.
--
-- RE-EJECUCIÓN
--   Idempotente: se puede ejecutar dos veces; la segunda no cambia nada (los
--   mensajes dirán "YA EXISTE" y "0 filas"). Las "Note 1050" de CREATE TABLE IF NOT
--   EXISTS y las "Warning 1681" (TINYINT(1)) son esperadas.
--
-- DESPUÉS (en el servidor de la aplicación, con el código nuevo desplegado)
--   php artisan optimize:clear
--   php artisan ranking:refresh-languages
--   php artisan ranking:refresh-technologies
--   php artisan ranking:refresh-certifications
--   php artisan queue:restart
--
-- REVERSIÓN
--   DESPLIEGUE_RANKINGS_ROLLBACK.sql (desplegar antes el código anterior).
--
-- Archivo generado con docs/produccion/herramientas/generar_despliegue_consolidado.py
-- a partir de los scripts de ranking_lenguajes/, ranking_tecnologias/ y ranking_certificaciones/.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

"""

VERIFY = """
-- =====================================================================
-- VERIFICACIÓN (solo lectura) — resultado esperado en cada consulta
-- =====================================================================

-- 1. Tablas creadas. Esperado: 15 filas con existe = 'SI'.
SELECT t.tabla, IF(i.table_name IS NULL, 'NO', 'SI') AS existe
  FROM (SELECT 'language_rankings' AS tabla UNION ALL SELECT 'language_ranking_meta'
        UNION ALL SELECT 'language_ranking_weekly' UNION ALL SELECT 'language_ranking_weekly_meta'
        UNION ALL SELECT 'technology_rankings' UNION ALL SELECT 'technology_ranking_meta'
        UNION ALL SELECT 'technology_ranking_weekly' UNION ALL SELECT 'technology_ranking_weekly_meta'
        UNION ALL SELECT 'certification_rankings' UNION ALL SELECT 'certification_ranking_meta'
        UNION ALL SELECT 'certification_ranking_weekly' UNION ALL SELECT 'certification_ranking_weekly_meta'
        UNION ALL SELECT 'market_entity_aliases' UNION ALL SELECT 'cache' UNION ALL SELECT 'jobs') t
  LEFT JOIN information_schema.tables i ON i.table_schema = DATABASE() AND i.table_name = t.tabla;

-- 2. Índices y columnas. Esperado: 11 filas con existe = 'SI'.
SELECT x.objeto, IF(COUNT(s.index_name) > 0, 'SI', 'NO') AS existe
  FROM (SELECT 'language_job' AS tbl, 'idx_lj_job_entity' AS idx, 'language_job.idx_lj_job_entity' AS objeto
        UNION ALL SELECT 'technology_job', 'idx_tj_job_entity', 'technology_job.idx_tj_job_entity'
        UNION ALL SELECT 'certification_job', 'idx_cj_job_entity', 'certification_job.idx_cj_job_entity'
        UNION ALL SELECT 'job_offers', 'idx_jo_published_at', 'job_offers.idx_jo_published_at'
        UNION ALL SELECT 'job_offers', 'idx_jo_created_at', 'job_offers.idx_jo_created_at'
        UNION ALL SELECT 'job_offers', 'idx_jo_source_created', 'job_offers.idx_jo_source_created'
        UNION ALL SELECT 'scraper_runs', 'idx_scraper_runs_entity_finished', 'scraper_runs.idx_scraper_runs_entity_finished'
        UNION ALL SELECT 'market_entities', 'idx_market_entities_type_active', 'market_entities.idx_market_entities_type_active') x
  LEFT JOIN information_schema.statistics s
         ON s.table_schema = DATABASE() AND s.table_name = x.tbl AND s.index_name = x.idx
 GROUP BY x.objeto
UNION ALL
SELECT CONCAT('market_entities.', c.col), IF(i.column_name IS NULL, 'NO', 'SI')
  FROM (SELECT 'is_active' AS col UNION ALL SELECT 'inactive_reason' UNION ALL SELECT 'inactive_at') c
  LEFT JOIN information_schema.columns i
         ON i.table_schema = DATABASE() AND i.table_name = 'market_entities' AND i.column_name = c.col;
-- (Si un índice sale 'NO' pero el paso 2 de su sección dijo "YA EXISTE (con ese nombre o uno
--  equivalente)", existe con otro nombre y las mismas columnas: es correcto.)

-- 3. Entidades activas por tipo. Esperado (con los datos de local del 2026-11-16):
--    language 26, technology 313, certification 79. En producción puede variar si
--    las entidades difieren; lo importante es que inactivas > 0 en los tres tipos.
SELECT entity_type, SUM(is_active = 1) AS activas, SUM(is_active = 0) AS inactivas
  FROM market_entities
 WHERE entity_type IN ('language', 'technology', 'certification')
 GROUP BY entity_type;

-- 4. Reglas del matcher por tipo. Esperado: language 298, technology 976, certification 333
--    (algo menos si en producción no existe alguna entidad: las reglas se cargan por slug).
SELECT me.entity_type, COUNT(*) AS reglas
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 GROUP BY me.entity_type;

-- 5. Catálogos completos. Esperado: 0 y 0 (toda entidad activa tiene su fila habilitada).
SELECT 'languages sin fila' AS control, COUNT(*) AS n FROM market_entities me
 WHERE me.entity_type = 'language' AND me.is_active = 1
   AND NOT EXISTS (SELECT 1 FROM languages l WHERE l.market_entity_id = me.id AND l.enabled = 1)
UNION ALL
SELECT 'certifications sin fila', COUNT(*) FROM market_entities me
 WHERE me.entity_type = 'certification' AND me.is_active = 1
   AND NOT EXISTS (SELECT 1 FROM certifications c WHERE c.market_entity_id = me.id AND c.enabled = 1);

-- 6. Términos de búsqueda de certificaciones. Esperado: sin_terminos = 0.
SELECT COUNT(*) AS certificaciones_activas,
       SUM(c.keywords IS NULL OR JSON_LENGTH(c.keywords) = 0) AS sin_terminos
  FROM market_entities me JOIN certifications c ON c.market_entity_id = me.id AND c.enabled = 1
 WHERE me.entity_type = 'certification' AND me.is_active = 1;

-- 7. Las asociaciones existentes NO se tocaron (comparar con el conteo previo del DBA).
SELECT 'language_job' AS tabla, COUNT(*) AS filas FROM language_job
UNION ALL SELECT 'technology_job', COUNT(*) FROM technology_job
UNION ALL SELECT 'certification_job', COUNT(*) FROM certification_job;

SELECT 'OK: despliegue de los rankings terminado. Ahora: php artisan ranking:refresh-languages / -technologies / -certifications' AS resultado;
"""

ROLLBACK_HEADER = """-- =====================================================================
-- DESPLIEGUE_RANKINGS_ROLLBACK.sql
-- ---------------------------------------------------------------------
-- SOLO SI HACE FALTA REVERTIR. No se entrega con el despliegue.
--
-- QUÉ HACE
--   Deshace DESPLIEGUE_RANKINGS_PRODUCCION.sql en orden inverso: keywords y
--   catálogo de certificaciones, reglas del matcher (incluida la tabla
--   market_entity_aliases), entidades reactivadas con sus valores anteriores,
--   carreras de tecnologías, columnas is_active de market_entities, índices y las
--   tablas de resumen (datos calculados: se regeneran). Usa las tablas de respaldo
--   que creó el despliegue y las elimina al final.
--   Si el despliegue renombró language_ranking_weekly(_meta) a *_anterior_2026_11,
--   les devuelve su nombre.
--   No toca language_job / technology_job / certification_job.
--
-- ANTES
--   Desplegar el código anterior (el nuevo usa las columnas y tablas que se quitan).
--   No ejecutar mientras corra jobs:run-all.
--
-- REQUISITOS / TIEMPO
--   MySQL 8.0.13+. 1-2 minutos (quitar índices). Sin DELIMITER ni procedimientos.
--
-- RE-EJECUCIÓN
--   Idempotente.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

"""

RESTORE_OLD = """
-- ---------------------------------------------------------------------
-- Tablas de evolución renombradas por el despliegue: devolverles su nombre
-- ---------------------------------------------------------------------
SET @hay = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'language_ranking_weekly_anterior_2026_11')
         - (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'language_ranking_weekly');
SET @sql = IF(@hay > 0, 'RENAME TABLE `language_ranking_weekly_anterior_2026_11` TO `language_ranking_weekly`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @hay = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'language_ranking_weekly_meta_anterior_2026_11')
         - (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'language_ranking_weekly_meta');
SET @sql = IF(@hay > 0, 'RENAME TABLE `language_ranking_weekly_meta_anterior_2026_11` TO `language_ranking_weekly_meta`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'Tablas de evolución anteriores: restauradas si existían' AS resultado;
"""


def section(i, n, title, sql):
    return (f"\n-- #####################################################################\n"
            f"-- PARTE {i}/{n}: {title}\n"
            f"-- #####################################################################\n\n" + sql)


def build(items, header, transform_fn, footer):
    out = [header]
    for i, (title, path) in enumerate(items, 1):
        out.append(section(i, len(items), title, transform_fn(path, body(read(path)))))
    out.append(footer)
    return ''.join(out)


deploy = build(DEPLOY, DEPLOY_HEADER, transform, VERIFY)
rollback = build(ROLLBACK, ROLLBACK_HEADER, lambda p, s: s, RESTORE_OLD + "\nSELECT 'OK: reversión del despliegue de los rankings terminada' AS resultado;\n")

# Controles del archivo de despliegue: nada destructivo ni DELIMITER/procedimientos (tampoco en comentarios)
bad = re.findall(r'(?im)^(?!\s*--).*\b(DELETE|TRUNCATE|DROP|DELIMITER|PROCEDURE)\b.*$', deploy)
if bad:
    sys.exit('ERROR: el despliegue contiene sentencias prohibidas: ' + repr(bad[:5]))
for word in ('_limpieza', 'respaldo_limpieza', 'clean-associations'):
    if word in deploy and word != 'clean-associations':
        sys.exit(f'ERROR: el despliegue menciona {word}')

open(ROOT + 'DESPLIEGUE_RANKINGS_PRODUCCION.sql', 'w', encoding='utf-8', newline='\n').write(deploy)
open(ROOT + 'DESPLIEGUE_RANKINGS_ROLLBACK.sql', 'w', encoding='utf-8', newline='\n').write(rollback)
print('Generados:', ROOT + 'DESPLIEGUE_RANKINGS_PRODUCCION.sql', f'({deploy.count(chr(10))} líneas)', '|',
      ROOT + 'DESPLIEGUE_RANKINGS_ROLLBACK.sql', f'({rollback.count(chr(10))} líneas)')
