-- =====================================================================
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


-- #####################################################################
-- PARTE 1/17: Lenguajes 01 — tablas de resumen
-- #####################################################################

CREATE TABLE IF NOT EXISTS `language_rankings` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`             SMALLINT UNSIGNED NOT NULL,
  `period`           ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,

  -- Copia de market_entities al momento del cálculo
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_isil`         TINYINT(1)        NOT NULL DEFAULT 0,
  `has_trend`        TINYINT(1)        NOT NULL DEFAULT 0,
  `classification`   VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  -- Métricas crudas
  `total_jobs`       INT UNSIGNED      NOT NULL DEFAULT 0,
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,

  -- Puntajes 0-100 sin redondear. final_score y position se recalculan al
  -- leer con los pesos vigentes; aquí quedan como referencia del cálculo.
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_language_rankings_period_entity` (`year`, `period`, `market_entity_id`),
  KEY `idx_language_rankings_period_score` (`year`, `period`, `final_score`),
  KEY `idx_language_rankings_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. language_ranking_meta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `language_ranking_meta` (
  `id`                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `period`              ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  `vacantes_analizadas` INT UNSIGNED      NOT NULL DEFAULT 0,
  `reportes_analizados` INT UNSIGNED      NOT NULL DEFAULT 0,
  `max_labor`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `labor_weight`        DECIMAL(4,3)      NOT NULL,
  `trend_weight`        DECIMAL(4,3)      NOT NULL,
  `rows_count`          INT UNSIGNED      NOT NULL DEFAULT 0,
  `duration_ms`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`       TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_language_ranking_meta_period` (`year`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Tablas de Laravel para caché y colas (solo si faltan)
--    Mismo esquema que las migraciones 0001_01_01_000001/000002.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value`      MEDIUMTEXT   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner`      VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `queue`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`      LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL,
  `reserved_at`  INT UNSIGNED     DEFAULT NULL,
  `available_at` INT UNSIGNED     NOT NULL,
  `created_at`   INT UNSIGNED     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue`      TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`    LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception`  LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK 01: tablas del ranking de lenguajes listas' AS resultado;

-- #####################################################################
-- PARTE 2/17: Lenguajes 02 — índices
-- #####################################################################

SET @tbl = 'language_job', @idx = 'idx_lj_job_entity', @cols = 'job_offer_id,market_entity_id';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 1 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 2. job_offers (published_at)
--    Rango por fecha de publicación. En la base de desarrollo ya existe
--    como idx_jo_published_at; si existe con otro nombre, se omite.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_published_at', @cols = 'published_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 2 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. job_offers (created_at)
--    JobMarketStatus: MIN/MAX(created_at), ofertas del mes y del periodo.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_created_at', @cols = 'created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 3 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 4. job_offers (source, created_at)
--    JobMarketStatus: COUNT(*) y MAX(created_at) por fuente.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_source_created', @cols = 'source,created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 4 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 5. scraper_runs (entity, finished_at)
--    ScrapingStatusService: últimas ejecuciones por entidad.
-- ---------------------------------------------------------------------
SET @tbl = 'scraper_runs', @idx = 'idx_scraper_runs_entity_finished', @cols = 'entity,finished_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 5 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

SELECT 'OK 02: índices revisados' AS resultado;

-- #####################################################################
-- PARTE 3/17: Lenguajes 05 — market_entities.is_active (fuente única de entidad activa)
-- #####################################################################

SET @tbl = 'market_entities';

SET @col = 'is_active';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = @col);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe = 0, 'CREADA', 'YA EXISTE')) AS resultado;

SET @col = 'inactive_reason';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = @col);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD COLUMN `inactive_reason` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe = 0, 'CREADA', 'YA EXISTE')) AS resultado;

SET @col = 'inactive_at';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = @col);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD COLUMN `inactive_at` TIMESTAMP NULL DEFAULT NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe = 0, 'CREADA', 'YA EXISTE')) AS resultado;

-- ---------------------------------------------------------------------
-- 2. Índice (entity_type, is_active)
-- ---------------------------------------------------------------------
SET @idx = 'idx_market_entities_type_active';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD INDEX `idx_market_entities_type_active` (`entity_type`, `is_active`), ALGORITHM=INPLACE, LOCK=NONE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @idx, ': ', IF(@existe = 0, 'CREADO', 'YA EXISTE')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. Reclasificar IDC y OECD como inactivas (no son lenguajes)
--    Solo toca filas que siguen activas: re-ejecutar no cambia nada.
-- ---------------------------------------------------------------------
UPDATE `market_entities`
   SET `is_active` = 0,
       `inactive_reason` = 'Organización/fuente de reportes, no es un lenguaje de programación',
       `inactive_at` = NOW()
 WHERE `entity_type` = 'language'
   AND `slug` IN ('idc', 'oecd')
   AND `is_active` = 1;
SELECT CONCAT('IDC/OECD desactivadas en esta ejecución: ', ROW_COUNT()) AS resultado;

UPDATE `languages` l
  JOIN `market_entities` me ON me.id = l.market_entity_id
   SET l.enabled = 0
 WHERE me.entity_type = 'language'
   AND me.slug IN ('idc', 'oecd')
   AND l.enabled = 1;
SELECT CONCAT('Filas de languages deshabilitadas en esta ejecución: ', ROW_COUNT()) AS resultado;

SELECT id, name, slug, is_active, inactive_reason, inactive_at
  FROM market_entities
 WHERE entity_type = 'language' AND slug IN ('idc', 'oecd');

SELECT 'OK 05: marca de entidades activas lista' AS resultado;

-- #####################################################################
-- PARTE 4/17: Lenguajes 06 — tablas de evolución (fotos por semana/quincena/mes)
-- #####################################################################

SET @vieja = (SELECT COUNT(*) FROM information_schema.tables t
               WHERE t.table_schema = DATABASE() AND t.table_name = 'language_ranking_weekly')
           - (SELECT COUNT(*) FROM information_schema.columns c
               WHERE c.table_schema = DATABASE() AND c.table_name = 'language_ranking_weekly' AND c.column_name = 'cutoff_date');
SET @sql = IF(@vieja > 0, 'RENAME TABLE `language_ranking_weekly` TO `language_ranking_weekly_anterior_2026_11`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('language_ranking_weekly: ', IF(@vieja > 0, 'estructura anterior RENOMBRADA a language_ranking_weekly_anterior_2026_11, se crea la nueva', 'sin versión anterior')) AS resultado;

SET @vieja = (SELECT COUNT(*) FROM information_schema.tables t
               WHERE t.table_schema = DATABASE() AND t.table_name = 'language_ranking_weekly_meta')
           - (SELECT COUNT(*) FROM information_schema.columns c
               WHERE c.table_schema = DATABASE() AND c.table_name = 'language_ranking_weekly_meta' AND c.column_name = 'cutoff_date');
SET @sql = IF(@vieja > 0, 'RENAME TABLE `language_ranking_weekly_meta` TO `language_ranking_weekly_meta_anterior_2026_11`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('language_ranking_weekly_meta: ', IF(@vieja > 0, 'estructura anterior RENOMBRADA a language_ranking_weekly_meta_anterior_2026_11, se crea la nueva', 'sin versión anterior')) AS resultado;

-- ---------------------------------------------------------------------
-- 1. Fotos por ventana y lenguaje
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `language_ranking_weekly` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`      ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`       VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  -- Año ISO para weekly (la semana 1 de 2027 puede empezar el 28-dic-2026); año calendario para el resto
  `year`             SMALLINT UNSIGNED NOT NULL,
  `cutoff_date`      DATE              NOT NULL,              -- fecha de la foto
  `semester`         CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,  -- '2026-s2'
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jobs`             INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes acumuladas del semestre al corte
  `new_jobs`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes publicadas dentro de la ventana
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,      -- reportes del semestre disponibles al corte
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lrw_window_entity` (`period_type`, `period_key`, `market_entity_id`),
  KEY `idx_lrw_type_year` (`period_type`, `year`),
  KEY `idx_lrw_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Una fila por ventana
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `language_ranking_weekly_meta` (
  `id`                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`       ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`        VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year`              SMALLINT UNSIGNED NOT NULL,
  `label`             VARCHAR(80)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date`        DATE              NOT NULL,
  `end_date`          DATE              NOT NULL,
  `cutoff_date`       DATE              NOT NULL,             -- end_date, o hoy si está en curso
  `semester`          CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_semester_start` TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = primera foto de un semestre (el acumulado se reinicia)
  `is_partial`        TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = ventana en curso
  `unique_offers`     INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas acumuladas del semestre al corte
  `new_unique_offers` INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas publicadas dentro de la ventana
  `max_labor`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `languages_count`   INT UNSIGNED      NOT NULL DEFAULT 0,
  `calculated_at`     TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lrwm_window` (`period_type`, `period_key`),
  KEY `idx_lrwm_type_year_start` (`period_type`, `year`, `start_date`),
  KEY `idx_lrwm_type_start` (`period_type`, `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK 06: tablas de evolución listas (fotos por fecha de corte)' AS resultado;

-- #####################################################################
-- PARTE 5/17: Lenguajes 09 — entidades (Shell/Ada inactivos, slug de F#, Go/Rust/Scala, GNU/Linux y CLI)
-- #####################################################################

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

-- #####################################################################
-- PARTE 6/17: Lenguajes 11 — matcher: tabla market_entity_aliases, reglas de lenguajes y filas nuevas de languages
-- #####################################################################

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
  CONSTRAINT `fk_mea_entity` FOREIGN KEY (`market_entity_id`) REFERENCES `market_entities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SELECT 'market_entity_aliases lista' AS resultado;

START TRANSACTION;

-- ===================== INICIO DEL BLOQUE GENERADO =====================
-- Generado por docs/produccion/herramientas/generar_aliases_sql.php languages language lenguajes
-- 28 entidades. Las entidades que no existan en este entorno se omiten solas (JOIN por slug).
-- (Sin borrado previo: INSERT IGNORE no duplica reglas al ejecutar dos veces)

-- sql
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SQL', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'sql'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- java
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Java', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Java Script', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'java'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'javascript'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- typescript
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'TypeScript', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'typescript'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- python
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Python', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'python'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'c'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'c-plus-plus'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- php
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'PHP', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'php'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- css
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CSS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CSS3', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'css'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- html
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'HTML', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'HTML5', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'html'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- kotlin
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Kotlin', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'kotlin'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- matlab
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'MATLAB', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'matlab'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- json
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'JSON', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'json'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- xml
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'XML', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'xml'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'scala'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- perl
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Perl', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'perl'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cobol
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'COBOL', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'cobol'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- lua
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Lua', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'lua'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- vba
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VBA', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Visual Basic for Applications', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'vba'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- fsharp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed lenguajes]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'F#', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'FSharp', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'language' AND me.slug = 'fsharp'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'rust'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'r'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'go'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'swift'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'dax'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'ruby'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'shell'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

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
 WHERE me.entity_type = 'language' AND me.slug = 'ada'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

SELECT CONCAT('Reglas sembradas: ', COUNT(*), ' (esperadas 298)') AS resultado
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'language' AND a.notes = '[seed lenguajes]';
-- ====================== FIN DEL BLOQUE GENERADO =======================

COMMIT;

-- ---------------------------------------------------------------------
-- 3. Filas de languages para los lenguajes activos que no tenían
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `_respaldo_lenguajes_2026_11` (
  `tabla`          VARCHAR(64)  NOT NULL,
  `fila_id`        BIGINT UNSIGNED NOT NULL,
  `columna`        VARCHAR(64)  NOT NULL,
  `valor_anterior` VARCHAR(255) NULL,
  `respaldado_en`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabla`, `fila_id`, `columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Filas que ya existían sin vínculo: vincularlas (no se crean duplicados)
UPDATE languages l
  JOIN market_entities me ON me.entity_type = 'language' AND me.name = l.name
   SET l.market_entity_id = me.id
 WHERE me.slug IN ('perl', 'ruby', 'vba', 'lua', 'cobol', 'fsharp') AND l.market_entity_id IS NULL;
SELECT CONCAT('Filas de languages existentes vinculadas: ', ROW_COUNT()) AS resultado;

-- Re-aplicación después de 12 (que deshabilita, no borra): rehabilitar la fila de cada uno de
-- estos lenguajes si está deshabilitada, su entidad está activa y no tiene otra fila habilitada.
-- Se registra como 'creada' para que 12 la vuelva a deshabilitar.
INSERT IGNORE INTO `_respaldo_lenguajes_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'languages', l.id, 'creada', NULL
  FROM languages l
  JOIN market_entities me ON me.id = l.market_entity_id AND me.entity_type = 'language' AND me.is_active = 1
 WHERE me.slug IN ('perl', 'ruby', 'vba', 'lua', 'cobol', 'fsharp') AND l.enabled = 0
   AND NOT EXISTS (SELECT 1 FROM (SELECT market_entity_id FROM languages WHERE enabled = 1) e WHERE e.market_entity_id = me.id);
UPDATE languages l
  JOIN market_entities me ON me.id = l.market_entity_id AND me.entity_type = 'language' AND me.is_active = 1
   SET l.enabled = 1, l.updated_at = NOW()
 WHERE me.slug IN ('perl', 'ruby', 'vba', 'lua', 'cobol', 'fsharp') AND l.enabled = 0
   AND NOT EXISTS (SELECT 1 FROM (SELECT market_entity_id FROM languages WHERE enabled = 1) e WHERE e.market_entity_id = me.id);
SELECT CONCAT('Filas de languages rehabilitadas: ', ROW_COUNT()) AS resultado;

INSERT INTO languages (name, enabled, market_entity_id, created_at, updated_at)
SELECT me.name, 1, me.id, NOW(), NOW()
  FROM market_entities me
 WHERE me.entity_type = 'language' AND me.is_active = 1
   AND me.slug IN ('perl', 'ruby', 'vba', 'lua', 'cobol', 'fsharp')
   AND NOT EXISTS (SELECT 1 FROM languages l WHERE l.name = me.name);
SET @creadas = ROW_COUNT();
SELECT CONCAT('Filas de languages creadas: ', @creadas) AS resultado;

-- Respaldo: solo las filas recién creadas (la última inserción de este script)
INSERT IGNORE INTO `_respaldo_lenguajes_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'languages', l.id, 'creada', NULL
  FROM languages l
 WHERE @creadas > 0
   AND l.id >= LAST_INSERT_ID() AND l.id < LAST_INSERT_ID() + @creadas;

-- Verificación: reglas por lenguaje
SELECT me.name, me.is_active,
       SUM(a.kind = 'alias') AS alias, SUM(a.kind = 'negative') AS negativos, SUM(a.kind = 'context') AS contextos
  FROM market_entities me
  LEFT JOIN market_entity_aliases a ON a.market_entity_id = me.id
 WHERE me.entity_type = 'language'
 GROUP BY me.id, me.name, me.is_active
 ORDER BY me.is_active DESC, me.name;

SELECT 'OK 11: matcher listo (tabla y reglas de lenguajes)' AS resultado;

-- #####################################################################
-- PARTE 7/17: Tecnologías 05 — is_active (repaso idempotente)
-- #####################################################################

SET @tbl = 'market_entities';

SET @col = 'is_active';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = @col);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe = 0, 'CREADA', 'YA EXISTE')) AS resultado;

SET @col = 'inactive_reason';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = @col);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD COLUMN `inactive_reason` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe = 0, 'CREADA', 'YA EXISTE')) AS resultado;

SET @col = 'inactive_at';
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = @col);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD COLUMN `inactive_at` TIMESTAMP NULL DEFAULT NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @col, ': ', IF(@existe = 0, 'CREADA', 'YA EXISTE')) AS resultado;

-- ---------------------------------------------------------------------
-- 2. Índice (entity_type, is_active)
-- ---------------------------------------------------------------------
SET @idx = 'idx_market_entities_type_active';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = @idx);
SET @sql = IF(@existe = 0,
  'ALTER TABLE `market_entities` ADD INDEX `idx_market_entities_type_active` (`entity_type`, `is_active`), ALGORITHM=INPLACE, LOCK=NONE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('market_entities.', @idx, ': ', IF(@existe = 0, 'CREADO', 'YA EXISTE')) AS resultado;

SELECT 'OK 05: marca de entidades activas lista (tecnologías)' AS resultado;

-- #####################################################################
-- PARTE 8/17: Tecnologías 01 — tablas de resumen
-- #####################################################################

CREATE TABLE IF NOT EXISTS `technology_rankings` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`             SMALLINT UNSIGNED NOT NULL,
  `period`           ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,

  -- Copia de market_entities al momento del cálculo
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_isil`         TINYINT(1)        NOT NULL DEFAULT 0,
  `has_trend`        TINYINT(1)        NOT NULL DEFAULT 0,
  `classification`   VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  -- Métricas crudas
  `total_jobs`       INT UNSIGNED      NOT NULL DEFAULT 0,
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,

  -- Puntajes 0-100 sin redondear. final_score y position se recalculan al
  -- leer con los pesos vigentes; aquí quedan como referencia del cálculo.
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technology_rankings_period_entity` (`year`, `period`, `market_entity_id`),
  KEY `idx_technology_rankings_period_score` (`year`, `period`, `final_score`),
  KEY `idx_technology_rankings_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. technology_ranking_meta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technology_ranking_meta` (
  `id`                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `period`              ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  `vacantes_analizadas` INT UNSIGNED      NOT NULL DEFAULT 0,
  `reportes_analizados` INT UNSIGNED      NOT NULL DEFAULT 0,
  `max_labor`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `labor_weight`        DECIMAL(4,3)      NOT NULL,
  `trend_weight`        DECIMAL(4,3)      NOT NULL,
  `rows_count`          INT UNSIGNED      NOT NULL DEFAULT 0,
  `duration_ms`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`       TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technology_ranking_meta_period` (`year`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Tablas de Laravel para caché y colas (solo si faltan)
--    Mismo esquema que las migraciones 0001_01_01_000001/000002.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value`      MEDIUMTEXT   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner`      VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `queue`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`      LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL,
  `reserved_at`  INT UNSIGNED     DEFAULT NULL,
  `available_at` INT UNSIGNED     NOT NULL,
  `created_at`   INT UNSIGNED     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue`      TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`    LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception`  LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Evolución (fotos por fecha de corte)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technology_ranking_weekly` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`      ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`       VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  -- Año ISO para weekly (la semana 1 de 2027 puede empezar el 28-dic-2026); año calendario para el resto
  `year`             SMALLINT UNSIGNED NOT NULL,
  `cutoff_date`      DATE              NOT NULL,              -- fecha de la foto
  `semester`         CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,  -- '2026-s2'
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jobs`             INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes acumuladas del semestre al corte
  `new_jobs`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes publicadas dentro de la ventana
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,      -- reportes del semestre disponibles al corte
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trw_window_entity` (`period_type`, `period_key`, `market_entity_id`),
  KEY `idx_trw_type_year` (`period_type`, `year`),
  KEY `idx_trw_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `technology_ranking_weekly_meta` (
  `id`                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`       ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`        VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year`              SMALLINT UNSIGNED NOT NULL,
  `label`             VARCHAR(80)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date`        DATE              NOT NULL,
  `end_date`          DATE              NOT NULL,
  `cutoff_date`       DATE              NOT NULL,             -- end_date, o hoy si está en curso
  `semester`          CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_semester_start` TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = primera foto de un semestre (el acumulado se reinicia)
  `is_partial`        TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = ventana en curso
  `unique_offers`     INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas acumuladas del semestre al corte
  `new_unique_offers` INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas publicadas dentro de la ventana
  `max_labor`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `entities_count`    INT UNSIGNED      NOT NULL DEFAULT 0,
  `calculated_at`     TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trwm_window` (`period_type`, `period_key`),
  KEY `idx_trwm_type_year_start` (`period_type`, `year`, `start_date`),
  KEY `idx_trwm_type_start` (`period_type`, `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK 01: tablas del ranking de tecnologías listas' AS resultado;

-- #####################################################################
-- PARTE 9/17: Tecnologías 02 — índices
-- #####################################################################

SET @tbl = 'technology_job', @idx = 'idx_tj_job_entity', @cols = 'job_offer_id,market_entity_id';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 1 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 2. job_offers (published_at)
--    Rango por fecha de publicación. En la base de desarrollo ya existe
--    como idx_jo_published_at; si existe con otro nombre, se omite.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_published_at', @cols = 'published_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 2 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. job_offers (created_at)
--    JobMarketStatus: MIN/MAX(created_at), ofertas del mes y del periodo.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_created_at', @cols = 'created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 3 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 4. job_offers (source, created_at)
--    JobMarketStatus: COUNT(*) y MAX(created_at) por fuente.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_source_created', @cols = 'source,created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 4 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 5. scraper_runs (entity, finished_at)
--    ScrapingStatusService: últimas ejecuciones por entidad.
-- ---------------------------------------------------------------------
SET @tbl = 'scraper_runs', @idx = 'idx_scraper_runs_entity_finished', @cols = 'entity,finished_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 5 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

SELECT 'OK 02: índices del ranking de tecnologías revisados' AS resultado;

-- #####################################################################
-- PARTE 10/17: Tecnologías 08 — carreras en market_entity_career
-- #####################################################################

CREATE TABLE IF NOT EXISTS `market_entity_career_migracion_cme` (
  `market_entity_id` BIGINT UNSIGNED NOT NULL,
  `career_id`        BIGINT UNSIGNED NOT NULL,
  `migrated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`market_entity_id`, `career_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Pares de tecnologías que faltan en market_entity_career
INSERT IGNORE INTO `market_entity_career_migracion_cme` (`market_entity_id`, `career_id`)
SELECT DISTINCT c.market_entity_id, c.career_id
  FROM career_market_entity c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'technology'
  JOIN careers ca ON ca.id = c.career_id
 WHERE NOT EXISTS (SELECT 1 FROM market_entity_career m
                    WHERE m.market_entity_id = c.market_entity_id AND m.career_id = c.career_id);
SELECT CONCAT('Pares registrados para migrar en esta ejecución: ', ROW_COUNT()) AS resultado;

-- 3. Insertarlos en market_entity_career (solo los que todavía no están)
INSERT INTO market_entity_career (market_entity_id, career_id, relevance_score, source, created_at, updated_at)
SELECT t.market_entity_id, t.career_id,
       (SELECT MAX(c.weight) FROM career_market_entity c
         WHERE c.market_entity_id = t.market_entity_id AND c.career_id = t.career_id),
       'manual', NOW(), NOW()
  FROM market_entity_career_migracion_cme t
 WHERE NOT EXISTS (SELECT 1 FROM market_entity_career m
                    WHERE m.market_entity_id = t.market_entity_id AND m.career_id = t.career_id);
SELECT CONCAT('Pares insertados en market_entity_career en esta ejecución: ', ROW_COUNT()) AS resultado;

-- 4. Verificación. Esperado: 0 pares de tecnologías de career_market_entity sin copiar.
SELECT COUNT(*) AS pares_tecnologia_sin_copiar
  FROM career_market_entity c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'technology'
  JOIN careers ca ON ca.id = c.career_id
 WHERE NOT EXISTS (SELECT 1 FROM market_entity_career m
                    WHERE m.market_entity_id = c.market_entity_id AND m.career_id = c.career_id);

SELECT (SELECT COUNT(*) FROM market_entity_career_migracion_cme) AS pares_migrados_total,
       (SELECT COUNT(*) FROM market_entity_career m
          JOIN market_entities me ON me.id = m.market_entity_id AND me.entity_type = 'technology') AS pares_tecnologia_en_market_entity_career;

SELECT 'OK 08: carreras de tecnologías unificadas en market_entity_career' AS resultado;

-- #####################################################################
-- PARTE 11/17: Tecnologías 06 — entidades (140 desactivadas, incluidos 41 duplicados)
-- #####################################################################

UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Organización o reporte, no es una tecnología', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'gartner',
  'forrester-research',
  'mckinsey-company',
  'mckinsey-global-institute',
  'international-data-corporation-idc',
  'international-labour-organization',
  'international-telecommunication-union-itu',
  'world-economic-forum',
  'linkedin-workforce-report',
  'cisco-annual-cybersecurity-report',
  'cisco-annual-cybersecurity-report-2025'
);
SELECT CONCAT('Organización o reporte, no es una tecnología: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- Ya existe como metodología (otro indicador) (6)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Ya existe como metodología (otro indicador)', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'devops',
  'safe',
  'less',
  'cobit',
  'iso-270012022',
  'nist'
);
SELECT CONCAT('Ya existe como metodología (otro indicador): ', ROW_COUNT(), ' desactivadas') AS resultado;

-- Norma, marco o regulación, no es una tecnología (15)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Norma, marco o regulación, no es una tecnología', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'coso',
  'pmbok',
  'togaf',
  'zachman-framework',
  'mda',
  'iso-25000',
  'iso-27000',
  'isoiec-12207',
  'isoiec-25000',
  'isoiec-9126',
  'gdpr',
  'pci-dss',
  'plaft-compliance',
  'gesintel-cumplimiento-normativo-peru',
  'auditoria-de-seguridad-de-datos'
);
SELECT CONCAT('Norma, marco o regulación, no es una tecnología: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- Pertenece a otro indicador: certificaciones (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Pertenece a otro indicador: certificaciones', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'comptia-a'
);
SELECT CONCAT('Pertenece a otro indicador: certificaciones: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- Concepto genérico, no es una tecnología concreta (33)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Concepto genérico, no es una tecnología concreta', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'apis',
  'api',
  'cloud',
  'erp',
  'erps',
  'firewall',
  'vpn',
  'sdk',
  'web-services',
  'no-code',
  'software-colaborativos',
  'bases-de-datos-newsql',
  'bases-de-datos-nosql',
  'bases-de-datos-relacionales',
  'nosql',
  'redes-neuronales',
  'modelos-de-aprendizaje-profundo',
  'cnn',
  'rnn',
  'gans',
  'data-lakehouses',
  'datamarts',
  'datawarehouse',
  'arraylist',
  'byzantine',
  'game-design-document-gdd',
  'workflow-management-system',
  'eai',
  'mom',
  'bpms',
  'siem',
  'idsips',
  'ips'
);
SELECT CONCAT('Concepto genérico, no es una tecnología concreta: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- Protocolo, estándar o algoritmo: se reconsidera en la Fase 3 con alias estrictos (31)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Protocolo, estándar o algoritmo: se reconsidera en la Fase 3 con alias estrictos', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'wi-fi',
  'wlan',
  'ethernet',
  'tcp',
  'udp',
  'stp',
  'dhcp',
  'ntp',
  'ssl',
  'tls',
  'ssltls',
  'ssh',
  'sftp',
  'amqp',
  'mqtt',
  'coap',
  'lorawan',
  'zigbee',
  'ipsec',
  'kerberos',
  'radius',
  'pki',
  'rsa',
  'dsa',
  'ecc',
  'diffie-hellman',
  'rc4',
  'vaes',
  'etherchannel',
  'nfs',
  'afs'
);
SELECT CONCAT('Protocolo, estándar o algoritmo: se reconsidera en la Fase 3: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- (Transformers no se desactiva: queda activo con las reglas estrictas de la parte de Tecnologías 10)

-- Pertenece a certificaciones (Cisco CyberOps Associate) (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Pertenece a certificaciones (Cisco CyberOps Associate)', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'cyberops'
);
SELECT CONCAT('Pertenece a certificaciones (Cisco CyberOps Associate): ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon S3 (4)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon S3', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'aws-s3',
  's3',
  'simple-storage-service-s3',
  'amazon-simple-storage-service-s3'
);
SELECT CONCAT('duplicado de Amazon S3: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon S3 Glacier (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon S3 Glacier', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'aws-s3-glacier'
);
SELECT CONCAT('duplicado de Amazon S3 Glacier: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon SNS (2)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon SNS', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'amazon-simple-notification-service-sns',
  'simple-notification-service-sns'
);
SELECT CONCAT('duplicado de Amazon SNS: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon SQS (2)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon SQS', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'amazon-simple-queue-service-sqs',
  'simple-queue-service-sqs'
);
SELECT CONCAT('duplicado de Amazon SQS: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon EC2 (2)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon EC2', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'ec2',
  'elastic-computing-cloud-ec2'
);
SELECT CONCAT('duplicado de Amazon EC2: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon EC2 Auto Scaling (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon EC2 Auto Scaling', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'aws-ec2-auto-scaling'
);
SELECT CONCAT('duplicado de Amazon EC2 Auto Scaling: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon CloudFront (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon CloudFront', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'aws-cloudfront'
);
SELECT CONCAT('duplicado de Amazon CloudFront: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon VPC (2)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon VPC', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'aws-vpc',
  'vpc'
);
SELECT CONCAT('duplicado de Amazon VPC: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon ElastiCache (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon ElastiCache', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'elasticache'
);
SELECT CONCAT('duplicado de Amazon ElastiCache: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon Redshift (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon Redshift', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'redshift'
);
SELECT CONCAT('duplicado de Amazon Redshift: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon RDS (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon RDS', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'rds'
);
SELECT CONCAT('duplicado de Amazon RDS: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Amazon SageMaker (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Amazon SageMaker', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'sagemaker'
);
SELECT CONCAT('duplicado de Amazon SageMaker: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Elastic Load Balancing (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Elastic Load Balancing', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'elastic-load-balancer-elb'
);
SELECT CONCAT('duplicado de Elastic Load Balancing: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de IIS (2)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de IIS', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'internet-information-server-iis',
  'internet-information-services-iis'
);
SELECT CONCAT('duplicado de IIS: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Identity and Access Management (IAM) (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Identity and Access Management (IAM)', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'iam-identity-and-access-management'
);
SELECT CONCAT('duplicado de Identity and Access Management (IAM): ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Power BI (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Power BI', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'microsoft-power-bi'
);
SELECT CONCAT('duplicado de Power BI: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de SQL Server (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de SQL Server', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'microsoft-sql-server'
);
SELECT CONCAT('duplicado de SQL Server: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Adobe Photoshop (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Adobe Photoshop', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'photoshop'
);
SELECT CONCAT('duplicado de Adobe Photoshop: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Google AdWords (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Google AdWords', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'adwords'
);
SELECT CONCAT('duplicado de Google AdWords: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Google Analytics (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Google Analytics', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'google-analytics-4'
);
SELECT CONCAT('duplicado de Google Analytics: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de VirtualBox (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de VirtualBox', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'virtual-box'
);
SELECT CONCAT('duplicado de VirtualBox: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de HDFS (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de HDFS', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'hadoop-distributed-file-system-hdfs'
);
SELECT CONCAT('duplicado de HDFS: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Metasploit (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Metasploit', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'metasploit-framework'
);
SELECT CONCAT('duplicado de Metasploit: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Microsoft Azure (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Microsoft Azure', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'azure'
);
SELECT CONCAT('duplicado de Microsoft Azure: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Google Cloud Platform (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Google Cloud Platform', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'gcp'
);
SELECT CONCAT('duplicado de Google Cloud Platform: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Unity (5)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Unity', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'unity-3d',
  'unity3d',
  'unityeditor',
  'unityengine',
  'unity-ui'
);
SELECT CONCAT('duplicado de Unity: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de Windows Server (2)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de Windows Server', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'windows-server-2019',
  'windows-server-2022'
);
SELECT CONCAT('duplicado de Windows Server: ', ROW_COUNT(), ' desactivadas') AS resultado;

-- duplicado de React.js (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] duplicado de React.js', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'cms-reactjs'
);
SELECT CONCAT('duplicado de React.js: ', ROW_COUNT(), ' desactivadas') AS resultado;

SELECT COUNT(*) AS tecnologias_activas FROM market_entities WHERE entity_type = 'technology' AND is_active = 1;
SELECT 'OK 06: entidades de tecnologías reclasificadas' AS resultado;

-- #####################################################################
-- PARTE 12/17: Tecnologías 10 — reglas del matcher y Transformers
-- #####################################################################

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
  CONSTRAINT `fk_mea_entity` FOREIGN KEY (`market_entity_id`) REFERENCES `market_entities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- ===================== INICIO DEL BLOQUE GENERADO =====================
-- Generado por docs/produccion/herramientas/generar_aliases_sql.php technologies technology tecnologias
-- 175 entidades. Las entidades que no existan en este entorno se omiten solas (JOIN por slug).
-- (Sin borrado previo: INSERT IGNORE no duplica reglas al ejecutar dos veces)

-- amazon-s3
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon S3', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS S3', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Simple Storage Service', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon Simple Storage Service', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'S3', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 's3', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'bucket', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'buckets', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'storage', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'almacenamiento', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-s3'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-s3-glacier
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon S3 Glacier', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS S3 Glacier', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'S3 Glacier', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon Glacier', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Glacier', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-s3-glacier'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-sns
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon SNS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS SNS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Simple Notification Service', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SNS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'SQS', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-sns'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-sqs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon SQS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS SQS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Simple Queue Service', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SQS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'SNS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'queue', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'colas', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-sqs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-ec2
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon EC2', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS EC2', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EC2', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic Compute Cloud', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic Computing Cloud', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-ec2'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-ec2-auto-scaling
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon EC2 Auto Scaling', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS EC2 Auto Scaling', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EC2 Auto Scaling', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-ec2-auto-scaling'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-cloudfront
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon CloudFront', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS CloudFront', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CloudFront', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-cloudfront'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-vpc
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon VPC', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS VPC', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'VPC', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'subnet', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'subnets', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'network', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'networking', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'redes', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Azure', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-vpc'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-elasticache
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon ElastiCache', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS ElastiCache', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ElastiCache', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-elasticache'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-redshift
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Redshift', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Redshift', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Redshift', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-redshift'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-rds
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon RDS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS RDS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'RDS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'PostgreSQL', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'MySQL', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Aurora', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'database', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'base de datos', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-rds'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-sagemaker
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon SageMaker', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS SageMaker', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SageMaker', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-sagemaker'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- elastic-load-balancing
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Elastic Load Balancing', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic Load Balancer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ELB', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'elastic-load-balancing'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- iis
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'IIS', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Internet Information Services', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Internet Information Server', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'iis'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- identity-and-access-management-iam
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Identity and Access Management', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'IAM', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'identity-and-access-management-iam'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- power-bi
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Power BI', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PowerBI', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Power BI', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'power-bi'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- sql-server
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SQL Server', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft SQL Server', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MS SQL', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MSSQL', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'sql-server'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- adobe-photoshop
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Adobe Photoshop', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Photoshop', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'adobe-photoshop'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-adwords
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google AdWords', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AdWords', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google Ads', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-adwords'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-analytics
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Analytics', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google Analytics 4', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'GA4', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-analytics'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- virtualbox
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VirtualBox', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Virtual Box', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'virtualbox'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- hdfs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'HDFS', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Hadoop Distributed File System', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'hdfs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- metasploit
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Metasploit', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Metasploit Framework', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'metasploit'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Microsoft Azure', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MS Azure', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-azure'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-cloud-platform
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Cloud Platform', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google Cloud', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'GCP', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-cloud-platform'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- unity
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Unity3D', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Unity 3D', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Unity Engine', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'UnityEngine', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'UnityEditor', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Unity UI', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Unity', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'Unity Catalog', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Unity Health', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Unity Bank', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'game', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'games', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'juegos', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'videojuegos', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'C#', 0, 0, 0, 100
    UNION ALL SELECT 'context', '3D', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'Unreal', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'engine', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'VR', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'AR', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'XR', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'gameplay', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'mobile', 0, 0, 0, 100
    UNION ALL SELECT 'context', 'Godot', 0, 0, 0, 100
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'unity'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- windows-server
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Windows Server', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Windows Server 2019', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Windows Server 2022', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'windows-server'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- reactjs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'React.js', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ReactJS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'React', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'React Native', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'React to', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'reactjs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- rest
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'RESTful', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'REST API', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'REST APIs', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'REST services', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'API REST', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'APIs REST', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'REST', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'API', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'APIs', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'HTTP', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'JSON', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'web services', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'servicios web', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'microservices', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'microservicios', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'endpoint', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'endpoints', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'GraphQL', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SOAP', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'gRPC', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'OpenAPI', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Swagger', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'rest'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- soap
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SOAP API', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SOAP web services', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SOAP services', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SOAP', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'SOAP note', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'SOAP notes', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'SOAP format', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'web services', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'servicios web', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'XML', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'WSDL', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'REST', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'API', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'APIs', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'WCF', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'integration', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'integración', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'soap'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- ajax
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AJAX', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Ajax', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'JavaScript', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'jQuery', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'JSON', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'HTML', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'web', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'XMLHttpRequest', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'PHP', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'ajax'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- express
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Express.js', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ExpressJS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Express', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'American Express', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Express Scripts', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Express Entry', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'FedEx Express', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Express delivery', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Express Employment', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Aliexpress', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'Node.js', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'NodeJS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Node', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'JavaScript', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'TypeScript', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'NestJS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'MongoDB', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'MERN', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'MEAN', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'API', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'APIs', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'npm', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'express'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- excel
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Excel', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Excel', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MS Excel', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Excel at', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Excel in', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Excel as', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'excel'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- docker
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Docker', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Dockerfile', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'docker-compose', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'dock worker', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'docker'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- apache
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache HTTP Server', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Apache httpd', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Apache2', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Apache', 1, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Apache (?:Spark|Kafka|Flink|Storm|Tomcat|Airflow|Hadoop|Beam|Camel|NiFi|Cassandra|Maven|Superset|Iceberg|Hive|Pig)', 1, 1, 0, NULL
    UNION ALL SELECT 'negative', 'Apache (?:Solr|Lucene|Zookeeper|ZooKeeper|Ignite|Druid|Pulsar|Hudi|Arrow|Parquet|Avro|Groovy|Struts|Wicket|Ant|POI)', 1, 1, 0, NULL
    UNION ALL SELECT 'negative', 'Apache (?:Cordova|Helicopter|OFBiz|ActiveMQ|Sqoop|Oozie|HBase|Phoenix|Atlas|Ranger|Kylin)', 1, 1, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- teams
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Microsoft Teams', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MS Teams', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'teams'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- monday
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'monday.com', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Monday.com', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'monday'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- pure
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Pure Storage', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Pure Data', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'pure'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- foundation
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Zurb Foundation', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Foundation CSS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Foundation for Sites', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'foundation'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- maze
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Maze', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'UX', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'usability', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'usabilidad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'user research', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Figma', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'prototype', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'prototipo', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'testing', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'maze'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- eraser
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Eraser.io', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'eraser'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- translate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Translate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Translate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'translate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- transcribe
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Transcribe', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Transcribe', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'transcribe'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- polly
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Polly', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Polly', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'polly'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- comprehend
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Comprehend', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Comprehend', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'comprehend'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- custom-labels
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Rekognition Custom Labels', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon Rekognition Custom Labels', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'custom-labels'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-forecast
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Forecast', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Forecast', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-forecast'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-lex
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Lex', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Lex', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-lex'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-mq
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon MQ', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS MQ', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-mq'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-aurora
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Aurora', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Aurora', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Aurora PostgreSQL', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Aurora MySQL', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Aurora Serverless', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-aurora'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-ecs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon ECS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS ECS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic Container Service', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ECS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Docker', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'container', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'containers', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Fargate', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'EKS', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-ecs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-efs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon EFS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS EFS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic File System', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EFS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-efs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-mechanical-turk
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Mechanical Turk', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Mechanical Turk', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MTurk', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-mechanical-turk'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-web-service
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Web Services', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon Web Service', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-web-service'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-lambda
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Lambda', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon Lambda', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Lambda functions', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-lambda'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-iam
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS IAM', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon IAM', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-iam'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-key-management-service-aws-kms
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS KMS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Key Management Service', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Key Management Service', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-key-management-service-aws-kms'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-elastic-block-store
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Elastic Block Store', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic Block Store', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon EBS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS EBS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EBS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'AWS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Amazon Web Services', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Amazon', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cloud', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'EC2', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'S3', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Lambda', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CloudFormation', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-elastic-block-store'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-step-functions
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Step Functions', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Step Functions', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-step-functions'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-elastic-beanstalk
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Elastic Beanstalk', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic Beanstalk', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-elastic-beanstalk'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-appsync
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS AppSync', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AppSync', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-appsync'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cost-explorer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Cost Explorer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cost Explorer', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'cost-explorer'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-dynamodb
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon DynamoDB', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DynamoDB', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-dynamodb'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-cloudwatch
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon CloudWatch', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CloudWatch', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-cloudwatch'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-cognito
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Cognito', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Cognito', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cognito', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-cognito'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-eventbridge
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon EventBridge', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EventBridge', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-eventbridge'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-kinesis
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Kinesis', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Kinesis', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Kinesis', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-kinesis'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amazon-rekognition
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Rekognition', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Rekognition', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-rekognition'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- azure-sentinel
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Sentinel', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Sentinel', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-sentinel'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- azure-synapse-analytics
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Synapse Analytics', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Synapse', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Synapse Analytics', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-synapse-analytics'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- azure-data-factory
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Data Factory', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Data Factory', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ADF', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Azure', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Databricks', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Synapse', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ETL', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pipelines', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-data-factory'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- azure-machine-learning
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Machine Learning', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure ML', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-machine-learning'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- azure-logic-apps
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Logic Apps', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Logic Apps', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-logic-apps'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- azure-blob
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Blob', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Blob Storage', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Blob Storage', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-blob'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- sql-azure
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SQL Azure', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure SQL', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure SQL Database', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'sql-azure'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cli-command-line-interface
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'command line', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'command-line', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'línea de comandos', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CLI', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Linux', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Unix', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'terminal', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Bash', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'shell', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'scripting', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'PowerShell', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Git', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'command', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'cli-command-line-interface'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- file-server-resource-manager-fsrm
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'File Server Resource Manager', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'FSRM', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'file-server-resource-manager-fsrm'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- apache-spark
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache Spark', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PySpark', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Spark SQL', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Spark Streaming', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Spark', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Hadoop', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Databricks', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Scala', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'big data', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Kafka', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'data engineer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'data engineering', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ingeniero de datos', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Hive', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ETL', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Python', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'SQL', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Airflow', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-spark'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- apache-kafka
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache Kafka', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Kafka', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-kafka'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- apache-flink
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache Flink', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Flink', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-flink'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- apache-tomcat
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache Tomcat', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Tomcat', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-tomcat'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- hadoop-mapreduce
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Hadoop MapReduce', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MapReduce', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'hadoop-mapreduce'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- yarn
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'YARN', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Hadoop YARN', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'yarn'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- elastic-stack
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Elastic Stack', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ELK Stack', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ELK', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'elastic-stack'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- kubernetes
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Kubernetes', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'K8s', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'k8s', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'kubernetes'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- argo-cd
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Argo CD', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ArgoCD', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'argo-cd'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- hashicorp-vault
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'HashiCorp Vault', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Hashi Vault', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Vault HashiCorp', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Vault', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'HashiCorp', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'secret', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'secrets', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Consul', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kubernetes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'PAM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CyberArk', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'IAM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'PKI', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'hashicorp-vault'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- open-policy-agent
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Open Policy Agent', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'OPA', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'policy', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'policies', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kubernetes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Rego', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Gatekeeper', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'open-policy-agent'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- rabbit-mq
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'RabbitMQ', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Rabbit MQ', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'rabbit-mq'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- mongodb
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'MongoDB', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Mongo DB', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Mongo', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'mongodb'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- power-apps
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Power Apps', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PowerApps', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'power-apps'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- power-automate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Power Automate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Flow', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'power-automate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-adsense
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google AdSense', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AdSense', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-adsense'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- facebook-ads
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Facebook Ads', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Meta Ads', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'facebook-ads'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- adobe-illustrator
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Adobe Illustrator', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Illustrator', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Adobe', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Photoshop', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'InDesign', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Figma', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Creative Cloud', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'After Effects', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'adobe-illustrator'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- blender
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Blender', 1, 0, 1, NULL
    UNION ALL SELECT 'context', '3D', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'modeling', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'modelado', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'animation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'animación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Maya', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Unity', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Unreal', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ZBrush', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'render', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'rendering', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Substance', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'blender'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- maya
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Autodesk Maya', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Maya', 1, 0, 1, NULL
    UNION ALL SELECT 'context', '3D', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'animation', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'animación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Blender', 0, 0, 0, 80
    UNION ALL SELECT 'context', '3ds Max', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'modeling', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'modelado', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'rigging', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ZBrush', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Houdini', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Autodesk', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'maya'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- autodesk-3ds-max
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Autodesk 3ds Max', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', '3ds Max', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', '3D Studio Max', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', '3DS Max', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'autodesk-3ds-max'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- unreal-engine
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Unreal Engine', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Unreal', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'UE5', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'UE4', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'unreal-engine'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- visual-studio-code
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Visual Studio Code', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'VS Code', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'VSCode', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'visual-studio-code'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- intellij-idea
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'IntelliJ IDEA', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'IntelliJ', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'intellij-idea'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- xcode
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Xcode', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'xcode'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- jupyter-notebook
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Jupyter Notebook', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Jupyter Notebooks', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Jupyter', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'jupyter-notebook'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- jupyter-lab
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'JupyterLab', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Jupyter Lab', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'jupyter-lab'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-colab
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Colab', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Colab', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-colab'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- tailwind-css
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Tailwind CSS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'TailwindCSS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Tailwind', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'tailwind-css'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- entity-framework
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Entity Framework', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EF Core', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'entity-framework'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- nodejs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Node.js', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'NodeJS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Node', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'JavaScript', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'TypeScript', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Express', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'npm', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'NestJS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'backend', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'React', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'API', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'nodejs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- angularjs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Angular.js', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AngularJS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Angular', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'angularjs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- swiftui
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SwiftUI', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'swiftui'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- jetpack-compose
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Jetpack Compose', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'jetpack-compose'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- core-data
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Core Data', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'core-data'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- scikit-learn
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'scikit-learn', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'sklearn', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'scikit learn', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'scikit-learn'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- dall-e
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'DALL-E', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DALL E', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DALL·E', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DALLE', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'dall-e'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-gemini
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Gemini', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Gemini', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Google', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'AI', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'IA', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'LLM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'LLMs', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'GPT', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Claude', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'OpenAI', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'generative', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'generativa', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-gemini'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- gpt
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'GPT', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ChatGPT', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'OpenAI GPT', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'gpt'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- bert
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'BERT', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'bert'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- glove
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'GloVe', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'glove'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- copilot
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'GitHub Copilot', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Copilot', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Copilot', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'copilot'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- perplexity
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Perplexity', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Perplexity AI', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'perplexity'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cisco-ios
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Cisco IOS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'IOS XE', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'IOS-XE', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'cisco-ios'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cisco-dna-center
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Cisco DNA Center', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DNA Center', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'cisco-dna-center'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- ad-fs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AD FS', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ADFS', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Active Directory Federation Services', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'ad-fs'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- exchange-server-2019
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Exchange Server 2019', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Exchange Server', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Exchange', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'exchange-server-2019'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-outlook
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Microsoft Outlook', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Outlook', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-outlook'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- outlook-web-app
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Outlook Web App', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Outlook Web Access', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'OWA', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Outlook', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Exchange', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'email', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'correo', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'outlook-web-app'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- windows-server-app-fabric
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Windows Server AppFabric', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AppFabric', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'windows-server-app-fabric'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- samba
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Samba', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'SAMBA', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Linux', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'SMB', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'CIFS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'file server', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Active Directory', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'NFS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'servidor de archivos', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Unix', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'samba'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- nas
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'NAS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'storage', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'almacenamiento', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'backup', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'RAID', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SAN', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'NFS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'server', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'servidor', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Synology', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'QNAP', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'iSCSI', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'nas'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- san
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SAN', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'Storage Area Network', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'storage', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'almacenamiento', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'NAS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Fibre Channel', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'iSCSI', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'switch', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'backup', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'VMware', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'LUN', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'san'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- veeam-backup
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Veeam Backup', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Veeam', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'veeam-backup'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- proxmox-ve
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Proxmox VE', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Proxmox', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'proxmox-ve'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- vmware-esxi
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VMware ESXi', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ESXi', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vmware-esxi'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- vcenter-server
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'vCenter Server', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'vCenter', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vcenter-server'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- vmware-vsphere
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VMware vSphere', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'vSphere', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vmware-vsphere'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- vmware-nsx
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VMware NSX', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'NSX', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vmware-nsx'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- click-up
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'ClickUp', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Click Up', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'click-up'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- obsidian
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Obsidian', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'notes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'notas', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Notion', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Markdown', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'knowledge', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'obsidian'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- atlas-ti
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Atlas.ti', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ATLAS.ti', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Atlas Ti', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'atlas-ti'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- uipath
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'UiPath', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'uipath'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- amplitude
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amplitude', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'analytics', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'analítica', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'product', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'producto', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Mixpanel', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Google Analytics', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Segment', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Looker', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amplitude'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aspera
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'IBM Aspera', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Aspera', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aspera'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- tableau
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Tableau', 1, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Tableau de', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Tableaux', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'tableau'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- snowflake
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Snowflake', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'snowflake'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- postman
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Postman', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'API', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'APIs', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'REST', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'testing', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'pruebas', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Swagger', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Newman', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'SoapUI', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'JSON', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'QA', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'postman'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- puppet
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Puppet', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Ansible', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Chef', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'configuration management', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'DevOps', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'SaltStack', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Linux', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'puppet'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- pip
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'pip', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Python', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'PyPI', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'install', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'package', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'packages', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'conda', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'virtualenv', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'venv', 0, 0, 0, 40
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'pip'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- conda
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'conda', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Anaconda', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'conda'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- bootstrap
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Bootstrap', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'Bootstrapped', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Bootstrapping', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'CSS', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'HTML', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'front-end', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'frontend', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'JavaScript', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'jQuery', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Tailwind', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'responsive', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'React', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Angular', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Sass', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'bootstrap'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- web-api
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'ASP.NET Web API', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Web API', 1, 0, 1, NULL
    UNION ALL SELECT 'context', '.NET', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'ASP.NET', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'C#', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Microsoft', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Entity Framework', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'web-api'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- api-fetch
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Fetch API', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'API Fetch', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'api-fetch'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- retrofit
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Retrofit', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Android', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Kotlin', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Java', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'OkHttp', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Gson', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Jetpack', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'retrofit'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- soar
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SOAR', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'security', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'seguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentest', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentesting', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'penetration', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SOC', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SIEM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerability', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerabilidades', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forensics', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forense', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incident', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incidentes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ciberseguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cybersecurity', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kali', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'soar'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- volatility
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Volatility Framework', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Volatility', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'security', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'seguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentest', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentesting', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'penetration', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SOC', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SIEM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerability', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerabilidades', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forensics', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forense', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incident', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incidentes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ciberseguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cybersecurity', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kali', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'memory', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'memoria', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'DFIR', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'malware', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'volatility'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- hydra
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'THC Hydra', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'THC-Hydra', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Hydra', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'security', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'seguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentest', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentesting', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'penetration', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SOC', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SIEM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerability', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerabilidades', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forensics', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forense', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incident', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incidentes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ciberseguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cybersecurity', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kali', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'password', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'contraseñas', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'brute force', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'fuerza bruta', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Nmap', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Metasploit', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'hydra'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- owasp-zed-attack-proxy
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'OWASP Zed Attack Proxy', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'OWASP ZAP', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ZAP', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'OWASP', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Burp', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'security', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'seguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentest', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentesting', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'penetration', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SOC', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SIEM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerability', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerabilidades', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forensics', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forense', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incident', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incidentes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ciberseguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cybersecurity', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kali', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'owasp-zed-attack-proxy'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- norton
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Norton Antivirus', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Norton 360', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Norton', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'antivirus', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'McAfee', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Avast', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'endpoint', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'malware', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'security', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'seguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentest', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentesting', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'penetration', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SOC', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SIEM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerability', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerabilidades', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forensics', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forense', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incident', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incidentes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ciberseguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cybersecurity', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kali', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'norton'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- globus
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Globus', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'data transfer', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'HPC', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'research computing', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GridFTP', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'cluster', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'supercomputing', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'globus'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cockpit
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Cockpit', 1, 0, 1, NULL
    UNION ALL SELECT 'alias', 'COCKPIT', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Linux', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'RHEL', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Red Hat', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'server', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'servidor', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Fedora', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CentOS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'web console', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'cockpit'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- raspberry
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Raspberry Pi', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Raspberry', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'IoT', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Arduino', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Linux', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'embedded', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'embebido', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'sensor', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'sensores', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GPIO', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'Python', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'raspberry'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- wds
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Windows Deployment Services', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'WDS', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Windows', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'deployment', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'despliegue', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'WSUS', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Active Directory', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SCCM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'imaging', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'wds'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- jsps
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'JSP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'JSPs', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'JavaServer Pages', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'JSPS', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'jsps'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-report
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Microsoft Report Builder', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Report Builder', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SSRS', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SQL Server Reporting Services', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Reporting Services', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-report'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-excel-solver
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Excel Solver', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-excel-solver'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- kali-linux
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Kali Linux', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Kali', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'security', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'seguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentest', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'pentesting', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'penetration', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SOC', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SIEM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerability', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'vulnerabilidades', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forensics', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'forense', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incident', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'incidentes', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ciberseguridad', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'cybersecurity', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kali', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'kali-linux'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-maps-sdk-ios
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Maps SDK', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-maps-sdk-ios'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- ethercap
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Ettercap', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ethercap', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'ethercap'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- posenet
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'PoseNet', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'posenet'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- trend-vision-one
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Trend Vision One', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Trend Micro Vision One', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'trend-vision-one'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- standard-assets
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Standard Assets', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'standard-assets'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- sprite-mask
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Sprite Mask', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'sprite-mask'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- sprite-shape
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Sprite Shape', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'sprite-shape'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- shader-graph
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Shader Graph', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'shader-graph'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- transformers
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Hugging Face Transformers', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'HuggingFace Transformers', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Transformers library', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Transformers', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'power transformers', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'Transformers (?:Prime|movie|toys)', 0, 1, 0, NULL
    UNION ALL SELECT 'context', 'Hugging Face', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'HuggingFace', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'PyTorch', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'TensorFlow', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'NLP', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'LLM', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'LLMs', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'BERT', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GPT', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'fine-tuning', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'machine learning', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'deep learning', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'transformers'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- material-design
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Material Design', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Material UI', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MUI', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'material-design'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

SELECT CONCAT('Reglas sembradas: ', COUNT(*), ' (esperadas 976)') AS resultado
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'technology' AND a.notes = '[seed tecnologias]';
-- ====================== FIN DEL BLOQUE GENERADO =======================

-- Transformers: reactivar con sus reglas estrictas
UPDATE market_entities
   SET is_active = 1, inactive_reason = NULL, inactive_at = NULL
 WHERE entity_type = 'technology' AND slug = 'transformers' AND is_active = 0
   AND inactive_reason LIKE '[Tecnolog%2026-11] Ambiguo%';
SELECT CONCAT('Transformers reactivado: ', ROW_COUNT()) AS resultado;

COMMIT;

SELECT COUNT(DISTINCT a.market_entity_id) AS entidades_con_reglas, COUNT(*) AS reglas
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'technology';
SELECT 'OK 10: reglas de tecnologías cargadas' AS resultado;

-- #####################################################################
-- PARTE 13/17: Certificaciones 01 — tablas de resumen
-- #####################################################################

CREATE TABLE IF NOT EXISTS `certification_rankings` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`             SMALLINT UNSIGNED NOT NULL,
  `period`           ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,

  -- Copia de market_entities al momento del cálculo
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_isil`         TINYINT(1)        NOT NULL DEFAULT 0,
  `has_trend`        TINYINT(1)        NOT NULL DEFAULT 0,
  `classification`   VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  -- Métricas crudas
  `total_jobs`       INT UNSIGNED      NOT NULL DEFAULT 0,
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,

  -- Puntajes 0-100 sin redondear. final_score y position se recalculan al
  -- leer con los pesos vigentes; aquí quedan como referencia del cálculo.
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certification_rankings_period_entity` (`year`, `period`, `market_entity_id`),
  KEY `idx_certification_rankings_period_score` (`year`, `period`, `final_score`),
  KEY `idx_certification_rankings_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. certification_ranking_meta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certification_ranking_meta` (
  `id`                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `period`              ENUM('s1','s2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

  `vacantes_analizadas` INT UNSIGNED      NOT NULL DEFAULT 0,
  `reportes_analizados` INT UNSIGNED      NOT NULL DEFAULT 0,
  `max_labor`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`           INT UNSIGNED      NOT NULL DEFAULT 1,
  `labor_weight`        DECIMAL(4,3)      NOT NULL,
  `trend_weight`        DECIMAL(4,3)      NOT NULL,
  `rows_count`          INT UNSIGNED      NOT NULL DEFAULT 0,
  `duration_ms`         INT UNSIGNED      NOT NULL DEFAULT 0,

  `calculated_at`       TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certification_ranking_meta_period` (`year`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Tablas de Laravel para caché y colas (solo si faltan)
--    Mismo esquema que las migraciones 0001_01_01_000001/000002.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value`      MEDIUMTEXT   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner`      VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `queue`        VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`      LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL,
  `reserved_at`  INT UNSIGNED     DEFAULT NULL,
  `available_at` INT UNSIGNED     NOT NULL,
  `created_at`   INT UNSIGNED     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue`      TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload`    LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception`  LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Evolución (fotos por fecha de corte)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certification_ranking_weekly` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`      ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`       VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  -- Año ISO para weekly (la semana 1 de 2027 puede empezar el 28-dic-2026); año calendario para el resto
  `year`             SMALLINT UNSIGNED NOT NULL,
  `cutoff_date`      DATE              NOT NULL,              -- fecha de la foto
  `semester`         CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,  -- '2026-s2'
  `market_entity_id` BIGINT UNSIGNED   NOT NULL,
  `name`             VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jobs`             INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes acumuladas del semestre al corte
  `new_jobs`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- vacantes publicadas dentro de la ventana
  `trend_reports`    INT UNSIGNED      NOT NULL DEFAULT 0,      -- reportes del semestre disponibles al corte
  `labor_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `trend_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,
  `final_score`      DECIMAL(9,4)      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `position`         INT UNSIGNED      NOT NULL DEFAULT 0,      -- referencia (pesos del cálculo)
  `calculated_at`    TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crw_window_entity` (`period_type`, `period_key`, `market_entity_id`),
  KEY `idx_crw_type_year` (`period_type`, `year`),
  KEY `idx_crw_entity` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `certification_ranking_weekly_meta` (
  `id`                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `period_type`       ENUM('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key`        VARCHAR(16)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year`              SMALLINT UNSIGNED NOT NULL,
  `label`             VARCHAR(80)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date`        DATE              NOT NULL,
  `end_date`          DATE              NOT NULL,
  `cutoff_date`       DATE              NOT NULL,             -- end_date, o hoy si está en curso
  `semester`          CHAR(7)      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_semester_start` TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = primera foto de un semestre (el acumulado se reinicia)
  `is_partial`        TINYINT(1)        NOT NULL DEFAULT 0,   -- 1 = ventana en curso
  `unique_offers`     INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas acumuladas del semestre al corte
  `new_unique_offers` INT UNSIGNED      NOT NULL DEFAULT 0,   -- vacantes únicas publicadas dentro de la ventana
  `max_labor`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `max_trend`         INT UNSIGNED      NOT NULL DEFAULT 1,
  `entities_count`    INT UNSIGNED      NOT NULL DEFAULT 0,
  `calculated_at`     TIMESTAMP         NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crwm_window` (`period_type`, `period_key`),
  KEY `idx_crwm_type_year_start` (`period_type`, `year`, `start_date`),
  KEY `idx_crwm_type_start` (`period_type`, `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK 01: tablas del ranking de certificaciones listas' AS resultado;

-- #####################################################################
-- PARTE 14/17: Certificaciones 02 — índices
-- #####################################################################

SET @tbl = 'certification_job', @idx = 'idx_cj_job_entity', @cols = 'job_offer_id,market_entity_id';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 1 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 2. job_offers (published_at)
--    Rango por fecha de publicación. En la base de desarrollo ya existe
--    como idx_jo_published_at; si existe con otro nombre, se omite.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_published_at', @cols = 'published_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 2 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. job_offers (created_at)
--    JobMarketStatus: MIN/MAX(created_at), ofertas del mes y del periodo.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_created_at', @cols = 'created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 3 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 4. job_offers (source, created_at)
--    JobMarketStatus: COUNT(*) y MAX(created_at) por fuente.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_source_created', @cols = 'source,created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 4 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 5. scraper_runs (entity, finished_at)
--    ScrapingStatusService: últimas ejecuciones por entidad.
-- ---------------------------------------------------------------------
SET @tbl = 'scraper_runs', @idx = 'idx_scraper_runs_entity_finished', @cols = 'entity,finished_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 5 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

SELECT 'OK 02: índices del ranking de certificaciones revisados' AS resultado;

-- #####################################################################
-- PARTE 15/17: Certificaciones 05 — entidades (duplicados, CompTIA A+, catálogo)
-- #####################################################################

CREATE TABLE IF NOT EXISTS `_respaldo_certificaciones_2026_11` (
  `tabla`          VARCHAR(64)  NOT NULL,
  `fila_id`        BIGINT UNSIGNED NOT NULL,
  `columna`        VARCHAR(64)  NOT NULL,
  `valor_anterior` VARCHAR(255) NULL,
  `respaldado_en`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabla`, `fila_id`, `columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Duplicados (slug duplicado → slug principal)
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `_respaldo_certificaciones_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'market_entities', d.id, 'is_active', d.is_active
  FROM (
    SELECT _utf8mb4'certified-information-systems-security-professional-cissp' COLLATE utf8mb4_unicode_ci AS dup, _utf8mb4'cissp' COLLATE utf8mb4_unicode_ci AS principal
    UNION ALL SELECT 'certified-information-security-manager-cism', 'cism'
    UNION ALL SELECT 'microsoft-certified-azure-security-engineer-associate', 'microsoft-azure-security-engineer-(az-500)'
    UNION ALL SELECT 'microsoft-certified-azure-devops-engineer-expert', 'microsoft-azure-devops-engineer-(az-400)'
    UNION ALL SELECT 'microsoft-certified-azure-ai-engineer-associate', 'microsoft-azure-ai-engineer-(ai-102)'
    UNION ALL SELECT 'aws-certified-machine-learning-specialty', 'aws-machine-learning-–-specialty'
    UNION ALL SELECT 'aws-certified-advanced-networking-specialty', 'aws-advanced-networking-–-specialty'
    UNION ALL SELECT 'aws-certified-devops-engineer-professional', 'aws-devops-engineer-–-professional'
    UNION ALL SELECT 'microsoft-certified-azure-data-engineer-associate', 'microsoft-azure-data-engineer-(dp-203)'
    UNION ALL SELECT 'microsoft-certified-azure-solutions-architect-expert-az-303-az-304', 'microsoft-azure-solutions-architect-(az-305)'
    UNION ALL SELECT 'oracle-machine-learning-2021-certified-professional', 'oracle-machine-learning-2022-certified-professional'
    UNION ALL SELECT 'oracle-cloud-infrastructure-data-science-2021-certified-professional', 'oracle-cloud-infrastructure-data-science-2022-certified-professional'
  ) x
  JOIN market_entities d ON d.entity_type = 'certification' AND d.slug = x.dup AND d.is_active = 1
  JOIN market_entities p ON p.entity_type = 'certification' AND p.slug = x.principal AND p.is_active = 1;

UPDATE market_entities d
  JOIN (
    SELECT _utf8mb4'certified-information-systems-security-professional-cissp' COLLATE utf8mb4_unicode_ci AS dup, _utf8mb4'cissp' COLLATE utf8mb4_unicode_ci AS principal
    UNION ALL SELECT 'certified-information-security-manager-cism', 'cism'
    UNION ALL SELECT 'microsoft-certified-azure-security-engineer-associate', 'microsoft-azure-security-engineer-(az-500)'
    UNION ALL SELECT 'microsoft-certified-azure-devops-engineer-expert', 'microsoft-azure-devops-engineer-(az-400)'
    UNION ALL SELECT 'microsoft-certified-azure-ai-engineer-associate', 'microsoft-azure-ai-engineer-(ai-102)'
    UNION ALL SELECT 'aws-certified-machine-learning-specialty', 'aws-machine-learning-–-specialty'
    UNION ALL SELECT 'aws-certified-advanced-networking-specialty', 'aws-advanced-networking-–-specialty'
    UNION ALL SELECT 'aws-certified-devops-engineer-professional', 'aws-devops-engineer-–-professional'
    UNION ALL SELECT 'microsoft-certified-azure-data-engineer-associate', 'microsoft-azure-data-engineer-(dp-203)'
    UNION ALL SELECT 'microsoft-certified-azure-solutions-architect-expert-az-303-az-304', 'microsoft-azure-solutions-architect-(az-305)'
    UNION ALL SELECT 'oracle-machine-learning-2021-certified-professional', 'oracle-machine-learning-2022-certified-professional'
    UNION ALL SELECT 'oracle-cloud-infrastructure-data-science-2021-certified-professional', 'oracle-cloud-infrastructure-data-science-2022-certified-professional'
  ) x ON d.slug = x.dup
  JOIN market_entities p ON p.entity_type = 'certification' AND p.slug = x.principal AND p.is_active = 1
   SET d.is_active = 0, d.inactive_at = NOW(),
       d.inactive_reason = LEFT(CONCAT('[Certificaciones 2026-11] duplicado de ', p.name), 255)
 WHERE d.entity_type = 'certification' AND d.is_active = 1;
SELECT CONCAT('Duplicados desactivados: ', ROW_COUNT()) AS resultado;

-- ---------------------------------------------------------------------
-- 2. CompTIA A+ como certificación
-- ---------------------------------------------------------------------
INSERT INTO market_entities (name, slug, entity_type, origin, category, vendor, level, has_isil, has_trend, status,
                             discovery_method, is_active, created_at, updated_at)
SELECT 'CompTIA A+', 'comptia-a-plus', 'certification', 'market', 'Soporte TI', 'CompTIA', 'Fundamentos', 0, 0,
       'validated', 'fase-4-tecnologias', 1, NOW(), NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM market_entities WHERE entity_type = 'certification' AND slug = 'comptia-a-plus');
SET @creada = ROW_COUNT();
INSERT IGNORE INTO `_respaldo_certificaciones_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'market_entities', id, 'creada', NULL FROM market_entities
 WHERE entity_type = 'certification' AND slug = 'comptia-a-plus' AND @creada > 0;
SELECT CONCAT('CompTIA A+ creada: ', @creada) AS resultado;

-- Re-aplicación después de 06: reactivar CompTIA A+
INSERT IGNORE INTO `_respaldo_certificaciones_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'market_entities', id, 'creada', NULL FROM market_entities
 WHERE entity_type = 'certification' AND slug = 'comptia-a-plus' AND is_active = 0
   AND inactive_reason LIKE 'Revertido por 06%';
UPDATE market_entities SET is_active = 1, inactive_reason = NULL, inactive_at = NULL
 WHERE entity_type = 'certification' AND slug = 'comptia-a-plus' AND is_active = 0
   AND inactive_reason LIKE 'Revertido por 06%';

-- ---------------------------------------------------------------------
-- 3. Fila de catálogo para cada certificación activa sin ella
-- ---------------------------------------------------------------------
-- Filas existentes deshabilitadas de certificaciones activas sin ninguna habilitada: rehabilitar
INSERT IGNORE INTO `_respaldo_certificaciones_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'certifications', c.id, 'enabled', c.enabled
  FROM certifications c JOIN market_entities me ON me.id = c.market_entity_id
 WHERE me.entity_type = 'certification' AND me.is_active = 1 AND c.enabled = 0
   AND NOT EXISTS (SELECT 1 FROM certifications c2 WHERE c2.market_entity_id = me.id AND c2.enabled = 1);
UPDATE certifications c JOIN market_entities me ON me.id = c.market_entity_id
   SET c.enabled = 1, c.updated_at = NOW()
 WHERE me.entity_type = 'certification' AND me.is_active = 1 AND c.enabled = 0
   AND NOT EXISTS (SELECT 1 FROM (SELECT market_entity_id FROM certifications WHERE enabled = 1) c2 WHERE c2.market_entity_id = me.id);
SELECT CONCAT('Filas de certifications rehabilitadas: ', ROW_COUNT()) AS resultado;

INSERT INTO certifications (name, vendor, level, category, enabled, market_entity_id, created_at, updated_at)
SELECT me.name, me.vendor, me.level, me.category, 1, me.id, NOW(), NOW()
  FROM market_entities me
 WHERE me.entity_type = 'certification' AND me.is_active = 1
   AND NOT EXISTS (SELECT 1 FROM certifications c WHERE c.market_entity_id = me.id)
   AND NOT EXISTS (SELECT 1 FROM certifications c WHERE c.name = me.name);
SET @catalogo = ROW_COUNT();
SET @primer_id = LAST_INSERT_ID();
INSERT IGNORE INTO `_respaldo_certificaciones_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'certifications', c.id, 'creada', NULL FROM certifications c
 WHERE @catalogo > 0 AND c.id >= @primer_id AND c.id < @primer_id + @catalogo;
SELECT CONCAT('Filas de certifications creadas: ', @catalogo) AS resultado;

-- Varios scrapers usan certifications.keywords como término de búsqueda y omiten las filas sin
-- keywords (JSON): las que no tienen reciben [nombre] (la asociación la decide el matcher).
INSERT IGNORE INTO `_respaldo_certificaciones_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'certifications', id, 'keywords', NULL FROM certifications WHERE enabled = 1 AND (keywords IS NULL OR keywords = '');
UPDATE certifications SET keywords = JSON_ARRAY(name), updated_at = NOW() WHERE enabled = 1 AND (keywords IS NULL OR keywords = '');
SELECT CONCAT('Filas de certifications con keywords = [nombre]: ', ROW_COUNT()) AS resultado;

-- (Paso 4, copia de asociaciones desde tecnologías: excluido; solo tiene sentido con la limpieza)

COMMIT;


-- Verificación
SELECT is_active, COUNT(*) AS certificaciones FROM market_entities WHERE entity_type = 'certification' GROUP BY is_active;
SELECT COUNT(*) AS activas_sin_catalogo FROM market_entities me
 WHERE me.entity_type = 'certification' AND me.is_active = 1
   AND NOT EXISTS (SELECT 1 FROM certifications c WHERE c.market_entity_id = me.id AND c.enabled = 1);
SELECT 'OK 05: entidades de certificaciones ajustadas' AS resultado;

-- #####################################################################
-- PARTE 16/17: Certificaciones 07 — reglas del matcher
-- #####################################################################

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
  CONSTRAINT `fk_mea_entity` FOREIGN KEY (`market_entity_id`) REFERENCES `market_entities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- ===================== INICIO DEL BLOQUE GENERADO =====================
-- Generado por docs/produccion/herramientas/generar_aliases_sql.php certifications certification certificaciones
-- 66 entidades. Las entidades que no existan en este entorno se omiten solas (JOIN por slug).
-- (Sin borrado previo: INSERT IGNORE no duplica reglas al ejecutar dos veces)

-- aws-certified-cloud-practitioner
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Cloud Practitioner', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Cloud Practitioner', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CLF-C01', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CLF-C02', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-certified-cloud-practitioner'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-solutions-architect-–-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Solutions Architect Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Solutions Architect Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS SAA', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SAA-C02', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SAA-C03', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-solutions-architect-–-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-solutions-architect-–-professional
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Solutions Architect Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Solutions Architect Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SAP-C01', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SAP-C02', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-solutions-architect-–-professional'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-developer-–-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Developer Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Developer Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DVA-C01', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DVA-C02', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-developer-–-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-sysops-administrator-–-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified SysOps Administrator', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS SysOps Administrator', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Certified SysOps', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SOA-C01', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SOA-C02', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-sysops-administrator-–-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-devops-engineer-–-professional
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified DevOps Engineer Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS DevOps Engineer Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DOP-C01', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DOP-C02', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-devops-engineer-–-professional'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-security-–-specialty
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Security Specialty', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Security Specialty', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SCS-C01', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SCS-C02', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-security-–-specialty'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-advanced-networking-–-specialty
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Advanced Networking', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Advanced Networking', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ANS-C01', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-advanced-networking-–-specialty'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-machine-learning-–-specialty
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Machine Learning Specialty', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Machine Learning Specialty', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Certified Machine Learning', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MLS-C01', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-machine-learning-–-specialty'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-certified-database-specialty
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Database Specialty', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Database Specialty', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DBS-C01', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-certified-database-specialty'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- aws-certified-data-analytics-specialty
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Certified Data Analytics', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Data Analytics Specialty', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DAS-C01', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-certified-data-analytics-specialty'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-fundamentals-(az-900)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AZ 900', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ900', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Fundamentals', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-fundamentals-(az-900)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-administrator-(az-104)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AZ 104', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ104', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Administrator Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Certified Azure Administrator', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-administrator-(az-104)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-solutions-architect-(az-305)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AZ 305', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ305', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ 303', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ 304', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Solutions Architect Expert', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Certified Azure Solutions Architect', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-solutions-architect-(az-305)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-developer-(az-204)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AZ 204', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ204', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Developer Associate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-developer-(az-204)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-devops-engineer-(az-400)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AZ 400', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ400', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure DevOps Engineer Expert', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-devops-engineer-(az-400)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-security-engineer-(az-500)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AZ 500', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AZ500', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Security Engineer Associate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-security-engineer-(az-500)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-data-engineer-(dp-203)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'DP 203', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DP203', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Data Engineer Associate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-data-engineer-(dp-203)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-azure-ai-engineer-(ai-102)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AI 102', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AI102', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure AI Engineer Associate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-ai-engineer-(ai-102)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-certified-azure-ai-fundamentals-ai-900
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AI 900', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AI900', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure AI Fundamentals', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-certified-azure-ai-fundamentals-ai-900'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-certified-azure-data-fundamentals-dp-900
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'DP 900', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DP900', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Data Fundamentals', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-certified-azure-data-fundamentals-dp-900'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- microsoft-certified-azure-data-scientist-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'DP 100', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DP100', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure Data Scientist Associate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-certified-azure-data-scientist-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-associate-cloud-engineer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Associate Cloud Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google ACE', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-associate-cloud-engineer'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-cloud-digital-leader
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Cloud Digital Leader', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-cloud-digital-leader'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-professional-cloud-architect
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Professional Cloud Architect', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google Cloud Professional Cloud Architect', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'GCP Professional Cloud Architect', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Professional Cloud Architect', 0, 0, 1, NULL
    UNION ALL SELECT 'context', 'Google', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-architect'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-professional-cloud-devops-engineer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Professional Cloud DevOps Engineer', 0, 0, 1, NULL
    UNION ALL SELECT 'context', 'Google', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-devops-engineer'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-professional-cloud-network-engineer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Professional Cloud Network Engineer', 0, 0, 1, NULL
    UNION ALL SELECT 'context', 'Google', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-network-engineer'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-professional-cloud-security-engineer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Professional Cloud Security Engineer', 0, 0, 1, NULL
    UNION ALL SELECT 'context', 'Google', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 80
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 80
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-security-engineer'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-professional-data-engineer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Professional Data Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google Cloud Professional Data Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'GCP Professional Data Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Professional Data Engineer', 0, 0, 1, NULL
    UNION ALL SELECT 'context', 'Google', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 40
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-data-engineer'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- google-professional-machine-learning-engineer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Professional Machine Learning Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google Cloud Professional Machine Learning Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Professional Machine Learning Engineer', 0, 0, 1, NULL
    UNION ALL SELECT 'context', 'Google', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'GCP', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 40
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-machine-learning-engineer'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-kubernetes-administrator-(cka)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Kubernetes Administrator', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CKA', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-kubernetes-administrator-(cka)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-kubernetes-application-developer-(ckad)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Kubernetes Application Developer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CKAD', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-kubernetes-application-developer-(ckad)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-kubernetes-security-specialist-(cks)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Kubernetes Security Specialist', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CKS', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-kubernetes-security-specialist-(cks)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- docker-certified-associate-(dca)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Docker Certified Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DCA', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'Docker', 0, 0, 0, 40
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'docker-certified-associate-(dca)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- hashicorp-terraform-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Terraform Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'HashiCorp Certified Terraform', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'hashicorp-terraform-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- hashicorp-vault-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Vault Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'HashiCorp Certified Vault', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'hashicorp-vault-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- databricks-data-engineer-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Databricks Certified Data Engineer Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Databricks Data Engineer Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Databricks Certified Data Engineer', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'databricks-data-engineer-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- linux-foundation-certified-sysadmin-(lfcs)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Linux Foundation Certified System Administrator', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Linux Foundation Certified SysAdmin', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'LFCS', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'linux-foundation-certified-sysadmin-(lfcs)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- linux-foundation-certified-engineer-(lfce)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Linux Foundation Certified Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'LFCE', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'linux-foundation-certified-engineer-(lfce)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cissp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CISSP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Certified Information Systems Security Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cissp'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cism
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CISM', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Certified Information Security Manager', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cism'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-information-systems-auditor-cisa
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Information Systems Auditor', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CISA', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'CISA KEV', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'CISA advisory', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'CISA advisories', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'ISACA', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CISSP', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CISM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'audit', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'auditor', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'auditoría', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-information-systems-auditor-cisa'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-cloud-security-professional-ccsp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Cloud Security Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CCSP', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-cloud-security-professional-ccsp'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-cloud-security-specialist-ccss
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Cloud Security Specialist', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-cloud-security-specialist-ccss'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-ethical-hacker-(ceh)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Ethical Hacker', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CEH', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-ethical-hacker-(ceh)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-information-privacy-professional-cipp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Information Privacy Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CIPP', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-information-privacy-professional-cipp'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-information-privacy-technologist-cipt
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Information Privacy Technologist', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CIPT', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-information-privacy-technologist-cipt'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- comptia-security+
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CompTIA Security+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Security+', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Sec+', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-security+'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- comptia-network+
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CompTIA Network+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Network+', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-network+'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- comptia-cysa+
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CompTIA CySA+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CySA+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CySA', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-cysa+'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- comptia-pentest+
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CompTIA PenTest+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PenTest+', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-pentest+'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- comptia-a-plus
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CompTIA A+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'A+ certification', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'A+ certified', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'A+ certificate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-a-plus'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cisco-ccna
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CCNA', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco Certified Network Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'CCNA CyberOps', 0, 0, 0, NULL
    UNION ALL SELECT 'negative', 'CCNA Cyber Ops', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-ccna'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cisco-ccnp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CCNP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco Certified Network Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-ccnp'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cisco-certified-internetwork-expert-ccie
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CCIE', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco Certified Internetwork Expert', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-certified-internetwork-expert-ccie'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cisco-certified-cyberops-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CyberOps Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CCNA CyberOps', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CCNA Cyber Ops', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco CyberOps', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-certified-cyberops-associate'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- cisco-certified-devnet-professional
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'DevNet Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco Certified DevNet Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-certified-devnet-professional'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- project-management-professional-pmp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'PMP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Project Management Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'project-management-professional-pmp'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- pmi-acp-(agile-certified-practitioner)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'PMI-ACP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PMI ACP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Agile Certified Practitioner', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'pmi-acp-(agile-certified-practitioner)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-scrum-master-(csm)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Scrum Master', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Certified ScrumMaster', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CSM', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'Customer Success Manager', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'Scrum', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Agile', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ágil', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Scrum Alliance', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'PSM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SAFe', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kanban', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-scrum-master-(csm)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- professional-scrum-master-(psm-i)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Professional Scrum Master', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PSM I', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PSM 1', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PSM-I', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PSM', 1, 0, 1, NULL
    UNION ALL SELECT 'negative', 'Process Safety Management', 0, 0, 0, NULL
    UNION ALL SELECT 'context', 'Scrum', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Scrum.org', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Agile', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'ágil', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'CSM', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'SAFe', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'professional-scrum-master-(psm-i)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- safe-agilist-(sa)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SAFe Agilist', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Leading SAFe', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SAFe SA', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Certified SAFe Agilist', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'safe-agilist-(sa)'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- safe-devops-practitioner
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SAFe DevOps Practitioner', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SAFe DevOps', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'safe-devops-practitioner'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- itil-4-foundation
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'ITIL 4 Foundation', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ITIL Foundation', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ITIL v4', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ITIL 4', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ITIL v3', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ITIL certified', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ITIL certification', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'certificación ITIL', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ITIL', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'certification', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certifications', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificación', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificaciones', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificate', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificado', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certified', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificada', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'certificado/a', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'credential', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'exam', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'examen', 0, 0, 0, 40
    UNION ALL SELECT 'context', 'accreditation', 0, 0, 0, 40
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'itil-4-foundation'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-data-management-professional-cdmp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Data Management Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CDMP', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-data-management-professional-cdmp'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

-- certified-data-scientist-dasca
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Data Scientist DASCA', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DASCA', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-data-scientist-dasca'
   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind
                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);

SELECT CONCAT('Reglas sembradas: ', COUNT(*), ' (esperadas 333)') AS resultado
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'certification' AND a.notes = '[seed certificaciones]';
-- ====================== FIN DEL BLOQUE GENERADO =======================

COMMIT;

SELECT COUNT(DISTINCT a.market_entity_id) AS certificaciones_con_reglas, COUNT(*) AS reglas
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'certification';
SELECT 'OK 07: reglas de certificaciones cargadas' AS resultado;

-- #####################################################################
-- PARTE 17/17: Certificaciones 12 — términos de búsqueda (keywords)
-- #####################################################################

CREATE TABLE IF NOT EXISTS `_respaldo_keywords_cert_2026_11` (
  `certification_id` BIGINT UNSIGNED NOT NULL,
  `keywords_anterior` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `respaldado_en`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`certification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


START TRANSACTION;

INSERT IGNORE INTO `_respaldo_keywords_cert_2026_11` (certification_id, keywords_anterior)
SELECT c.id, c.keywords
  FROM certifications c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'certification'
  JOIN (
    SELECT _utf8mb4'aws-certified-cloud-practitioner' COLLATE utf8mb4_unicode_ci AS slug, _utf8mb4'["AWS Certified Cloud Practitioner", "AWS Cloud Practitioner", "CLF-C02"]' COLLATE utf8mb4_unicode_ci AS terms
    UNION ALL SELECT 'aws-solutions-architect-–-associate', '["AWS Certified Solutions Architect Associate", "AWS Solutions Architect Associate", "SAA-C03"]'
    UNION ALL SELECT 'aws-solutions-architect-–-professional', '["AWS Certified Solutions Architect Professional", "AWS Solutions Architect Professional", "SAP-C02"]'
    UNION ALL SELECT 'aws-developer-–-associate', '["AWS Certified Developer Associate", "AWS Developer Associate", "DVA-C02"]'
    UNION ALL SELECT 'aws-sysops-administrator-–-associate', '["AWS Certified SysOps Administrator", "AWS SysOps Administrator", "SOA-C02"]'
    UNION ALL SELECT 'aws-devops-engineer-–-professional', '["AWS Certified DevOps Engineer", "AWS DevOps Engineer Professional", "DOP-C02"]'
    UNION ALL SELECT 'aws-security-–-specialty', '["AWS Certified Security Specialty", "AWS Security Specialty", "SCS-C02"]'
    UNION ALL SELECT 'aws-advanced-networking-–-specialty', '["AWS Certified Advanced Networking", "AWS Advanced Networking Specialty", "ANS-C01"]'
    UNION ALL SELECT 'aws-machine-learning-–-specialty', '["AWS Certified Machine Learning", "AWS Machine Learning Specialty", "MLS-C01"]'
    UNION ALL SELECT 'aws-certified-database-specialty', '["AWS Certified Database Specialty", "AWS Database Specialty", "DBS-C01"]'
    UNION ALL SELECT 'aws-certified-data-analytics-specialty', '["AWS Certified Data Analytics", "AWS Data Analytics Specialty", "DAS-C01"]'
    UNION ALL SELECT 'microsoft-azure-fundamentals-(az-900)', '["AZ-900", "Azure Fundamentals"]'
    UNION ALL SELECT 'microsoft-azure-administrator-(az-104)', '["AZ-104", "Azure Administrator Associate"]'
    UNION ALL SELECT 'microsoft-azure-solutions-architect-(az-305)', '["AZ-305", "Azure Solutions Architect Expert"]'
    UNION ALL SELECT 'microsoft-azure-developer-(az-204)', '["AZ-204", "Azure Developer Associate"]'
    UNION ALL SELECT 'microsoft-azure-devops-engineer-(az-400)', '["AZ-400", "Azure DevOps Engineer Expert"]'
    UNION ALL SELECT 'microsoft-azure-security-engineer-(az-500)', '["AZ-500", "Azure Security Engineer Associate"]'
    UNION ALL SELECT 'microsoft-azure-data-engineer-(dp-203)', '["DP-203", "Azure Data Engineer Associate"]'
    UNION ALL SELECT 'microsoft-azure-ai-engineer-(ai-102)', '["AI-102", "Azure AI Engineer Associate"]'
    UNION ALL SELECT 'microsoft-certified-azure-ai-fundamentals-ai-900', '["AI-900", "Azure AI Fundamentals"]'
    UNION ALL SELECT 'microsoft-certified-azure-data-fundamentals-dp-900', '["DP-900", "Azure Data Fundamentals"]'
    UNION ALL SELECT 'microsoft-certified-azure-data-scientist-associate', '["DP-100", "Azure Data Scientist Associate"]'
    UNION ALL SELECT 'microsoft-certified-azure-ai-ethics-specialist', '["Azure AI Ethics Specialist"]'
    UNION ALL SELECT 'responsible-ai-certification-by-microsoft', '["Responsible AI Certification Microsoft"]'
    UNION ALL SELECT 'google-associate-cloud-engineer', '["Google Associate Cloud Engineer", "Associate Cloud Engineer"]'
    UNION ALL SELECT 'google-cloud-digital-leader', '["Google Cloud Digital Leader", "Cloud Digital Leader"]'
    UNION ALL SELECT 'google-professional-cloud-architect', '["Google Professional Cloud Architect", "Professional Cloud Architect"]'
    UNION ALL SELECT 'google-professional-cloud-devops-engineer', '["Google Professional Cloud DevOps Engineer", "Professional Cloud DevOps Engineer"]'
    UNION ALL SELECT 'google-professional-cloud-network-engineer', '["Google Professional Cloud Network Engineer", "Professional Cloud Network Engineer"]'
    UNION ALL SELECT 'google-professional-cloud-security-engineer', '["Google Professional Cloud Security Engineer", "Professional Cloud Security Engineer"]'
    UNION ALL SELECT 'google-professional-data-engineer', '["Google Professional Data Engineer", "GCP Professional Data Engineer"]'
    UNION ALL SELECT 'google-professional-machine-learning-engineer', '["Google Professional Machine Learning Engineer", "Google Cloud Machine Learning Engineer"]'
    UNION ALL SELECT 'certified-kubernetes-administrator-(cka)', '["CKA", "Certified Kubernetes Administrator"]'
    UNION ALL SELECT 'certified-kubernetes-application-developer-(ckad)', '["CKAD", "Certified Kubernetes Application Developer"]'
    UNION ALL SELECT 'certified-kubernetes-security-specialist-(cks)', '["CKS", "Certified Kubernetes Security Specialist"]'
    UNION ALL SELECT 'docker-certified-associate-(dca)', '["Docker Certified Associate", "Docker DCA"]'
    UNION ALL SELECT 'hashicorp-terraform-associate', '["Terraform Associate", "HashiCorp Certified Terraform Associate"]'
    UNION ALL SELECT 'hashicorp-vault-associate', '["Vault Associate", "HashiCorp Certified Vault Associate"]'
    UNION ALL SELECT 'databricks-data-engineer-associate', '["Databricks Certified Data Engineer", "Databricks Data Engineer Associate"]'
    UNION ALL SELECT 'linux-foundation-certified-sysadmin-(lfcs)', '["LFCS", "Linux Foundation Certified System Administrator"]'
    UNION ALL SELECT 'linux-foundation-certified-engineer-(lfce)', '["LFCE", "Linux Foundation Certified Engineer"]'
    UNION ALL SELECT 'cissp', '["CISSP", "Certified Information Systems Security Professional"]'
    UNION ALL SELECT 'cism', '["CISM", "Certified Information Security Manager"]'
    UNION ALL SELECT 'certified-information-systems-auditor-cisa', '["Certified Information Systems Auditor", "CISA ISACA"]'
    UNION ALL SELECT 'certified-cloud-security-professional-ccsp', '["CCSP", "Certified Cloud Security Professional"]'
    UNION ALL SELECT 'certified-cloud-security-specialist-ccss', '["Certified Cloud Security Specialist"]'
    UNION ALL SELECT 'certified-ethical-hacker-(ceh)', '["CEH", "Certified Ethical Hacker"]'
    UNION ALL SELECT 'certified-information-privacy-professional-cipp', '["CIPP", "Certified Information Privacy Professional"]'
    UNION ALL SELECT 'certified-information-privacy-technologist-cipt', '["CIPT", "Certified Information Privacy Technologist"]'
    UNION ALL SELECT 'comptia-security+', '["Security+", "CompTIA Security+"]'
    UNION ALL SELECT 'comptia-network+', '["Network+", "CompTIA Network+"]'
    UNION ALL SELECT 'comptia-cysa+', '["CySA+", "CompTIA CySA+"]'
    UNION ALL SELECT 'comptia-pentest+', '["PenTest+", "CompTIA PenTest+"]'
    UNION ALL SELECT 'comptia-a-plus', '["CompTIA A+", "A+ certification"]'
    UNION ALL SELECT 'cisco-ccna', '["CCNA", "Cisco Certified Network Associate"]'
    UNION ALL SELECT 'cisco-ccnp', '["CCNP", "Cisco Certified Network Professional"]'
    UNION ALL SELECT 'cisco-certified-internetwork-expert-ccie', '["CCIE", "Cisco Certified Internetwork Expert"]'
    UNION ALL SELECT 'cisco-certified-cyberops-associate', '["CyberOps Associate", "CCNA CyberOps"]'
    UNION ALL SELECT 'cisco-certified-devnet-professional', '["DevNet Professional", "Cisco Certified DevNet Professional"]'
    UNION ALL SELECT 'project-management-professional-pmp', '["PMP", "Project Management Professional"]'
    UNION ALL SELECT 'pmi-acp-(agile-certified-practitioner)', '["PMI-ACP", "Agile Certified Practitioner"]'
    UNION ALL SELECT 'certified-scrum-master-(csm)', '["Certified Scrum Master", "Certified ScrumMaster", "CSM Scrum"]'
    UNION ALL SELECT 'professional-scrum-master-(psm-i)', '["Professional Scrum Master", "PSM I"]'
    UNION ALL SELECT 'safe-agilist-(sa)', '["SAFe Agilist", "Leading SAFe"]'
    UNION ALL SELECT 'safe-devops-practitioner', '["SAFe DevOps Practitioner", "SAFe DevOps"]'
    UNION ALL SELECT 'itil-4-foundation', '["ITIL 4 Foundation", "ITIL Foundation", "ITIL certification"]'
    UNION ALL SELECT 'certified-data-management-professional-cdmp', '["CDMP", "Certified Data Management Professional"]'
    UNION ALL SELECT 'certified-data-scientist-dasca', '["DASCA", "Certified Data Scientist DASCA"]'
    UNION ALL SELECT 'ibm-ai-engineering-professional-certificate', '["IBM AI Engineering Professional Certificate"]'
    UNION ALL SELECT 'ibm-ai-ethics-professional-certificate', '["IBM AI Ethics Professional Certificate"]'
    UNION ALL SELECT 'ibm-certified-professional-data-scientist', '["IBM Certified Data Scientist", "IBM Certified Professional Data Scientist"]'
    UNION ALL SELECT 'ibm-data-science-professional-certificate', '["IBM Data Science Professional Certificate"]'
    UNION ALL SELECT 'oracle-cloud-data-science-certified-associate', '["Oracle Cloud Data Science Certified Associate"]'
    UNION ALL SELECT 'oracle-cloud-infrastructure-data-science-2022-certified-professional', '["OCI Data Science Professional", "Oracle Cloud Infrastructure Data Science Professional"]'
    UNION ALL SELECT 'oracle-cloud-infrastructure-data-science-certified-associate', '["OCI Data Science Associate", "Oracle Cloud Infrastructure Data Science Associate"]'
    UNION ALL SELECT 'oracle-cloud-infrastructure-machine-learning-certified-specialist', '["OCI Machine Learning Specialist", "Oracle Cloud Infrastructure Machine Learning"]'
    UNION ALL SELECT 'oracle-machine-learning-2022-certified-professional', '["Oracle Machine Learning Certified Professional"]'
    UNION ALL SELECT 'oracle-machine-learning-cloud-certified-specialist', '["Oracle Machine Learning Cloud Specialist"]'
    UNION ALL SELECT 'oracle-machine-learning-cloud-service-certified-implementation-specialist', '["Oracle Machine Learning Cloud Service Implementation Specialist"]'
  ) k ON k.slug = me.slug
 WHERE c.keywords IS NULL OR NOT (CAST(c.keywords AS JSON) = CAST(k.terms AS JSON));

UPDATE certifications c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'certification'
  JOIN (
    SELECT _utf8mb4'aws-certified-cloud-practitioner' COLLATE utf8mb4_unicode_ci AS slug, _utf8mb4'["AWS Certified Cloud Practitioner", "AWS Cloud Practitioner", "CLF-C02"]' COLLATE utf8mb4_unicode_ci AS terms
    UNION ALL SELECT 'aws-solutions-architect-–-associate', '["AWS Certified Solutions Architect Associate", "AWS Solutions Architect Associate", "SAA-C03"]'
    UNION ALL SELECT 'aws-solutions-architect-–-professional', '["AWS Certified Solutions Architect Professional", "AWS Solutions Architect Professional", "SAP-C02"]'
    UNION ALL SELECT 'aws-developer-–-associate', '["AWS Certified Developer Associate", "AWS Developer Associate", "DVA-C02"]'
    UNION ALL SELECT 'aws-sysops-administrator-–-associate', '["AWS Certified SysOps Administrator", "AWS SysOps Administrator", "SOA-C02"]'
    UNION ALL SELECT 'aws-devops-engineer-–-professional', '["AWS Certified DevOps Engineer", "AWS DevOps Engineer Professional", "DOP-C02"]'
    UNION ALL SELECT 'aws-security-–-specialty', '["AWS Certified Security Specialty", "AWS Security Specialty", "SCS-C02"]'
    UNION ALL SELECT 'aws-advanced-networking-–-specialty', '["AWS Certified Advanced Networking", "AWS Advanced Networking Specialty", "ANS-C01"]'
    UNION ALL SELECT 'aws-machine-learning-–-specialty', '["AWS Certified Machine Learning", "AWS Machine Learning Specialty", "MLS-C01"]'
    UNION ALL SELECT 'aws-certified-database-specialty', '["AWS Certified Database Specialty", "AWS Database Specialty", "DBS-C01"]'
    UNION ALL SELECT 'aws-certified-data-analytics-specialty', '["AWS Certified Data Analytics", "AWS Data Analytics Specialty", "DAS-C01"]'
    UNION ALL SELECT 'microsoft-azure-fundamentals-(az-900)', '["AZ-900", "Azure Fundamentals"]'
    UNION ALL SELECT 'microsoft-azure-administrator-(az-104)', '["AZ-104", "Azure Administrator Associate"]'
    UNION ALL SELECT 'microsoft-azure-solutions-architect-(az-305)', '["AZ-305", "Azure Solutions Architect Expert"]'
    UNION ALL SELECT 'microsoft-azure-developer-(az-204)', '["AZ-204", "Azure Developer Associate"]'
    UNION ALL SELECT 'microsoft-azure-devops-engineer-(az-400)', '["AZ-400", "Azure DevOps Engineer Expert"]'
    UNION ALL SELECT 'microsoft-azure-security-engineer-(az-500)', '["AZ-500", "Azure Security Engineer Associate"]'
    UNION ALL SELECT 'microsoft-azure-data-engineer-(dp-203)', '["DP-203", "Azure Data Engineer Associate"]'
    UNION ALL SELECT 'microsoft-azure-ai-engineer-(ai-102)', '["AI-102", "Azure AI Engineer Associate"]'
    UNION ALL SELECT 'microsoft-certified-azure-ai-fundamentals-ai-900', '["AI-900", "Azure AI Fundamentals"]'
    UNION ALL SELECT 'microsoft-certified-azure-data-fundamentals-dp-900', '["DP-900", "Azure Data Fundamentals"]'
    UNION ALL SELECT 'microsoft-certified-azure-data-scientist-associate', '["DP-100", "Azure Data Scientist Associate"]'
    UNION ALL SELECT 'microsoft-certified-azure-ai-ethics-specialist', '["Azure AI Ethics Specialist"]'
    UNION ALL SELECT 'responsible-ai-certification-by-microsoft', '["Responsible AI Certification Microsoft"]'
    UNION ALL SELECT 'google-associate-cloud-engineer', '["Google Associate Cloud Engineer", "Associate Cloud Engineer"]'
    UNION ALL SELECT 'google-cloud-digital-leader', '["Google Cloud Digital Leader", "Cloud Digital Leader"]'
    UNION ALL SELECT 'google-professional-cloud-architect', '["Google Professional Cloud Architect", "Professional Cloud Architect"]'
    UNION ALL SELECT 'google-professional-cloud-devops-engineer', '["Google Professional Cloud DevOps Engineer", "Professional Cloud DevOps Engineer"]'
    UNION ALL SELECT 'google-professional-cloud-network-engineer', '["Google Professional Cloud Network Engineer", "Professional Cloud Network Engineer"]'
    UNION ALL SELECT 'google-professional-cloud-security-engineer', '["Google Professional Cloud Security Engineer", "Professional Cloud Security Engineer"]'
    UNION ALL SELECT 'google-professional-data-engineer', '["Google Professional Data Engineer", "GCP Professional Data Engineer"]'
    UNION ALL SELECT 'google-professional-machine-learning-engineer', '["Google Professional Machine Learning Engineer", "Google Cloud Machine Learning Engineer"]'
    UNION ALL SELECT 'certified-kubernetes-administrator-(cka)', '["CKA", "Certified Kubernetes Administrator"]'
    UNION ALL SELECT 'certified-kubernetes-application-developer-(ckad)', '["CKAD", "Certified Kubernetes Application Developer"]'
    UNION ALL SELECT 'certified-kubernetes-security-specialist-(cks)', '["CKS", "Certified Kubernetes Security Specialist"]'
    UNION ALL SELECT 'docker-certified-associate-(dca)', '["Docker Certified Associate", "Docker DCA"]'
    UNION ALL SELECT 'hashicorp-terraform-associate', '["Terraform Associate", "HashiCorp Certified Terraform Associate"]'
    UNION ALL SELECT 'hashicorp-vault-associate', '["Vault Associate", "HashiCorp Certified Vault Associate"]'
    UNION ALL SELECT 'databricks-data-engineer-associate', '["Databricks Certified Data Engineer", "Databricks Data Engineer Associate"]'
    UNION ALL SELECT 'linux-foundation-certified-sysadmin-(lfcs)', '["LFCS", "Linux Foundation Certified System Administrator"]'
    UNION ALL SELECT 'linux-foundation-certified-engineer-(lfce)', '["LFCE", "Linux Foundation Certified Engineer"]'
    UNION ALL SELECT 'cissp', '["CISSP", "Certified Information Systems Security Professional"]'
    UNION ALL SELECT 'cism', '["CISM", "Certified Information Security Manager"]'
    UNION ALL SELECT 'certified-information-systems-auditor-cisa', '["Certified Information Systems Auditor", "CISA ISACA"]'
    UNION ALL SELECT 'certified-cloud-security-professional-ccsp', '["CCSP", "Certified Cloud Security Professional"]'
    UNION ALL SELECT 'certified-cloud-security-specialist-ccss', '["Certified Cloud Security Specialist"]'
    UNION ALL SELECT 'certified-ethical-hacker-(ceh)', '["CEH", "Certified Ethical Hacker"]'
    UNION ALL SELECT 'certified-information-privacy-professional-cipp', '["CIPP", "Certified Information Privacy Professional"]'
    UNION ALL SELECT 'certified-information-privacy-technologist-cipt', '["CIPT", "Certified Information Privacy Technologist"]'
    UNION ALL SELECT 'comptia-security+', '["Security+", "CompTIA Security+"]'
    UNION ALL SELECT 'comptia-network+', '["Network+", "CompTIA Network+"]'
    UNION ALL SELECT 'comptia-cysa+', '["CySA+", "CompTIA CySA+"]'
    UNION ALL SELECT 'comptia-pentest+', '["PenTest+", "CompTIA PenTest+"]'
    UNION ALL SELECT 'comptia-a-plus', '["CompTIA A+", "A+ certification"]'
    UNION ALL SELECT 'cisco-ccna', '["CCNA", "Cisco Certified Network Associate"]'
    UNION ALL SELECT 'cisco-ccnp', '["CCNP", "Cisco Certified Network Professional"]'
    UNION ALL SELECT 'cisco-certified-internetwork-expert-ccie', '["CCIE", "Cisco Certified Internetwork Expert"]'
    UNION ALL SELECT 'cisco-certified-cyberops-associate', '["CyberOps Associate", "CCNA CyberOps"]'
    UNION ALL SELECT 'cisco-certified-devnet-professional', '["DevNet Professional", "Cisco Certified DevNet Professional"]'
    UNION ALL SELECT 'project-management-professional-pmp', '["PMP", "Project Management Professional"]'
    UNION ALL SELECT 'pmi-acp-(agile-certified-practitioner)', '["PMI-ACP", "Agile Certified Practitioner"]'
    UNION ALL SELECT 'certified-scrum-master-(csm)', '["Certified Scrum Master", "Certified ScrumMaster", "CSM Scrum"]'
    UNION ALL SELECT 'professional-scrum-master-(psm-i)', '["Professional Scrum Master", "PSM I"]'
    UNION ALL SELECT 'safe-agilist-(sa)', '["SAFe Agilist", "Leading SAFe"]'
    UNION ALL SELECT 'safe-devops-practitioner', '["SAFe DevOps Practitioner", "SAFe DevOps"]'
    UNION ALL SELECT 'itil-4-foundation', '["ITIL 4 Foundation", "ITIL Foundation", "ITIL certification"]'
    UNION ALL SELECT 'certified-data-management-professional-cdmp', '["CDMP", "Certified Data Management Professional"]'
    UNION ALL SELECT 'certified-data-scientist-dasca', '["DASCA", "Certified Data Scientist DASCA"]'
    UNION ALL SELECT 'ibm-ai-engineering-professional-certificate', '["IBM AI Engineering Professional Certificate"]'
    UNION ALL SELECT 'ibm-ai-ethics-professional-certificate', '["IBM AI Ethics Professional Certificate"]'
    UNION ALL SELECT 'ibm-certified-professional-data-scientist', '["IBM Certified Data Scientist", "IBM Certified Professional Data Scientist"]'
    UNION ALL SELECT 'ibm-data-science-professional-certificate', '["IBM Data Science Professional Certificate"]'
    UNION ALL SELECT 'oracle-cloud-data-science-certified-associate', '["Oracle Cloud Data Science Certified Associate"]'
    UNION ALL SELECT 'oracle-cloud-infrastructure-data-science-2022-certified-professional', '["OCI Data Science Professional", "Oracle Cloud Infrastructure Data Science Professional"]'
    UNION ALL SELECT 'oracle-cloud-infrastructure-data-science-certified-associate', '["OCI Data Science Associate", "Oracle Cloud Infrastructure Data Science Associate"]'
    UNION ALL SELECT 'oracle-cloud-infrastructure-machine-learning-certified-specialist', '["OCI Machine Learning Specialist", "Oracle Cloud Infrastructure Machine Learning"]'
    UNION ALL SELECT 'oracle-machine-learning-2022-certified-professional', '["Oracle Machine Learning Certified Professional"]'
    UNION ALL SELECT 'oracle-machine-learning-cloud-certified-specialist', '["Oracle Machine Learning Cloud Specialist"]'
    UNION ALL SELECT 'oracle-machine-learning-cloud-service-certified-implementation-specialist', '["Oracle Machine Learning Cloud Service Implementation Specialist"]'
  ) k ON k.slug = me.slug
   SET c.keywords = k.terms, c.updated_at = NOW()
 WHERE c.keywords IS NULL OR NOT (CAST(c.keywords AS JSON) = CAST(k.terms AS JSON));
SELECT CONCAT('Filas de certifications con keywords actualizadas: ', ROW_COUNT()) AS resultado;

COMMIT;


-- Verificación: certificaciones activas y su término principal
SELECT me.name, JSON_UNQUOTE(JSON_EXTRACT(c.keywords, '$[0]')) AS termino_principal, JSON_LENGTH(c.keywords) AS terminos
  FROM market_entities me
  JOIN certifications c ON c.market_entity_id = me.id AND c.enabled = 1
 WHERE me.entity_type = 'certification' AND me.is_active = 1
 ORDER BY me.name;
SELECT 'OK 12: términos de búsqueda de certificaciones listos' AS resultado;

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
