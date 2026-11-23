<?php
// Genera docs/produccion/ofertas_exclusion/01_exclusion_titulos.sql a partir de la semilla
// database/data/job_title_exclusions/seed.php (la misma que usa la migración 2026_11_23_000001).
// Uso, desde la raíz del proyecto:  php83 docs/produccion/herramientas/generar_exclusion_titulos_sql.php
// No editar el .sql a mano: cambiar la plantilla de abajo o la semilla y volver a generar.

chdir(__DIR__ . '/../../..');
$seed = require 'database/data/job_title_exclusions/seed.php';

$q = fn (?string $v) => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
$rows = [];
foreach ($seed as $i => [$pattern, $mode, $whole, $category]) {
    $rows[] = ($i === 0 ? '  SELECT ' : '  UNION ALL SELECT ')
        . $q($pattern) . ($i === 0 ? ' AS pattern' : '') . ', '
        . $q($mode) . ($i === 0 ? ' AS match_mode' : '') . ', '
        . (int) $whole . ($i === 0 ? ' AS whole_word' : '') . ', '
        . $q($category) . ($i === 0 ? ' AS category' : '') . ', '
        . ($i + 1) . ($i === 0 ? ' AS orden' : '');
}
$seedSql = implode("\n", $rows);
$count = count($seed);

// Índices de job_offers: [nombre, columnas, motivo]
$indexes = [
    ['idx_jo_title_excl', '`title_exclusion_id`', 'filtro "Excluidas por título" y conteos por patrón'],
    ['idx_jo_pub_excl', '`published_at`, `title_exclusion_id`', 'cubre la consulta del ranking (rango de fechas + exclusión)'],
    ['idx_jo_company', '`company`', 'combo Empresa del listado (SELECT DISTINCT)'],
    ['idx_jo_city', '`city`', 'combo Ciudad'],
    ['idx_jo_modality', '`modality`', 'combo Modalidad'],
    ['idx_jo_job_type', '`job_type`', 'combo Tipo de trabajo'],
    ['idx_jo_remote_type', '`remote_type`', 'combo Tipo remoto'],
    ['idx_jo_workload', '`workload`', 'combo Carga laboral'],
];
$indexSql = '';
foreach ($indexes as $i => [$name, $cols, $why]) {
    $step = '6' . chr(97 + $i);
    $indexSql .= <<<SQL
-- {$step}. {$name} ({$cols}): {$why}
SET @idx = '{$name}', @cols = '{$cols}';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso {$step} - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;


SQL;
}
$indexCount = count($indexes);
$indexNames = "'" . implode("', '", array_column($indexes, 0)) . "'";

$sql = <<<SQL
-- =====================================================================
-- 01_exclusion_titulos.sql   (GENERADO: herramientas/generar_exclusion_titulos_sql.php)
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Exclusión de ofertas por TÍTULO (roles que no son de TI: comercial,
--   marketing, call center, salud…) de los indicadores de Lenguajes,
--   Tecnologías y Certificaciones. Equivale a la migración
--   2026_11_23_000001_create_job_title_exclusions.php.
--
--   1. job_title_exclusion_keywords   lista de patrones (excluir / excepción),
--                                     con la semilla de {$count} patrones (PATRONES_EXCLUIR
--                                     de exportar_periodos_ti.py). La semilla solo se carga
--                                     si la tabla no tiene filas de origen 'seed'.
--   2. job_offer_processes            procesos en cola del módulo (registro: quién,
--                                     cuándo, cuántas; progreso).
--   3. job_title_exclusion_candidates ofertas que excluiría un "Analizar basura".
--   4. job_offer_deletion_backups     respaldo (JSON por fila) de "Eliminar definitivamente".
--   5. job_offers.title_exclusion_id  NULL = válida; si no, el patrón que la excluyó.
--      La llena la aplicación: php artisan job-offers:apply-title-exclusions
--   6. Índices en job_offers: idx_jo_title_excl (title_exclusion_id),
--      idx_jo_pub_excl (published_at, title_exclusion_id), que cubre la consulta
--      del ranking, y uno por cada combo del listado /job-offers (company, city,
--      modality, job_type, remote_type, workload): hoy cada SELECT DISTINCT
--      recorre la tabla completa (1,7-2,9 s cada uno).
--
--   No borra ni modifica datos existentes: sin DELETE, TRUNCATE ni DROP.
--   No cambia el resultado de los rankings hasta que el operador ejecute el
--   comando de la aplicación (todas las ofertas quedan con NULL = válidas).
--
-- REQUISITOS
--   MySQL 8.0+. Ejecutar en la base del Observatorio.
--
-- TIEMPO / BLOQUEOS
--   Tablas nuevas: menos de 1 s.
--   Paso 5 (columna en job_offers): en local, con ~400 000 ofertas, 43 s.
--   job_offers tiene un índice FULLTEXT, por lo que MySQL puede reconstruir la
--   tabla (ALGORITHM=INPLACE); se permiten lecturas y escrituras durante el
--   cambio salvo un bloqueo breve al inicio y al final. Recomendado: con los
--   scrapers detenidos.
--   Paso 6 (8 índices): INPLACE, LOCK=NONE (sin bloquear escrituras); en local 3-5 s cada uno.
--
-- RE-EJECUCIÓN
--   Idempotente: cada paso revisa information_schema y se omite si ya está hecho.
--
-- REVERSIÓN
--   02_exclusion_titulos_rollback.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Lista de patrones
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_title_exclusion_keywords` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- se compara con el título en minúsculas y sin tildes
  `pattern`       VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  -- exclude = rol no TI; exception = protege títulos que coincidirían por error (gana)
  `type`          VARCHAR(10)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exclude',
  -- text = texto literal; regex = expresión regular (PCRE)
  `match_mode`    VARCHAR(10)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `whole_word`    TINYINT(1) NOT NULL DEFAULT 0,
  `category`      VARCHAR(60)  COLLATE utf8mb4_unicode_ci NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  -- seed = semilla; manual; ai = sugerencia de la IA aprobada
  `origin`        VARCHAR(10)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `notes`         TEXT COLLATE utf8mb4_unicode_ci NULL,
  -- ofertas cuyo título coincide (lo calcula job-offers:apply-title-exclusions)
  `matches_count` INT UNSIGNED NULL,
  `counted_at`    TIMESTAMP NULL,
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jtek_pattern` (`type`, `match_mode`, `whole_word`, `pattern`),
  KEY `idx_jtek_active` (`is_active`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semilla (solo si aún no se cargó; no repone patrones que se hayan borrado después)
INSERT INTO `job_title_exclusion_keywords`
  (`pattern`, `type`, `match_mode`, `whole_word`, `category`, `is_active`, `origin`, `notes`, `created_at`, `updated_at`)
SELECT s.pattern, 'exclude', s.match_mode, s.whole_word, s.category, 1, 'seed',
       'PATRONES_EXCLUIR de exportar_periodos_ti.py', NOW(), NOW()
  FROM (
{$seedSql}
  ) s
 WHERE NOT EXISTS (SELECT 1 FROM `job_title_exclusion_keywords` k WHERE k.origin = 'seed')
 ORDER BY s.orden;

SELECT CONCAT('Paso 1 - job_title_exclusion_keywords: ', COUNT(*), ' patrones (', SUM(origin = 'seed'), ' de la semilla)') AS resultado
  FROM `job_title_exclusion_keywords`;

-- ---------------------------------------------------------------------
-- 2-4. Procesos, candidatos del análisis y respaldo de eliminaciones
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_offer_processes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- apply | analyze | delete | restore | suggest | export
  `type`        VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  -- queued | running | done | failed
  `status`      VARCHAR(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `user_id`     BIGINT UNSIGNED NULL,
  `user_name`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
  `params`      JSON NULL,
  `total`       INT UNSIGNED NOT NULL DEFAULT 0,
  `processed`   INT UNSIGNED NOT NULL DEFAULT 0,
  `cursor_id`   BIGINT UNSIGNED NULL,
  `state`       JSON NULL,
  `result`      JSON NULL,
  `message`     TEXT COLLATE utf8mb4_unicode_ci NULL,
  `started_at`  TIMESTAMP NULL,
  `finished_at` TIMESTAMP NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jop_type_status` (`type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_title_exclusion_candidates` (
  `process_id`   BIGINT UNSIGNED NOT NULL,
  `job_offer_id` BIGINT UNSIGNED NOT NULL,
  `keyword_id`   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`process_id`, `job_offer_id`),
  KEY `idx_jtec_offer` (`job_offer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_offer_deletion_backups` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_id`   BIGINT UNSIGNED NOT NULL,
  `table_name`   VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_offer_id` BIGINT UNSIGNED NOT NULL,
  -- la fila completa en JSON
  `payload`      LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `restored_at`  TIMESTAMP NULL,
  `created_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jodb_process` (`process_id`, `table_name`),
  KEY `idx_jodb_offer` (`job_offer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Pasos 2-4 - job_offer_processes, job_title_exclusion_candidates y job_offer_deletion_backups: listas' AS resultado;

-- ---------------------------------------------------------------------
-- 5. job_offers.title_exclusion_id
-- ---------------------------------------------------------------------
SET @existe = (SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND column_name = 'title_exclusion_id');
SET @sql = IF(@existe = 0, 'ALTER TABLE `job_offers` ADD COLUMN `title_exclusion_id` BIGINT UNSIGNED NULL', 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 5 - job_offers.title_exclusion_id: ',
              IF(@existe = 0, CONCAT('CREADA en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- ---------------------------------------------------------------------
-- 6. Índices
-- ---------------------------------------------------------------------
{$indexSql}-- ---------------------------------------------------------------------
-- VERIFICACIÓN (resultado esperado entre corchetes)
-- ---------------------------------------------------------------------
SELECT 'patrones de la semilla [{$count}]' AS verificacion,
       (SELECT COUNT(*) FROM `job_title_exclusion_keywords` WHERE origin = 'seed') AS valor
UNION ALL
SELECT 'tablas nuevas [4]', COUNT(*) FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN ('job_title_exclusion_keywords', 'job_offer_processes', 'job_title_exclusion_candidates', 'job_offer_deletion_backups')
UNION ALL
SELECT 'columna job_offers.title_exclusion_id [1]', COUNT(*) FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND column_name = 'title_exclusion_id'
UNION ALL
SELECT 'índices nuevos en job_offers [{$indexCount}]', COUNT(DISTINCT index_name) FROM information_schema.statistics
 WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name IN ({$indexNames});

SQL;

file_put_contents('docs/produccion/ofertas_exclusion/01_exclusion_titulos.sql', $sql);
echo "Generado docs/produccion/ofertas_exclusion/01_exclusion_titulos.sql ({$count} patrones)\n";
