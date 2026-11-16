-- =====================================================================
-- 10_matcher_aliases_tecnologias.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE (Fase 3 de Tecnologías: matcher)
--   1. Crea market_entity_aliases si no existe (la misma tabla del script 11
--      de Lenguajes; estructura idéntica).
--   2. Carga las reglas de tecnologías (notes = '[seed tecnologias]'):
--      alias estrictos para palabras comunes y siglas (REST, SOAP, AJAX,
--      Express, Unity, Excel, Apache, S3, SAN…) y los 41 duplicados
--      desactivados como alias de su principal.
--      Generadas desde database/data/entity_aliases/technologies.php con
--      docs/produccion/herramientas/generar_aliases_sql.php: no editar a mano
--      el bloque generado.
--   3. Reactiva Transformers (desactivado en 06 por ambiguo) ahora que tiene
--      reglas estrictas (Hugging Face / contexto de ML).
--
-- REQUISITOS
--   MySQL 8.0+. 06_ranking_technologies_entidades.sql aplicado.
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s.
--
-- RE-EJECUCIÓN
--   Idempotente.
--
-- REVERSIÓN
--   11_matcher_aliases_tecnologias_rollback.sql
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
-- Generado por docs/produccion/herramientas/generar_aliases_sql.php technologies technology tecnologias
-- 175 entidades. Las entidades que no existan en este entorno se omiten solas (JOIN por slug).
DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id
 WHERE me.entity_type = 'technology' AND a.notes = '[seed tecnologias]';
SELECT CONCAT('Reglas sembradas anteriores borradas: ', ROW_COUNT()) AS resultado;

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-s3';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-s3-glacier';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-sns';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-sqs';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-ec2';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-ec2-auto-scaling';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-cloudfront';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-vpc';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-elasticache';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-redshift';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-rds';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-sagemaker';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'elastic-load-balancing';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'iis';

-- identity-and-access-management-iam
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Identity and Access Management', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'IAM', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'identity-and-access-management-iam';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'power-bi';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'sql-server';

-- adobe-photoshop
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Adobe Photoshop', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Photoshop', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'adobe-photoshop';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'google-adwords';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'google-analytics';

-- virtualbox
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VirtualBox', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Virtual Box', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'virtualbox';

-- hdfs
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'HDFS', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Hadoop Distributed File System', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'hdfs';

-- metasploit
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Metasploit', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Metasploit Framework', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'metasploit';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-azure';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'google-cloud-platform';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'unity';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'windows-server';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'reactjs';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'rest';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'soap';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'ajax';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'express';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'excel';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'docker';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'apache';

-- teams
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Microsoft Teams', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MS Teams', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'teams';

-- monday
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'monday.com', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Monday.com', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'monday';

-- pure
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Pure Storage', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Pure Data', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'pure';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'foundation';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'maze';

-- eraser
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Eraser.io', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'eraser';

-- translate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Translate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Translate', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'translate';

-- transcribe
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Transcribe', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Transcribe', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'transcribe';

-- polly
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Polly', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Polly', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'polly';

-- comprehend
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Comprehend', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Comprehend', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'comprehend';

-- custom-labels
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Rekognition Custom Labels', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon Rekognition Custom Labels', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'custom-labels';

-- amazon-forecast
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Forecast', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Forecast', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-forecast';

-- amazon-lex
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Lex', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS Lex', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-lex';

-- amazon-mq
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon MQ', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AWS MQ', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-mq';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-aurora';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-ecs';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-efs';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-mechanical-turk';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-web-service';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-lambda';

-- aws-iam
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS IAM', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Amazon IAM', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-iam';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-key-management-service-aws-kms';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-elastic-block-store';

-- aws-step-functions
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Step Functions', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Step Functions', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-step-functions';

-- aws-elastic-beanstalk
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Elastic Beanstalk', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Elastic Beanstalk', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-elastic-beanstalk';

-- aws-appsync
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS AppSync', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AppSync', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aws-appsync';

-- cost-explorer
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'AWS Cost Explorer', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Cost Explorer', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'cost-explorer';

-- amazon-dynamodb
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon DynamoDB', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DynamoDB', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-dynamodb';

-- amazon-cloudwatch
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon CloudWatch', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'CloudWatch', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-cloudwatch';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-cognito';

-- amazon-eventbridge
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon EventBridge', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EventBridge', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-eventbridge';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-kinesis';

-- amazon-rekognition
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Amazon Rekognition', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Rekognition', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'amazon-rekognition';

-- azure-sentinel
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Sentinel', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Sentinel', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-sentinel';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-synapse-analytics';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-data-factory';

-- azure-machine-learning
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Machine Learning', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Azure ML', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-machine-learning';

-- azure-logic-apps
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Azure Logic Apps', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Logic Apps', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-logic-apps';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'azure-blob';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'sql-azure';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'cli-command-line-interface';

-- file-server-resource-manager-fsrm
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'File Server Resource Manager', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'FSRM', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'file-server-resource-manager-fsrm';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-spark';

-- apache-kafka
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache Kafka', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Kafka', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-kafka';

-- apache-flink
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache Flink', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Flink', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-flink';

-- apache-tomcat
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Apache Tomcat', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Tomcat', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'apache-tomcat';

-- hadoop-mapreduce
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Hadoop MapReduce', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'MapReduce', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'hadoop-mapreduce';

-- yarn
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'YARN', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Hadoop YARN', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'yarn';

-- elastic-stack
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Elastic Stack', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ELK Stack', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ELK', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'elastic-stack';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'kubernetes';

-- argo-cd
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Argo CD', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ArgoCD', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'argo-cd';

-- hashicorp-vault
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'HashiCorp Vault', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Vault', 1, 0, 1, NULL
    UNION ALL SELECT 'context', 'HashiCorp', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'secrets', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Terraform', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Consul', 0, 0, 0, 60
    UNION ALL SELECT 'context', 'Kubernetes', 0, 0, 0, 60
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'hashicorp-vault';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'open-policy-agent';

-- rabbit-mq
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'RabbitMQ', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Rabbit MQ', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'rabbit-mq';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'mongodb';

-- power-apps
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Power Apps', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'PowerApps', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'power-apps';

-- power-automate
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Power Automate', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Microsoft Flow', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'power-automate';

-- google-adsense
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google AdSense', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AdSense', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-adsense';

-- facebook-ads
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Facebook Ads', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Meta Ads', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'facebook-ads';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'adobe-illustrator';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'blender';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'maya';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'autodesk-3ds-max';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'unreal-engine';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'visual-studio-code';

-- intellij-idea
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'IntelliJ IDEA', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'IntelliJ', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'intellij-idea';

-- xcode
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Xcode', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'xcode';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'jupyter-notebook';

-- jupyter-lab
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'JupyterLab', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Jupyter Lab', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'jupyter-lab';

-- google-colab
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Colab', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Colab', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-colab';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'tailwind-css';

-- entity-framework
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Entity Framework', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'EF Core', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'entity-framework';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'nodejs';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'angularjs';

-- swiftui
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'SwiftUI', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'swiftui';

-- jetpack-compose
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Jetpack Compose', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'jetpack-compose';

-- core-data
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Core Data', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'core-data';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'scikit-learn';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'dall-e';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'google-gemini';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'gpt';

-- bert
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'BERT', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'bert';

-- glove
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'GloVe', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'glove';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'copilot';

-- perplexity
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Perplexity', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Perplexity AI', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'perplexity';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'cisco-ios';

-- cisco-dna-center
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Cisco DNA Center', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'DNA Center', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'cisco-dna-center';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'ad-fs';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'exchange-server-2019';

-- microsoft-outlook
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Microsoft Outlook', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Outlook', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-outlook';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'outlook-web-app';

-- windows-server-app-fabric
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Windows Server AppFabric', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'AppFabric', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'windows-server-app-fabric';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'samba';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'nas';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'san';

-- veeam-backup
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Veeam Backup', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Veeam', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'veeam-backup';

-- proxmox-ve
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Proxmox VE', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Proxmox', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'proxmox-ve';

-- vmware-esxi
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VMware ESXi', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ESXi', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vmware-esxi';

-- vcenter-server
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'vCenter Server', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'vCenter', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vcenter-server';

-- vmware-vsphere
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VMware vSphere', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'vSphere', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vmware-vsphere';

-- vmware-nsx
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'VMware NSX', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'NSX', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'vmware-nsx';

-- click-up
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'ClickUp', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Click Up', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'click-up';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'obsidian';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'atlas-ti';

-- uipath
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'UiPath', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'uipath';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'amplitude';

-- aspera
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'IBM Aspera', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Aspera', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'aspera';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'tableau';

-- snowflake
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Snowflake', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'snowflake';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'postman';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'puppet';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'pip';

-- conda
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'conda', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Anaconda', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'conda';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'bootstrap';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'web-api';

-- api-fetch
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Fetch API', 1, 0, 0, NULL
    UNION ALL SELECT 'alias', 'API Fetch', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'api-fetch';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'retrofit';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'soar';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'volatility';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'hydra';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'owasp-zed-attack-proxy';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'norton';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'globus';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'cockpit';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'raspberry';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'wds';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'jsps';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-report';

-- microsoft-excel-solver
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Excel Solver', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'microsoft-excel-solver';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'kali-linux';

-- google-maps-sdk-ios
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Google Maps SDK', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'google-maps-sdk-ios';

-- ethercap
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Ettercap', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'ethercap', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'ethercap';

-- posenet
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'PoseNet', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'posenet';

-- trend-vision-one
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Trend Vision One', 0, 0, 0, NULL
    UNION ALL SELECT 'alias', 'Trend Micro Vision One', 0, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'trend-vision-one';

-- standard-assets
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Standard Assets', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'standard-assets';

-- sprite-mask
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Sprite Mask', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'sprite-mask';

-- sprite-shape
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Sprite Shape', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'sprite-shape';

-- shader-graph
INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)
SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, '[seed tecnologias]', NOW(), NOW()
  FROM market_entities me
  JOIN (
    SELECT 'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE
    UNION ALL SELECT 'alias', 'Shader Graph', 1, 0, 0, NULL
  ) r
 WHERE me.entity_type = 'technology' AND me.slug = 'shader-graph';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'transformers';

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
 WHERE me.entity_type = 'technology' AND me.slug = 'material-design';

SELECT CONCAT('Reglas sembradas: ', COUNT(*), ' (esperadas 968)') AS resultado
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
