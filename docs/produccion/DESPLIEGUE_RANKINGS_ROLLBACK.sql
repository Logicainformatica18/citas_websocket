-- =====================================================================
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


-- #####################################################################
-- PARTE 1/12: Certificaciones 13 — keywords
-- #####################################################################

CREATE TABLE IF NOT EXISTS `_respaldo_keywords_cert_2026_11` (
  `certification_id` BIGINT UNSIGNED NOT NULL,
  `keywords_anterior` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `respaldado_en`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`certification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE certifications c
  JOIN `_respaldo_keywords_cert_2026_11` r ON r.certification_id = c.id
   SET c.keywords = r.keywords_anterior, c.updated_at = NOW();
SELECT CONCAT('keywords restauradas: ', ROW_COUNT()) AS resultado;

DROP TABLE IF EXISTS `_respaldo_keywords_cert_2026_11`;
SELECT 'OK 13: reversión de 12 terminada' AS resultado;

-- #####################################################################
-- PARTE 2/12: Certificaciones 08 — reglas del matcher
-- #####################################################################

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_aliases');
SET @sql = IF(@existe > 0,
  'DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id WHERE me.entity_type = ''certification'' AND a.notes = ''[seed certificaciones]''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'Reglas sembradas de certificaciones borradas' AS resultado;

SELECT 'OK 08: reversión de 07 terminada' AS resultado;

-- #####################################################################
-- PARTE 3/12: Certificaciones 06 — entidades
-- #####################################################################

CREATE TABLE IF NOT EXISTS `_respaldo_certificaciones_2026_11` (
  `tabla` VARCHAR(64) NOT NULL, `fila_id` BIGINT UNSIGNED NOT NULL, `columna` VARCHAR(64) NOT NULL,
  `valor_anterior` VARCHAR(255) NULL, `respaldado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabla`, `fila_id`, `columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

UPDATE market_entities me
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'is_active'
   SET me.is_active = r.valor_anterior, me.inactive_reason = NULL, me.inactive_at = NULL
 WHERE me.is_active = 0 AND me.inactive_reason LIKE '[Certificaciones 2026-11]%';
SELECT CONCAT('Duplicados reactivados: ', ROW_COUNT()) AS resultado;

DELETE FROM certification_job WHERE certification_name_raw = '[migrado de technology_job 2026-11]';
SELECT CONCAT('Asociaciones copiadas borradas: ', ROW_COUNT()) AS resultado;

UPDATE certifications c
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'certifications' AND r.fila_id = c.id AND r.columna = 'creada'
   SET c.enabled = 0, c.updated_at = NOW();
SELECT CONCAT('Filas de certifications deshabilitadas: ', ROW_COUNT()) AS resultado;

UPDATE certifications c
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'certifications' AND r.fila_id = c.id AND r.columna = 'enabled'
   SET c.enabled = r.valor_anterior, c.updated_at = NOW();
SELECT CONCAT('Filas de certifications devueltas a su estado anterior: ', ROW_COUNT()) AS resultado;

UPDATE certifications c
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'certifications' AND r.fila_id = c.id AND r.columna = 'keywords'
   SET c.keywords = NULL, c.updated_at = NOW();
SELECT CONCAT('keywords devueltas a NULL: ', ROW_COUNT()) AS resultado;

UPDATE market_entities me
  JOIN `_respaldo_certificaciones_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'creada'
   SET me.is_active = 0, me.inactive_at = NOW(), me.inactive_reason = 'Revertido por 06_ranking_certifications_entidades_rollback'
 WHERE me.is_active = 1;
SELECT CONCAT('CompTIA A+ desactivada: ', ROW_COUNT()) AS resultado;

COMMIT;

DROP TABLE IF EXISTS `_respaldo_certificaciones_2026_11`;
SELECT 'OK 06: reversión de 05 terminada' AS resultado;

-- #####################################################################
-- PARTE 4/12: Certificaciones 03 — tablas e índice
-- #####################################################################

DROP TABLE IF EXISTS `certification_ranking_weekly_meta`;
DROP TABLE IF EXISTS `certification_ranking_weekly`;
DROP TABLE IF EXISTS `certification_ranking_meta`;
DROP TABLE IF EXISTS `certification_rankings`;
SELECT 'Tablas del ranking de certificaciones eliminadas (si existían)' AS resultado;

SET @tbl = 'certification_job', @idx = 'idx_cj_job_entity';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SELECT 'OK 03: rollback del ranking de certificaciones terminado' AS resultado;

-- #####################################################################
-- PARTE 5/12: Tecnologías 11 — reglas del matcher y Transformers
-- #####################################################################

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_aliases');
SET @sql = IF(@existe > 0,
  'DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id WHERE me.entity_type = ''technology'' AND a.notes = ''[seed tecnologias]''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'Reglas sembradas de tecnologías borradas' AS resultado;

UPDATE market_entities
   SET is_active = 0, inactive_at = NOW(),
       inactive_reason = '[Tecnologías 2026-11] Ambiguo: se reactiva en la Fase 3 con alias estrictos'
 WHERE entity_type = 'technology' AND slug = 'transformers' AND is_active = 1;
SELECT CONCAT('Transformers desactivado: ', ROW_COUNT()) AS resultado;

SELECT 'OK 11: reversión de 10 terminada' AS resultado;

-- #####################################################################
-- PARTE 6/12: Tecnologías 07 — entidades
-- #####################################################################

UPDATE market_entities
   SET is_active = 1, inactive_reason = NULL, inactive_at = NULL
 WHERE entity_type = 'technology' AND is_active = 0
   AND inactive_reason LIKE '[Tecnologías 2026-11]%';
SELECT CONCAT('Entidades reactivadas: ', ROW_COUNT()) AS resultado;

SELECT 'OK 07: reclasificación de entidades de tecnologías revertida' AS resultado;

-- #####################################################################
-- PARTE 7/12: Tecnologías 09 — carreras
-- #####################################################################

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_career_migracion_cme');

SET @sql = IF(@existe > 0,
  'DELETE m FROM market_entity_career m JOIN market_entity_career_migracion_cme t ON t.market_entity_id = m.market_entity_id AND t.career_id = m.career_id',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; SET @borrados = ROW_COUNT(); DEALLOCATE PREPARE stmt;
SELECT CONCAT('Pares borrados de market_entity_career: ', IF(@existe > 0, @borrados, 0)) AS resultado;

DROP TABLE IF EXISTS `market_entity_career_migracion_cme`;

SELECT 'OK 09: migración de carreras revertida' AS resultado;

-- #####################################################################
-- PARTE 8/12: Tecnologías 03 — tablas e índice
-- #####################################################################

DROP TABLE IF EXISTS `technology_ranking_weekly_meta`;
DROP TABLE IF EXISTS `technology_ranking_weekly`;
DROP TABLE IF EXISTS `technology_ranking_meta`;
DROP TABLE IF EXISTS `technology_rankings`;
SELECT 'Tablas del ranking de tecnologías eliminadas (si existían)' AS resultado;

SET @tbl = 'technology_job', @idx = 'idx_tj_job_entity';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SELECT 'OK 03: rollback del ranking de tecnologías terminado' AS resultado;

-- #####################################################################
-- PARTE 9/12: Lenguajes 12 — matcher (tabla compartida) y filas nuevas de languages
-- #####################################################################

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'market_entity_aliases');
SET @sql = IF(@existe > 0,
  'SELECT a.id, me.entity_type, me.name, a.kind, a.pattern, a.case_sensitive, a.is_weak, a.window_chars, a.notes FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id WHERE a.notes IS NULL OR a.notes NOT LIKE ''[seed %'' ORDER BY me.entity_type, me.name',
  'SELECT ''market_entity_aliases no existe'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Filas de languages creadas por 11: se DESHABILITAN (no se borran: pueden tener ofertas).
SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = '_respaldo_lenguajes_2026_11');
SET @sql = IF(@existe > 0,
  'UPDATE languages l JOIN _respaldo_lenguajes_2026_11 r ON r.tabla = ''languages'' AND r.fila_id = l.id AND r.columna = ''creada'' SET l.enabled = 0, l.updated_at = NOW()',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'Filas de languages creadas por 11: deshabilitadas (si había)' AS resultado;

DROP TABLE IF EXISTS `market_entity_aliases`;
SELECT 'OK 12: market_entity_aliases eliminada (si existía)' AS resultado;

-- #####################################################################
-- PARTE 10/12: Lenguajes 10 — entidades
-- #####################################################################

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = '_respaldo_lenguajes_2026_11');
SELECT IF(@existe > 0, 'Respaldo encontrado: se revierte', 'Sin respaldo: nada que revertir') AS resultado;

-- Si no existe, se crea vacía para que las sentencias siguientes no fallen; se borra al final.
CREATE TABLE IF NOT EXISTS `_respaldo_lenguajes_2026_11` (
  `tabla`          VARCHAR(64)  NOT NULL,
  `fila_id`        BIGINT UNSIGNED NOT NULL,
  `columna`        VARCHAR(64)  NOT NULL,
  `valor_anterior` VARCHAR(255) NULL,
  `respaldado_en`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabla`, `fila_id`, `columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

UPDATE market_entities me
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'is_active'
   SET me.is_active = r.valor_anterior, me.inactive_reason = NULL, me.inactive_at = NULL
 WHERE me.is_active = 0 AND me.inactive_reason LIKE '[Lenguajes 2026-11]%';
SELECT CONCAT('Entidades reactivadas: ', ROW_COUNT()) AS resultado;

UPDATE market_entities me
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'slug'
   SET me.slug = r.valor_anterior
 WHERE me.slug = 'fsharp';
SELECT CONCAT('Slug de F# restaurado: ', ROW_COUNT()) AS resultado;

UPDATE language_job lj
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'language_job' AND r.fila_id = lj.id AND r.columna = 'market_entity_id'
   SET lj.market_entity_id = NULL;
SELECT CONCAT('language_job desvinculadas: ', ROW_COUNT()) AS resultado;

UPDATE languages l
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'languages' AND r.fila_id = l.id AND r.columna = 'market_entity_id'
   SET l.market_entity_id = NULL;
SELECT CONCAT('languages desvinculados: ', ROW_COUNT()) AS resultado;

UPDATE languages l
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'languages' AND r.fila_id = l.id AND r.columna = 'enabled'
   SET l.enabled = r.valor_anterior, l.updated_at = NOW()
 WHERE l.enabled = 0;
SELECT CONCAT('languages rehabilitados: ', ROW_COUNT()) AS resultado;

COMMIT;

DROP TABLE IF EXISTS `_respaldo_lenguajes_2026_11`;
SELECT 'OK 10: reversión de 09 terminada' AS resultado;

-- #####################################################################
-- PARTE 11/12: Lenguajes 07 — evolución e is_active
-- #####################################################################

SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = 'is_active');
SET @sql = IF(@existe > 0,
  'SELECT id, entity_type, name, slug, inactive_reason, inactive_at FROM market_entities WHERE is_active = 0 ORDER BY entity_type, name',
  'SELECT ''market_entities.is_active no existe'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 1. Tablas de evolución
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `language_ranking_weekly_meta`;
DROP TABLE IF EXISTS `language_ranking_weekly`;
SELECT 'Tablas language_ranking_weekly(_meta) eliminadas (si existían)' AS resultado;

-- ---------------------------------------------------------------------
-- 2. Índice y columnas de market_entities
-- ---------------------------------------------------------------------
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND index_name = 'idx_market_entities_type_active');
SET @sql = IF(@existe > 0, 'ALTER TABLE `market_entities` DROP INDEX `idx_market_entities_type_active`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('idx_market_entities_type_active: ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @col = 'inactive_at';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = @col);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `market_entities` DROP COLUMN `', @col, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe > 0, 'ELIMINADA', 'NO EXISTE')) AS resultado;

SET @col = 'inactive_reason';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = @col);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `market_entities` DROP COLUMN `', @col, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe > 0, 'ELIMINADA', 'NO EXISTE')) AS resultado;

SET @col = 'is_active';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'market_entities' AND column_name = @col);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `market_entities` DROP COLUMN `', @col, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe > 0, 'ELIMINADA', 'NO EXISTE')) AS resultado;

SELECT 'OK 07: rollback de la Fase A terminado' AS resultado;

-- #####################################################################
-- PARTE 12/12: Lenguajes 03 — tablas e índices
-- #####################################################################

DROP TABLE IF EXISTS `language_ranking_meta`;
DROP TABLE IF EXISTS `language_rankings`;
SELECT 'Tablas language_rankings y language_ranking_meta eliminadas (si existían)' AS resultado;

-- ---------------------------------------------------------------------
-- 2. Índices (por nombre)
-- ---------------------------------------------------------------------
SET @tbl = 'language_job', @idx = 'idx_lj_job_entity';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @tbl = 'job_offers', @idx = 'idx_jo_created_at';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @tbl = 'job_offers', @idx = 'idx_jo_source_created';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SET @tbl = 'scraper_runs', @idx = 'idx_scraper_runs_entity_finished';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. (Opcional) job_offers.idx_jo_published_at
--    Descomentar SOLO si el script 02 lo creó en esta base (su salida decía
--    "Paso 2 - job_offers.idx_jo_published_at: CREADO").
-- ---------------------------------------------------------------------
-- SET @tbl = 'job_offers', @idx = 'idx_jo_published_at';
-- SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
--                 WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
-- SET @sql = IF(@existe > 0, CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', @idx, '`, ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
-- PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- SELECT CONCAT(@tbl, '.', @idx, ': ', IF(@existe > 0, 'ELIMINADO', 'NO EXISTE')) AS resultado;

SELECT 'OK 03: rollback terminado' AS resultado;

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

SELECT 'OK: reversión del despliegue de los rankings terminada' AS resultado;
