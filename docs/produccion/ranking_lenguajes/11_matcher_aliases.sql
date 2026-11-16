-- =====================================================================
-- 11_matcher_aliases.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE (Fase B: matcher de entidades)
--   1. Crea market_entity_aliases: reglas con las que el matcher
--      (App\Services\Matching\EntityTextMatcher) decide si una oferta menciona
--      una entidad. Tabla común a todos los tipos (lenguajes, tecnologías,
--      certificaciones…):
--        kind = alias     texto que confirma la entidad (case_sensitive, is_weak)
--        kind = negative  contexto que anula el hallazgo ("R&D", "Go-to", "Swift code")
--        kind = context   lo que necesita un alias débil (is_weak = 1)
--        window_chars     caracteres alrededor del hallazgo (NULL = toda la oferta)
--      pattern usa utf8mb4_bin: "Go" y "go" son reglas distintas.
--   2. Carga las reglas de los lenguajes (notes = '[seed lenguajes]').
--      Generadas desde database/data/entity_aliases/languages.php con
--      docs/produccion/herramientas/generar_aliases_sql.php: no editar a mano
--      el bloque generado; cambiar el archivo PHP y regenerar.
--
-- REQUISITOS
--   MySQL 8.0+. 09_ranking_languages_entidades.sql aplicado (slug 'fsharp').
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s. Tabla nueva; no bloquea tablas existentes.
--
-- RE-EJECUCIÓN
--   Idempotente: CREATE TABLE IF NOT EXISTS; las reglas sembradas se borran
--   y se vuelven a insertar (las creadas a mano se conservan).
--
-- REVERSIÓN
--   12_matcher_aliases_rollback.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `market_entity_aliases` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `market_entity_id` BIGINT UNSIGNED NOT NULL,
  `kind`             ENUM('alias','negative','context') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alias',
  `pattern`          VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `case_sensitive`   TINYINT    NOT NULL DEFAULT 0,
  `is_regex`         TINYINT    NOT NULL DEFAULT 0,
  `is_weak`          TINYINT    NOT NULL DEFAULT 0,
  `window_chars`     SMALLINT UNSIGNED NULL,
  `notes`            VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mea_rule` (`market_entity_id`, `kind`, `pattern`, `case_sensitive`),
  CONSTRAINT `fk_mea_entity` FOREIGN KEY (`market_entity_id`) REFERENCES `market_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SELECT 'market_entity_aliases lista' AS resultado;

START TRANSACTION;

-- ===================== INICIO DEL BLOQUE GENERADO =====================
-- Generado por docs/produccion/herramientas/generar_aliases_sql.php languages language lenguajes
-- 28 entidades. Las entidades que no existan en este entorno se omiten solas (JOIN por slug).
DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'language' AND a.notes = '[seed lenguajes]';
SELECT CONCAT('Reglas sembradas anteriores borradas: ', ROW_COUNT()) AS resultado;

-- sql
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SQL', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'sql';

-- java
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Java', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Java Script', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'java';

-- javascript
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'JavaScript', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Java Script', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ECMAScript', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'JS', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'javascript';

-- typescript
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'TypeScript', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'typescript';

-- python
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Python', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'python';

-- c
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'C#', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'C Sharp', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CSharp', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'c';

-- c-plus-plus
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'C++', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'C plus plus', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'cpp', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'c-plus-plus';

-- php
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'PHP', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'php';

-- css
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CSS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CSS3', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'css';

-- html
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'HTML', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'HTML5', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'html';

-- kotlin
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Kotlin', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'kotlin';

-- matlab
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'MATLAB', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'matlab';

-- json
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'JSON', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'json';

-- xml
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'XML', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'xml';

-- scala
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Scala', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SCALA', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'scala', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Spark', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'Java', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'Akka', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'Kotlin', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'Python', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'big data', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'Hadoop', 0, 0, 0, 40
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'scala';

-- perl
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Perl', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'perl';

-- cobol
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'COBOL', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'cobol';

-- lua
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Lua', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'lua';

-- vba
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VBA', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Visual Basic for Applications', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'vba';

-- fsharp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'F#', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'FSharp', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'fsharp';

-- rust
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Rust', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'RUST', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Rustlang', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Rust Belt', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'rust';

-- r
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'R', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'RStudio', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'R programming', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'R language', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'lenguaje R', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'R Shiny', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'tidyverse', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ggplot2', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'R+D', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'R$', 1, 0, 0, NULL
    UNION ALL SELECT 'negative', '(R)', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'R/3', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'd.R.', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'SAP R', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', '[A-Z]+&R|R&[A-Z]+', 1, 1, 0, NULL
    UNION ALL SELECT 'negative', 'R ?-?\\d+', 1, 1, 0, NULL
    UNION ALL SELECT 'context', 'statistics', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'statistical', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'estadística', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'estadístico', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'data', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'datos', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'analytics', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'analítica', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'machine learning', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'Python', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'SAS', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'Stata', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'SPSS', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'SQL', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'MATLAB', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'econometrics', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'econometría', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'biostatistics', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'Shiny', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'CRAN', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'programming', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'programación', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'r';

-- go
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Go', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'Golang', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go to', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go-to-market', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go live', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go/No-Go', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'No Go', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go getter', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Let''s Go', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go ahead', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go beyond', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go above', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'On the Go', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Pokémon Go', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go big', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go far', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go the extra mile', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go out', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go back', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go through', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go Intérim', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go deeper', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Go Direct', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', '[„"“'']Go[“"”'']', 1, 1, 0, NULL
    UNION ALL SELECT 'context', 'developer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'desarrollador', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'desarrolladora', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'engineer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ingeniero', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ingeniera', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programming', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programmer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programador', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'backend', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'back-end', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'software', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'coding', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'language', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'lenguaje', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'microservices', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'microservicios', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Python', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Java', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Rust', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Kubernetes', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Docker', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'gRPC', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Node.js', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'C++', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Scala', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Kotlin', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'TypeScript', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'development', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'desarrollo', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'SRE', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'DevOps', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'React', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'API', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'APIs', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Linux', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Fullstack', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Full Stack', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'services', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'servicios', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Développeur', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Développeuse', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Développement', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'logiciel', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'iOS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Android', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'IA', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'AI', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'go';

-- swift
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Swift', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'SwiftUI', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SWIFT', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'SWIFT messaging', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'SWIFT network', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'SWIFT MT', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'SEPA', 0, 0, 0, 40
    UNION ALL SELECT 'negative', 'LS-SWIFT', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'SWIFT Emergency', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'BIC', 0, 0, 0, 20
    UNION ALL SELECT 'negative', 'Target2', 0, 0, 0, 60
    UNION ALL SELECT 'negative', 'Swift/BIC', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Swift BIC', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Swift transfer', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Swift payments', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Taylor Swift', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'MT103', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'iOS', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'Xcode', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'Objective-C', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'Apple', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'macOS', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'UIKit', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'Kotlin', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'mobile', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'móvil', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'iPhone', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'iPad', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'developer', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'desarrollador', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'desarrolladora', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'engineer', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'ingeniero', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'ingeniera', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programming', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programación', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programmer', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programador', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'backend', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'back-end', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'software', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'coding', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'language', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'lenguaje', 0, 0, 0, 120
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'swift';

-- dax
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'DAX', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'DAX index', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'DAX 40', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'DAX 30', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'DAX companies', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'DAX-listed', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'DAX-Konzern', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'DAX-Konzerne', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'DAX-Unternehmen', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'Power BI', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'PowerBI', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'Power Pivot', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'SSAS', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'Tabular', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'Analysis Services', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'Excel', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'Fabric', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'Power Query', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'M Query', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'Business Intelligence', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'BI', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'data modelling', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'data modeling', 0, 0, 0, 150
    UNION ALL SELECT 'context', 'data model', 0, 0, 0, 150
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'dax';

-- ruby
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Ruby', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'Ruby on Rails', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'RoR', 1, 0, 0, NULL
    UNION ALL SELECT 'context', 'Rails', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'RSpec', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'Sinatra', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'gems', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'Python', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'JavaScript', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'PHP', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'Elixir', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'developer', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'desarrollador', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'desarrolladora', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'engineer', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'ingeniero', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'ingeniera', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programming', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programación', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programmer', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'programador', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'backend', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'back-end', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'software', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'coding', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'language', 0, 0, 0, 120
    UNION ALL SELECT 'context', 'lenguaje', 0, 0, 0, 120
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'ruby';

-- shell
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Shell scripting', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Shell script', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Shell scripts', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Bash', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Bash scripting', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'sh', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'Zsh', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'script', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'scripting', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'Linux', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Unix', 0, 0, 0, 60
    UNION ALL SELECT 'negative', 'PowerShell', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'shell';

-- ada
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Ada', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'Ada 95', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Ada 2005', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Ada 2012', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Ada programming', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Ada Lovelace', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Ada County', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'SPARK', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'avionics', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'aviónica', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'embedded', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'embebido', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'DO-178', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'safety-critical', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'real-time', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'C++', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'developer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'desarrollador', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'desarrolladora', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'engineer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ingeniero', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ingeniera', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programming', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programmer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'programador', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'backend', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'back-end', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'software', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'coding', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'language', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'lenguaje', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'ada';

SELECT CONCAT('Reglas sembradas: ', COUNT(*), ' (esperadas 298)') AS resultado
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'language' AND a.notes = '[seed lenguajes]';
-- ====================== FIN DEL BLOQUE GENERADO =======================

COMMIT;

-- Verificación: reglas por lenguaje
SELECT me.name, me.is_active,
       SUM(a.kind = 'alias') AS alias, SUM(a.kind = 'negative') AS negativos, SUM(a.kind = 'context') AS contextos
  FROM market_entities me
  LEFT JOIN market_entity_aliases a ON a.market_entity_id = me.id
 WHERE me.entity_type = 'language'
 GROUP BY me.id, me.name, me.is_active
 ORDER BY me.is_active DESC, me.name;

SELECT 'OK 11: matcher listo (tabla y reglas de lenguajes)' AS resultado;
