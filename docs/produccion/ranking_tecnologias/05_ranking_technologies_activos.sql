-- =====================================================================
-- 05_ranking_technologies_activos.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   1. Agrega a market_entities la marca de "entidad activa", única fuente
--      de verdad para rankings, evolución, export y scrapers:
--        is_active        TINYINT(1) NOT NULL DEFAULT 1
--        inactive_reason  VARCHAR(255) NULL   (por qué se desactivó)
--        inactive_at      TIMESTAMP NULL      (cuándo)
--      e índice idx_market_entities_type_active (entity_type, is_active).
--      Todas las filas existentes quedan activas (DEFAULT 1).
--   (Es el mismo script de columnas que ranking_lenguajes/05: si ya se aplicó,
--   todo sale "YA EXISTE". Las entidades de tecnologías que se desactivan van en
--   06_ranking_technologies_entidades.sql, que se aplica aparte.)
--
-- REQUISITOS
--   MySQL 8.0 o superior. Ejecutar conectado a la base del observatorio.
--
-- TIEMPO ESTIMADO
--   Menos de 2 segundos (market_entities tiene ~600 filas).
--
-- BLOQUEOS
--   ADD COLUMN al final de la tabla: MySQL 8 lo hace en modo INSTANT (solo
--   metadatos). El índice se crea en línea (LOCK=NONE). Bloqueo de
--   metadatos muy breve.
--
-- RE-EJECUCIÓN
--   Idempotente: cada paso informa "YA EXISTE" / "SIN CAMBIOS".
--
-- REVERSIÓN
--   ranking_lenguajes/07_ranking_languages_rollback_fase_a.sql (columnas compartidas con Lenguajes)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Columnas
-- ---------------------------------------------------------------------
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
