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
--                                     con la semilla de 116 patrones (PATRONES_EXCLUIR
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
  SELECT 'comercial' AS pattern, 'text' AS match_mode, 0 AS whole_word, 'ventas' AS category, 1 AS orden
  UNION ALL SELECT 'commercial', 'text', 0, 'ventas', 2
  UNION ALL SELECT '\\bsales?\\b', 'regex', 0, 'ventas', 3
  UNION ALL SELECT 'pre-?sales', 'regex', 0, 'ventas', 4
  UNION ALL SELECT '\\bventas?\\b', 'regex', 0, 'ventas', 5
  UNION ALL SELECT 'vendedor', 'text', 0, 'ventas', 6
  UNION ALL SELECT 'preventa', 'text', 0, 'ventas', 7
  UNION ALL SELECT 'account executive', 'text', 0, 'ventas', 8
  UNION ALL SELECT 'account manager', 'text', 0, 'ventas', 9
  UNION ALL SELECT 'ejecutivo de cuentas?', 'regex', 0, 'ventas', 10
  UNION ALL SELECT 'key account', 'text', 0, 'ventas', 11
  UNION ALL SELECT 'business development', 'text', 0, 'ventas', 12
  UNION ALL SELECT 'desarrollo de negocios?', 'regex', 0, 'ventas', 13
  UNION ALL SELECT 'marketing', 'text', 0, 'marketing', 14
  UNION ALL SELECT 'mercadeo', 'text', 0, 'marketing', 15
  UNION ALL SELECT 'community manager', 'text', 0, 'marketing', 16
  UNION ALL SELECT 'recruit', 'text', 0, 'recursos humanos', 17
  UNION ALL SELECT 'reclutador', 'text', 0, 'recursos humanos', 18
  UNION ALL SELECT 'talent acquisition', 'text', 0, 'recursos humanos', 19
  UNION ALL SELECT 'recursos humanos', 'text', 0, 'recursos humanos', 20
  UNION ALL SELECT 'human resources', 'text', 0, 'recursos humanos', 21
  UNION ALL SELECT 'contador', 'text', 0, 'contabilidad', 22
  UNION ALL SELECT 'contab', 'text', 0, 'contabilidad', 23
  UNION ALL SELECT 'accountant', 'text', 0, 'contabilidad', 24
  UNION ALL SELECT 'accounting', 'text', 0, 'contabilidad', 25
  UNION ALL SELECT 'customer service', 'text', 0, 'atención al cliente', 26
  UNION ALL SELECT 'atencion al cliente', 'text', 0, 'atención al cliente', 27
  UNION ALL SELECT 'call center', 'text', 0, 'atención al cliente', 28
  UNION ALL SELECT 'data entry', 'text', 0, 'digitación', 29
  UNION ALL SELECT 'captura de datos', 'text', 0, 'digitación', 30
  UNION ALL SELECT 'digitador', 'text', 0, 'digitación', 31
  UNION ALL SELECT 'finanzas', 'text', 1, 'finanzas', 32
  UNION ALL SELECT 'tesorer', 'text', 0, 'finanzas', 33
  UNION ALL SELECT 'auditor contable', 'text', 0, 'finanzas', 34
  UNION ALL SELECT 'abogad', 'text', 0, 'legal', 35
  UNION ALL SELECT 'lawyer', 'text', 0, 'legal', 36
  UNION ALL SELECT 'attorney', 'text', 0, 'legal', 37
  UNION ALL SELECT 'paralegal', 'text', 0, 'legal', 38
  UNION ALL SELECT 'legal counsel', 'text', 0, 'legal', 39
  UNION ALL SELECT 'asistente administrativ', 'text', 0, 'administración', 40
  UNION ALL SELECT 'administrative assistant', 'text', 0, 'administración', 41
  UNION ALL SELECT 'receptionist', 'text', 0, 'administración', 42
  UNION ALL SELECT 'recepcionista', 'text', 0, 'administración', 43
  UNION ALL SELECT 'secretari', 'text', 0, 'administración', 44
  UNION ALL SELECT 'nurse', 'text', 0, 'salud', 45
  UNION ALL SELECT 'enfermer', 'text', 0, 'salud', 46
  UNION ALL SELECT 'clinical', 'text', 0, 'salud', 47
  UNION ALL SELECT 'clinico', 'text', 0, 'salud', 48
  UNION ALL SELECT 'medic(o|a)\\b', 'regex', 0, 'salud', 49
  UNION ALL SELECT 'physician', 'text', 0, 'salud', 50
  UNION ALL SELECT 'pharmac', 'text', 0, 'salud', 51
  UNION ALL SELECT 'farmac', 'text', 0, 'salud', 52
  UNION ALL SELECT 'dentist', 'text', 0, 'salud', 53
  UNION ALL SELECT 'odontolog', 'text', 0, 'salud', 54
  UNION ALL SELECT 'therapist', 'text', 0, 'salud', 55
  UNION ALL SELECT 'terapeuta', 'text', 0, 'salud', 56
  UNION ALL SELECT 'caregiver', 'text', 0, 'salud', 57
  UNION ALL SELECT 'cuidador', 'text', 0, 'salud', 58
  UNION ALL SELECT 'teacher', 'text', 0, 'educación', 59
  UNION ALL SELECT 'docente', 'text', 0, 'educación', 60
  UNION ALL SELECT 'profesor', 'text', 0, 'educación', 61
  UNION ALL SELECT 'tutor\\b', 'regex', 0, 'educación', 62
  UNION ALL SELECT 'infant', 'text', 0, 'educación', 63
  UNION ALL SELECT 'toddler', 'text', 0, 'educación', 64
  UNION ALL SELECT 'preschool', 'text', 0, 'educación', 65
  UNION ALL SELECT 'maestr(o|a)\\b', 'regex', 0, 'educación', 66
  UNION ALL SELECT '(truck|delivery|bus|van|cdl) driver', 'regex', 0, 'transporte y logística', 67
  UNION ALL SELECT 'chofer', 'text', 0, 'transporte y logística', 68
  UNION ALL SELECT 'conductor', 'text', 1, 'transporte y logística', 69
  UNION ALL SELECT 'warehouse', 'text', 0, 'transporte y logística', 70
  UNION ALL SELECT 'almacen', 'text', 0, 'transporte y logística', 71
  UNION ALL SELECT 'bodeg', 'text', 0, 'transporte y logística', 72
  UNION ALL SELECT 'forklift', 'text', 0, 'transporte y logística', 73
  UNION ALL SELECT 'montacarg', 'text', 0, 'transporte y logística', 74
  UNION ALL SELECT 'operario', 'text', 0, 'operarios y oficios', 75
  UNION ALL SELECT 'machine operator', 'text', 0, 'operarios y oficios', 76
  UNION ALL SELECT 'technician mechanic', 'text', 0, 'operarios y oficios', 77
  UNION ALL SELECT 'soldador', 'text', 0, 'operarios y oficios', 78
  UNION ALL SELECT 'welder', 'text', 0, 'operarios y oficios', 79
  UNION ALL SELECT 'plumber', 'text', 0, 'operarios y oficios', 80
  UNION ALL SELECT 'fontaner', 'text', 0, 'operarios y oficios', 81
  UNION ALL SELECT 'carpinter', 'text', 0, 'operarios y oficios', 82
  UNION ALL SELECT 'cashier', 'text', 0, 'comercio y servicios', 83
  UNION ALL SELECT 'cajer', 'text', 0, 'comercio y servicios', 84
  UNION ALL SELECT 'retail', 'text', 0, 'comercio y servicios', 85
  UNION ALL SELECT 'store manager', 'text', 0, 'comercio y servicios', 86
  UNION ALL SELECT 'chef', 'text', 1, 'comercio y servicios', 87
  UNION ALL SELECT 'cook', 'text', 1, 'comercio y servicios', 88
  UNION ALL SELECT 'cociner', 'text', 0, 'comercio y servicios', 89
  UNION ALL SELECT 'mesero', 'text', 0, 'comercio y servicios', 90
  UNION ALL SELECT 'waiter', 'text', 0, 'comercio y servicios', 91
  UNION ALL SELECT 'barista', 'text', 0, 'comercio y servicios', 92
  UNION ALL SELECT 'housekeep', 'text', 0, 'comercio y servicios', 93
  UNION ALL SELECT 'limpieza', 'text', 0, 'comercio y servicios', 94
  UNION ALL SELECT 'cleaner', 'text', 0, 'comercio y servicios', 95
  UNION ALL SELECT 'security guard', 'text', 0, 'comercio y servicios', 96
  UNION ALL SELECT 'guardia', 'text', 0, 'comercio y servicios', 97
  UNION ALL SELECT 'vigilante', 'text', 0, 'comercio y servicios', 98
  UNION ALL SELECT 'mechanical', 'text', 0, 'otras ingenierías', 99
  UNION ALL SELECT 'mecanic', 'text', 0, 'otras ingenierías', 100
  UNION ALL SELECT 'civil', 'text', 1, 'otras ingenierías', 101
  UNION ALL SELECT 'structural', 'text', 0, 'otras ingenierías', 102
  UNION ALL SELECT 'estructural', 'text', 0, 'otras ingenierías', 103
  UNION ALL SELECT 'electrical engineer', 'text', 0, 'otras ingenierías', 104
  UNION ALL SELECT 'ingeniero electric', 'text', 0, 'otras ingenierías', 105
  UNION ALL SELECT 'electricista', 'text', 0, 'otras ingenierías', 106
  UNION ALL SELECT 'construction', 'text', 0, 'otras ingenierías', 107
  UNION ALL SELECT 'construccion', 'text', 0, 'otras ingenierías', 108
  UNION ALL SELECT 'powertrain', 'text', 0, 'otras ingenierías', 109
  UNION ALL SELECT 'automotive engineer', 'text', 0, 'otras ingenierías', 110
  UNION ALL SELECT 'chemical engineer', 'text', 0, 'otras ingenierías', 111
  UNION ALL SELECT 'ingeniero quimic', 'text', 0, 'otras ingenierías', 112
  UNION ALL SELECT 'process engineer', 'text', 0, 'otras ingenierías', 113
  UNION ALL SELECT 'ingeniero de procesos', 'text', 0, 'otras ingenierías', 114
  UNION ALL SELECT 'manufactur', 'text', 0, 'otras ingenierías', 115
  UNION ALL SELECT 'produccion', 'text', 0, 'otras ingenierías', 116
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
-- 6a. idx_jo_title_excl (`title_exclusion_id`): filtro "Excluidas por título" y conteos por patrón
SET @idx = 'idx_jo_title_excl', @cols = '`title_exclusion_id`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6a - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- 6b. idx_jo_pub_excl (`published_at`, `title_exclusion_id`): cubre la consulta del ranking (rango de fechas + exclusión)
SET @idx = 'idx_jo_pub_excl', @cols = '`published_at`, `title_exclusion_id`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6b - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- 6c. idx_jo_company (`company`): combo Empresa del listado (SELECT DISTINCT)
SET @idx = 'idx_jo_company', @cols = '`company`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6c - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- 6d. idx_jo_city (`city`): combo Ciudad
SET @idx = 'idx_jo_city', @cols = '`city`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6d - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- 6e. idx_jo_modality (`modality`): combo Modalidad
SET @idx = 'idx_jo_modality', @cols = '`modality`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6e - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- 6f. idx_jo_job_type (`job_type`): combo Tipo de trabajo
SET @idx = 'idx_jo_job_type', @cols = '`job_type`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6f - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- 6g. idx_jo_remote_type (`remote_type`): combo Tipo remoto
SET @idx = 'idx_jo_remote_type', @cols = '`remote_type`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6g - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- 6h. idx_jo_workload (`workload`): combo Carga laboral
SET @idx = 'idx_jo_workload', @cols = '`workload`';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@existe = 0, CONCAT('ALTER TABLE `job_offers` ADD INDEX `', @idx, '` (', @cols, '), ALGORITHM=INPLACE, LOCK=NONE'), 'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 6h - job_offers.', @idx, ': ',
              IF(@existe = 0, CONCAT('CREADO en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), 'YA EXISTE')) AS resultado;

-- ---------------------------------------------------------------------
-- VERIFICACIÓN (resultado esperado entre corchetes)
-- ---------------------------------------------------------------------
SELECT 'patrones de la semilla [116]' AS verificacion,
       (SELECT COUNT(*) FROM `job_title_exclusion_keywords` WHERE origin = 'seed') AS valor
UNION ALL
SELECT 'tablas nuevas [4]', COUNT(*) FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN ('job_title_exclusion_keywords', 'job_offer_processes', 'job_title_exclusion_candidates', 'job_offer_deletion_backups')
UNION ALL
SELECT 'columna job_offers.title_exclusion_id [1]', COUNT(*) FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND column_name = 'title_exclusion_id'
UNION ALL
SELECT 'índices nuevos en job_offers [8]', COUNT(DISTINCT index_name) FROM information_schema.statistics
 WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name IN ('idx_jo_title_excl', 'idx_jo_pub_excl', 'idx_jo_company', 'idx_jo_city', 'idx_jo_modality', 'idx_jo_job_type', 'idx_jo_remote_type', 'idx_jo_workload');
