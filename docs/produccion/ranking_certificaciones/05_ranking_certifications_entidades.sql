-- =====================================================================
-- 05_ranking_certifications_entidades.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE (entidades del Ranking de Certificaciones, 2026-11)
--   1. Desactiva 12 certificaciones duplicadas (mismo examen con otro nombre);
--      inactive_reason = '[Certificaciones 2026-11] duplicado de {principal}'.
--      La limpieza (scripts 09-11) reasigna sus ofertas a la principal.
--   2. Da de alta CompTIA A+ (estaba como tecnología; Cisco CyberOps Associate
--      ya existía como certificación).
--   3. Crea la fila de `certifications` (catálogo que usan los scrapers) de cada
--      certificación activa que no la tenía: sin ella ningún scraper la buscaba.
--      Las filas sin keywords reciben el nombre (los scrapers las usan para buscar).
--   4. Copia a certification_job las asociaciones de las tecnologías
--      desactivadas "CompTIA A+" y "Cyberops" (technology_job no se toca). La
--      limpieza con el matcher descarta después las que el texto no confirma.
--   Cada cambio queda en _respaldo_certificaciones_2026_11 para 06.
--
-- REQUISITOS
--   01-02 de esta carpeta y ranking_lenguajes/05 (market_entities.is_active). MySQL 8.0+.
--
-- TIEMPO / BLOQUEOS
--   Menos de 2 s. Bloqueos de fila.
--
-- RE-EJECUCIÓN
--   Idempotente.
--
-- DESPUÉS
--   php artisan ranking:refresh-certifications
--
-- REVERSIÓN
--   06_ranking_certifications_entidades_rollback.sql
-- =====================================================================

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
DROP TEMPORARY TABLE IF EXISTS `_dup_cert`;
CREATE TEMPORARY TABLE `_dup_cert` (dup VARCHAR(191) PRIMARY KEY, principal VARCHAR(191) NOT NULL)
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `_dup_cert` VALUES
  ('certified-information-systems-security-professional-cissp', 'cissp'),
  ('certified-information-security-manager-cism', 'cism'),
  ('microsoft-certified-azure-security-engineer-associate', 'microsoft-azure-security-engineer-(az-500)'),
  ('microsoft-certified-azure-devops-engineer-expert', 'microsoft-azure-devops-engineer-(az-400)'),
  ('microsoft-certified-azure-ai-engineer-associate', 'microsoft-azure-ai-engineer-(ai-102)'),
  ('aws-certified-machine-learning-specialty', 'aws-machine-learning-–-specialty'),
  ('aws-certified-advanced-networking-specialty', 'aws-advanced-networking-–-specialty'),
  ('aws-certified-devops-engineer-professional', 'aws-devops-engineer-–-professional'),
  ('microsoft-certified-azure-data-engineer-associate', 'microsoft-azure-data-engineer-(dp-203)'),
  ('microsoft-certified-azure-solutions-architect-expert-az-303-az-304', 'microsoft-azure-solutions-architect-(az-305)'),
  ('oracle-machine-learning-2021-certified-professional', 'oracle-machine-learning-2022-certified-professional'),
  ('oracle-cloud-infrastructure-data-science-2021-certified-professional', 'oracle-cloud-infrastructure-data-science-2022-certified-professional');

INSERT IGNORE INTO `_respaldo_certificaciones_2026_11` (tabla, fila_id, columna, valor_anterior)
SELECT 'market_entities', d.id, 'is_active', d.is_active
  FROM `_dup_cert` x
  JOIN market_entities d ON d.entity_type = 'certification' AND d.slug = x.dup AND d.is_active = 1
  JOIN market_entities p ON p.entity_type = 'certification' AND p.slug = x.principal AND p.is_active = 1;

UPDATE market_entities d
  JOIN `_dup_cert` x ON d.slug = x.dup
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

-- ---------------------------------------------------------------------
-- 4. Asociaciones de las tecnologías CompTIA A+ y Cyberops → certification_job
-- ---------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `_tec_a_cert`;
CREATE TEMPORARY TABLE `_tec_a_cert` (tec VARCHAR(191) PRIMARY KEY, cert VARCHAR(191) NOT NULL)
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `_tec_a_cert` VALUES ('comptia-a', 'comptia-a-plus'), ('cyberops', 'cisco-certified-cyberops-associate');

INSERT IGNORE INTO certification_job (certification_id, market_entity_id, job_offer_id, certification_name_raw, created_at, updated_at)
SELECT c.id, mc.id, tj.job_offer_id, '[migrado de technology_job 2026-11]', NOW(), NOW()
  FROM `_tec_a_cert` x
  JOIN market_entities mt ON mt.entity_type = 'technology' AND mt.slug = x.tec
  JOIN technology_job tj ON tj.market_entity_id = mt.id
  JOIN market_entities mc ON mc.entity_type = 'certification' AND mc.slug = x.cert AND mc.is_active = 1
  JOIN certifications c ON c.market_entity_id = mc.id AND c.enabled = 1
 WHERE NOT EXISTS (SELECT 1 FROM certification_job cj WHERE cj.certification_id = c.id AND cj.job_offer_id = tj.job_offer_id);
SELECT CONCAT('Asociaciones copiadas desde technology_job: ', ROW_COUNT()) AS resultado;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `_dup_cert`;
DROP TEMPORARY TABLE IF EXISTS `_tec_a_cert`;

-- Verificación
SELECT is_active, COUNT(*) AS certificaciones FROM market_entities WHERE entity_type = 'certification' GROUP BY is_active;
SELECT COUNT(*) AS activas_sin_catalogo FROM market_entities me
 WHERE me.entity_type = 'certification' AND me.is_active = 1
   AND NOT EXISTS (SELECT 1 FROM certifications c WHERE c.market_entity_id = me.id AND c.enabled = 1);
SELECT 'OK 05: entidades de certificaciones ajustadas' AS resultado;
