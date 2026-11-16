-- =====================================================================
-- 07_matcher_aliases_certificaciones.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE (matcher de Certificaciones)
--   1. Crea market_entity_aliases si no existe (misma tabla que Lenguajes y
--      Tecnologías; estructura idéntica).
--   2. Carga las reglas de certificaciones (notes = '[seed certificaciones]'):
--      nombre oficial y código de examen como alias fuertes; siglas ambiguas
--      (CSM, PSM, CISA, ITIL, "Professional Data Engineer"…) solo con contexto;
--      los 12 duplicados desactivados como alias de su principal.
--      Generadas desde database/data/entity_aliases/certifications.php con
--      docs/produccion/herramientas/generar_aliases_sql.php: no editar a mano
--      el bloque generado.
--   La tabla antigua certification_aliases queda sin uso (no se borra).
--
-- REQUISITOS
--   MySQL 8.0+. 05_ranking_certifications_entidades.sql aplicado (CompTIA A+).
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s.
--
-- RE-EJECUCIÓN
--   Idempotente.
--
-- REVERSIÓN
--   08_matcher_aliases_certificaciones_rollback.sql
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

START TRANSACTION;

-- ===================== INICIO DEL BLOQUE GENERADO =====================
-- Generado por docs/produccion/herramientas/generar_aliases_sql.php certifications certification certificaciones
-- 66 entidades. Las entidades que no existan en este entorno se omiten solas (JOIN por slug).
DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'certification' AND a.notes = '[seed certificaciones]';
SELECT CONCAT('Reglas sembradas anteriores borradas: ', ROW_COUNT()) AS resultado;

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-certified-cloud-practitioner';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-solutions-architect-–-associate';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-solutions-architect-–-professional';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-developer-–-associate';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-sysops-administrator-–-associate';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-devops-engineer-–-professional';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-security-–-specialty';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-advanced-networking-–-specialty';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-machine-learning-–-specialty';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-certified-database-specialty';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'aws-certified-data-analytics-specialty';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-fundamentals-(az-900)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-administrator-(az-104)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-solutions-architect-(az-305)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-developer-(az-204)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-devops-engineer-(az-400)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-security-engineer-(az-500)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-data-engineer-(dp-203)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-azure-ai-engineer-(ai-102)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-certified-azure-ai-fundamentals-ai-900';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-certified-azure-data-fundamentals-dp-900';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'microsoft-certified-azure-data-scientist-associate';

-- google-associate-cloud-engineer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Associate Cloud Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Google ACE', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-associate-cloud-engineer';

-- google-cloud-digital-leader
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Cloud Digital Leader', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'google-cloud-digital-leader';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-architect';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-devops-engineer';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-network-engineer';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-cloud-security-engineer';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-data-engineer';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'google-professional-machine-learning-engineer';

-- certified-kubernetes-administrator-(cka)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Kubernetes Administrator', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CKA', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-kubernetes-administrator-(cka)';

-- certified-kubernetes-application-developer-(ckad)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Kubernetes Application Developer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CKAD', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-kubernetes-application-developer-(ckad)';

-- certified-kubernetes-security-specialist-(cks)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Kubernetes Security Specialist', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CKS', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-kubernetes-security-specialist-(cks)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'docker-certified-associate-(dca)';

-- hashicorp-terraform-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Terraform Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'HashiCorp Certified Terraform', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'hashicorp-terraform-associate';

-- hashicorp-vault-associate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Vault Associate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'HashiCorp Certified Vault', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'hashicorp-vault-associate';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'databricks-data-engineer-associate';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'linux-foundation-certified-sysadmin-(lfcs)';

-- linux-foundation-certified-engineer-(lfce)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Linux Foundation Certified Engineer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'LFCE', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'linux-foundation-certified-engineer-(lfce)';

-- cissp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CISSP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Certified Information Systems Security Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cissp';

-- cism
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CISM', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Certified Information Security Manager', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cism';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-information-systems-auditor-cisa';

-- certified-cloud-security-professional-ccsp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Cloud Security Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CCSP', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-cloud-security-professional-ccsp';

-- certified-cloud-security-specialist-ccss
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Cloud Security Specialist', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-cloud-security-specialist-ccss';

-- certified-ethical-hacker-(ceh)
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Ethical Hacker', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CEH', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-ethical-hacker-(ceh)';

-- certified-information-privacy-professional-cipp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Information Privacy Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CIPP', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-information-privacy-professional-cipp';

-- certified-information-privacy-technologist-cipt
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Information Privacy Technologist', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CIPT', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-information-privacy-technologist-cipt';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-security+';

-- comptia-network+
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CompTIA Network+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Network+', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-network+';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-cysa+';

-- comptia-pentest+
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CompTIA PenTest+', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PenTest+', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-pentest+';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'comptia-a-plus';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-ccna';

-- cisco-ccnp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CCNP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco Certified Network Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-ccnp';

-- cisco-certified-internetwork-expert-ccie
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'CCIE', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco Certified Internetwork Expert', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-certified-internetwork-expert-ccie';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-certified-cyberops-associate';

-- cisco-certified-devnet-professional
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'DevNet Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cisco Certified DevNet Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'cisco-certified-devnet-professional';

-- project-management-professional-pmp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'PMP', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Project Management Professional', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'project-management-professional-pmp';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'pmi-acp-(agile-certified-practitioner)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-scrum-master-(csm)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'professional-scrum-master-(psm-i)';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'safe-agilist-(sa)';

-- safe-devops-practitioner
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SAFe DevOps Practitioner', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'SAFe DevOps', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'safe-devops-practitioner';

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
 WHERE me.entity_type = 'certification' AND me.slug = 'itil-4-foundation';

-- certified-data-management-professional-cdmp
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Data Management Professional', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CDMP', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-data-management-professional-cdmp';

-- certified-data-scientist-dasca
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed certificaciones]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Certified Data Scientist DASCA', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DASCA', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'certification' AND me.slug = 'certified-data-scientist-dasca';

SELECT CONCAT('Reglas sembradas: ', COUNT(*), ' (esperadas 333)') AS resultado
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'certification' AND a.notes = '[seed certificaciones]';
-- ====================== FIN DEL BLOQUE GENERADO =======================

COMMIT;

SELECT COUNT(DISTINCT a.market_entity_id) AS certificaciones_con_reglas, COUNT(*) AS reglas
  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'certification';
SELECT 'OK 07: reglas de certificaciones cargadas' AS resultado;
