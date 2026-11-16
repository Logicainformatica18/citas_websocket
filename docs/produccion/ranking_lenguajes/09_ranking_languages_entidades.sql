-- =====================================================================
-- 09_ranking_languages_entidades.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE (decisiones de la Fase A de Lenguajes, confirmadas 2026-11-16)
--   1. Desactiva (market_entities.is_active = 0) dos entidades de tipo
--      'language' creadas por IA el 2026-11-12:
--        - Shell: hasta la Fase B (pasará a "Shell scripting" con alias Bash y sh).
--        - Ada:   nombre ambiguo; se evaluará con el matcher en la Fase B.
--      inactive_reason empieza con "[Lenguajes 2026-11]".
--   2. Corrige el slug de F#: 'f' -> 'fsharp' (Str::slug('F#') daba 'f').
--      market_entities.slug no se usa en URLs ni en otras tablas: solo para
--      evitar duplicados en languages:discover-gaps, que también compara por
--      nombre, así que F# no se vuelve a crear.
--   3. Vincula languages Go, Rust y Scala con sus entidades (por slug):
--      languages.market_entity_id estaba en NULL. Completa también las filas de
--      language_job de esos lenguajes que tengan market_entity_id en NULL.
--   4. languages GNU/Linux y CLI: enabled = 0 (no son lenguajes). NO se borran:
--      tienen cursos en course_language. Dejan de buscarse en los scrapers.
--   Perl, Ruby, VBA, Lua y COBOL quedan activos (no se tocan).
--
--   Cada valor que cambia se guarda antes en la tabla de respaldo
--   _respaldo_lenguajes_2026_11 (tabla, fila, columna, valor anterior) para que
--   10_ranking_languages_entidades_rollback.sql deje exactamente lo que había.
--   Se identifica por slug + entity_type y por languages.name (los ids pueden
--   variar entre entornos).
--
-- REQUISITOS
--   05_ranking_languages_activos.sql aplicado. MySQL 8.0+.
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s. Bloqueos de fila breves.
--
-- RE-EJECUCIÓN
--   Idempotente: cada UPDATE solo toca filas que aún no tienen el valor nuevo,
--   y el respaldo conserva el valor ORIGINAL (INSERT IGNORE).
--
-- DESPUÉS
--   php artisan ranking:refresh-languages
--
-- REVERSIÓN
--   10_ranking_languages_entidades_rollback.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `_respaldo_lenguajes_2026_11` (
  `tabla`          VARCHAR(64)  NOT NULL,
  `fila_id`        BIGINT UNSIGNED NOT NULL,
  `columna`        VARCHAR(64)  NOT NULL,
  `valor_anterior` VARCHAR(255) NULL,
  `respaldado_en`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabla`, `fila_id`, `columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Shell y Ada: desactivar con su motivo
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `_respaldo_lenguajes_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'market_entities', id, 'is_active', is_active
  FROM market_entities
 WHERE entity_type = 'language' AND slug IN ('shell', 'ada') AND is_active = 1;

UPDATE market_entities
   SET is_active = 0, inactive_at = NOW(),
       inactive_reason = '[Lenguajes 2026-11] Desactivado hasta la Fase B: pasará a "Shell scripting" con alias Bash y sh'
 WHERE entity_type = 'language' AND slug = 'shell' AND is_active = 1;
SELECT CONCAT('Shell desactivado: ', ROW_COUNT(), ' fila(s)') AS resultado;

UPDATE market_entities
   SET is_active = 0, inactive_at = NOW(),
       inactive_reason = '[Lenguajes 2026-11] Nombre ambiguo; se evaluará con el matcher en la Fase B'
 WHERE entity_type = 'language' AND slug = 'ada' AND is_active = 1;
SELECT CONCAT('Ada desactivado: ', ROW_COUNT(), ' fila(s)') AS resultado;

-- ---------------------------------------------------------------------
-- 2. F#: slug 'f' -> 'fsharp' (solo si 'fsharp' no existe ya)
-- ---------------------------------------------------------------------
SET @fsharp_existe = (SELECT COUNT(*) FROM market_entities WHERE entity_type = 'language' AND slug = 'fsharp');

INSERT IGNORE INTO `_respaldo_lenguajes_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'market_entities', id, 'slug', slug
  FROM market_entities
 WHERE entity_type = 'language' AND slug = 'f' AND name = 'F#' AND @fsharp_existe = 0;

UPDATE market_entities
   SET slug = 'fsharp'
 WHERE entity_type = 'language' AND slug = 'f' AND name = 'F#' AND @fsharp_existe = 0;
SELECT CONCAT('F# slug corregido: ', ROW_COUNT(), ' fila(s)',
              IF(@fsharp_existe > 0, ' (fsharp ya existe: aplicado antes)', '')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. Go, Rust, Scala: vincular languages con su entidad
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `_respaldo_lenguajes_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'languages', l.id, 'market_entity_id', NULL
  FROM languages l
  JOIN market_entities me ON me.entity_type = 'language' AND me.slug = LOWER(l.name)
 WHERE l.name IN ('Go', 'Rust', 'Scala') AND l.market_entity_id IS NULL;

UPDATE languages l
  JOIN market_entities me ON me.entity_type = 'language' AND me.slug = LOWER(l.name)
   SET l.market_entity_id = me.id
 WHERE l.name IN ('Go', 'Rust', 'Scala') AND l.market_entity_id IS NULL;
SELECT CONCAT('Go/Rust/Scala vinculados: ', ROW_COUNT(), ' fila(s)') AS resultado;

INSERT IGNORE INTO `_respaldo_lenguajes_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'language_job', lj.id, 'market_entity_id', NULL
  FROM language_job lj
  JOIN languages l ON l.id = lj.language_id
 WHERE l.name IN ('Go', 'Rust', 'Scala') AND l.market_entity_id IS NOT NULL AND lj.market_entity_id IS NULL;

UPDATE language_job lj
  JOIN languages l ON l.id = lj.language_id
   SET lj.market_entity_id = l.market_entity_id
 WHERE l.name IN ('Go', 'Rust', 'Scala') AND l.market_entity_id IS NOT NULL AND lj.market_entity_id IS NULL;
SELECT CONCAT('language_job de Go/Rust/Scala completadas: ', ROW_COUNT(), ' fila(s)') AS resultado;

-- ---------------------------------------------------------------------
-- 4. GNU/Linux y CLI: enabled = 0 (sin borrar)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `_respaldo_lenguajes_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'languages', id, 'enabled', enabled
  FROM languages
 WHERE name IN ('GNU/Linux', 'CLI') AND enabled = 1;

UPDATE languages SET enabled = 0, updated_at = NOW()
 WHERE name IN ('GNU/Linux', 'CLI') AND enabled = 1;
SELECT CONCAT('GNU/Linux y CLI deshabilitados: ', ROW_COUNT(), ' fila(s)') AS resultado;

COMMIT;

-- ---------------------------------------------------------------------
-- 5. Verificación
-- ---------------------------------------------------------------------
SELECT id, name, slug, is_active, inactive_reason
  FROM market_entities
 WHERE entity_type = 'language' AND slug IN ('perl','ruby','vba','lua','cobol','shell','ada','fsharp','f','go','rust','scala')
 ORDER BY name;

SELECT l.id, l.name, l.enabled, l.market_entity_id, me.slug
  FROM languages l LEFT JOIN market_entities me ON me.id = l.market_entity_id
 WHERE l.name IN ('Go','Rust','Scala','GNU/Linux','CLI')
 ORDER BY l.name;

SELECT tabla, columna, COUNT(*) AS filas_respaldadas
  FROM `_respaldo_lenguajes_2026_11` GROUP BY tabla, columna;

SELECT 'OK 09: entidades de lenguajes ajustadas' AS resultado;
