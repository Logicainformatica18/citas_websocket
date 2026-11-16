-- =====================================================================
-- 12_keywords_busqueda_certificaciones.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deja en certifications.keywords (JSON) términos útiles para BUSCAR en las
--   APIs de empleo: sigla o código de examen y nombre oficial ("CISSP", "AZ-400",
--   "AWS Certified DevOps Engineer"). El primero es el principal: lo envían los
--   scrapers que admiten un solo término por llamada.
--   Antes había alias genéricos ("aws", "ec2", "azure") o el nombre largo, y
--   varios scrapers ni siquiera decodificaban el JSON.
--   Buscar NO asocia: la asociación la sigue decidiendo el matcher.
--   Se identifica la fila por el slug de su entidad (market_entities). Las
--   keywords anteriores quedan en _respaldo_keywords_cert_2026_11 para 13.
--
-- REQUISITOS
--   MySQL 8.0+. 05_ranking_certifications_entidades.sql aplicado.
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s (~80 filas). Bloqueos de fila.
--
-- RE-EJECUCIÓN
--   Idempotente: solo actualiza las filas cuyo valor difiere; el respaldo guarda
--   el valor ORIGINAL (INSERT IGNORE).
--
-- REVERSIÓN
--   13_keywords_busqueda_certificaciones_rollback.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `_respaldo_keywords_cert_2026_11` (
  `certification_id` BIGINT UNSIGNED NOT NULL,
  `keywords_anterior` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `respaldado_en`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`certification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS `_kw_cert`;
CREATE TEMPORARY TABLE `_kw_cert` (
  slug VARCHAR(191) PRIMARY KEY,
  terms TEXT NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `_kw_cert` (slug, terms) VALUES
  ('aws-certified-cloud-practitioner', '["AWS Certified Cloud Practitioner", "AWS Cloud Practitioner", "CLF-C02"]'),
  ('aws-solutions-architect-–-associate', '["AWS Certified Solutions Architect Associate", "AWS Solutions Architect Associate", "SAA-C03"]'),
  ('aws-solutions-architect-–-professional', '["AWS Certified Solutions Architect Professional", "AWS Solutions Architect Professional", "SAP-C02"]'),
  ('aws-developer-–-associate', '["AWS Certified Developer Associate", "AWS Developer Associate", "DVA-C02"]'),
  ('aws-sysops-administrator-–-associate', '["AWS Certified SysOps Administrator", "AWS SysOps Administrator", "SOA-C02"]'),
  ('aws-devops-engineer-–-professional', '["AWS Certified DevOps Engineer", "AWS DevOps Engineer Professional", "DOP-C02"]'),
  ('aws-security-–-specialty', '["AWS Certified Security Specialty", "AWS Security Specialty", "SCS-C02"]'),
  ('aws-advanced-networking-–-specialty', '["AWS Certified Advanced Networking", "AWS Advanced Networking Specialty", "ANS-C01"]'),
  ('aws-machine-learning-–-specialty', '["AWS Certified Machine Learning", "AWS Machine Learning Specialty", "MLS-C01"]'),
  ('aws-certified-database-specialty', '["AWS Certified Database Specialty", "AWS Database Specialty", "DBS-C01"]'),
  ('aws-certified-data-analytics-specialty', '["AWS Certified Data Analytics", "AWS Data Analytics Specialty", "DAS-C01"]'),
  ('microsoft-azure-fundamentals-(az-900)', '["AZ-900", "Azure Fundamentals"]'),
  ('microsoft-azure-administrator-(az-104)', '["AZ-104", "Azure Administrator Associate"]'),
  ('microsoft-azure-solutions-architect-(az-305)', '["AZ-305", "Azure Solutions Architect Expert"]'),
  ('microsoft-azure-developer-(az-204)', '["AZ-204", "Azure Developer Associate"]'),
  ('microsoft-azure-devops-engineer-(az-400)', '["AZ-400", "Azure DevOps Engineer Expert"]'),
  ('microsoft-azure-security-engineer-(az-500)', '["AZ-500", "Azure Security Engineer Associate"]'),
  ('microsoft-azure-data-engineer-(dp-203)', '["DP-203", "Azure Data Engineer Associate"]'),
  ('microsoft-azure-ai-engineer-(ai-102)', '["AI-102", "Azure AI Engineer Associate"]'),
  ('microsoft-certified-azure-ai-fundamentals-ai-900', '["AI-900", "Azure AI Fundamentals"]'),
  ('microsoft-certified-azure-data-fundamentals-dp-900', '["DP-900", "Azure Data Fundamentals"]'),
  ('microsoft-certified-azure-data-scientist-associate', '["DP-100", "Azure Data Scientist Associate"]'),
  ('microsoft-certified-azure-ai-ethics-specialist', '["Azure AI Ethics Specialist"]'),
  ('responsible-ai-certification-by-microsoft', '["Responsible AI Certification Microsoft"]'),
  ('google-associate-cloud-engineer', '["Google Associate Cloud Engineer", "Associate Cloud Engineer"]'),
  ('google-cloud-digital-leader', '["Google Cloud Digital Leader", "Cloud Digital Leader"]'),
  ('google-professional-cloud-architect', '["Google Professional Cloud Architect", "Professional Cloud Architect"]'),
  ('google-professional-cloud-devops-engineer', '["Google Professional Cloud DevOps Engineer", "Professional Cloud DevOps Engineer"]'),
  ('google-professional-cloud-network-engineer', '["Google Professional Cloud Network Engineer", "Professional Cloud Network Engineer"]'),
  ('google-professional-cloud-security-engineer', '["Google Professional Cloud Security Engineer", "Professional Cloud Security Engineer"]'),
  ('google-professional-data-engineer', '["Google Professional Data Engineer", "GCP Professional Data Engineer"]'),
  ('google-professional-machine-learning-engineer', '["Google Professional Machine Learning Engineer", "Google Cloud Machine Learning Engineer"]'),
  ('certified-kubernetes-administrator-(cka)', '["CKA", "Certified Kubernetes Administrator"]'),
  ('certified-kubernetes-application-developer-(ckad)', '["CKAD", "Certified Kubernetes Application Developer"]'),
  ('certified-kubernetes-security-specialist-(cks)', '["CKS", "Certified Kubernetes Security Specialist"]'),
  ('docker-certified-associate-(dca)', '["Docker Certified Associate", "Docker DCA"]'),
  ('hashicorp-terraform-associate', '["Terraform Associate", "HashiCorp Certified Terraform Associate"]'),
  ('hashicorp-vault-associate', '["Vault Associate", "HashiCorp Certified Vault Associate"]'),
  ('databricks-data-engineer-associate', '["Databricks Certified Data Engineer", "Databricks Data Engineer Associate"]'),
  ('linux-foundation-certified-sysadmin-(lfcs)', '["LFCS", "Linux Foundation Certified System Administrator"]'),
  ('linux-foundation-certified-engineer-(lfce)', '["LFCE", "Linux Foundation Certified Engineer"]'),
  ('cissp', '["CISSP", "Certified Information Systems Security Professional"]'),
  ('cism', '["CISM", "Certified Information Security Manager"]'),
  ('certified-information-systems-auditor-cisa', '["Certified Information Systems Auditor", "CISA ISACA"]'),
  ('certified-cloud-security-professional-ccsp', '["CCSP", "Certified Cloud Security Professional"]'),
  ('certified-cloud-security-specialist-ccss', '["Certified Cloud Security Specialist"]'),
  ('certified-ethical-hacker-(ceh)', '["CEH", "Certified Ethical Hacker"]'),
  ('certified-information-privacy-professional-cipp', '["CIPP", "Certified Information Privacy Professional"]'),
  ('certified-information-privacy-technologist-cipt', '["CIPT", "Certified Information Privacy Technologist"]'),
  ('comptia-security+', '["Security+", "CompTIA Security+"]'),
  ('comptia-network+', '["Network+", "CompTIA Network+"]'),
  ('comptia-cysa+', '["CySA+", "CompTIA CySA+"]'),
  ('comptia-pentest+', '["PenTest+", "CompTIA PenTest+"]'),
  ('comptia-a-plus', '["CompTIA A+", "A+ certification"]'),
  ('cisco-ccna', '["CCNA", "Cisco Certified Network Associate"]'),
  ('cisco-ccnp', '["CCNP", "Cisco Certified Network Professional"]'),
  ('cisco-certified-internetwork-expert-ccie', '["CCIE", "Cisco Certified Internetwork Expert"]'),
  ('cisco-certified-cyberops-associate', '["CyberOps Associate", "CCNA CyberOps"]'),
  ('cisco-certified-devnet-professional', '["DevNet Professional", "Cisco Certified DevNet Professional"]'),
  ('project-management-professional-pmp', '["PMP", "Project Management Professional"]'),
  ('pmi-acp-(agile-certified-practitioner)', '["PMI-ACP", "Agile Certified Practitioner"]'),
  ('certified-scrum-master-(csm)', '["Certified Scrum Master", "Certified ScrumMaster", "CSM Scrum"]'),
  ('professional-scrum-master-(psm-i)', '["Professional Scrum Master", "PSM I"]'),
  ('safe-agilist-(sa)', '["SAFe Agilist", "Leading SAFe"]'),
  ('safe-devops-practitioner', '["SAFe DevOps Practitioner", "SAFe DevOps"]'),
  ('itil-4-foundation', '["ITIL 4 Foundation", "ITIL Foundation", "ITIL certification"]'),
  ('certified-data-management-professional-cdmp', '["CDMP", "Certified Data Management Professional"]'),
  ('certified-data-scientist-dasca', '["DASCA", "Certified Data Scientist DASCA"]'),
  ('ibm-ai-engineering-professional-certificate', '["IBM AI Engineering Professional Certificate"]'),
  ('ibm-ai-ethics-professional-certificate', '["IBM AI Ethics Professional Certificate"]'),
  ('ibm-certified-professional-data-scientist', '["IBM Certified Data Scientist", "IBM Certified Professional Data Scientist"]'),
  ('ibm-data-science-professional-certificate', '["IBM Data Science Professional Certificate"]'),
  ('oracle-cloud-data-science-certified-associate', '["Oracle Cloud Data Science Certified Associate"]'),
  ('oracle-cloud-infrastructure-data-science-2022-certified-professional', '["OCI Data Science Professional", "Oracle Cloud Infrastructure Data Science Professional"]'),
  ('oracle-cloud-infrastructure-data-science-certified-associate', '["OCI Data Science Associate", "Oracle Cloud Infrastructure Data Science Associate"]'),
  ('oracle-cloud-infrastructure-machine-learning-certified-specialist', '["OCI Machine Learning Specialist", "Oracle Cloud Infrastructure Machine Learning"]'),
  ('oracle-machine-learning-2022-certified-professional', '["Oracle Machine Learning Certified Professional"]'),
  ('oracle-machine-learning-cloud-certified-specialist', '["Oracle Machine Learning Cloud Specialist"]'),
  ('oracle-machine-learning-cloud-service-certified-implementation-specialist', '["Oracle Machine Learning Cloud Service Implementation Specialist"]');

START TRANSACTION;

INSERT IGNORE INTO `_respaldo_keywords_cert_2026_11` (certification_id, keywords_anterior)
SELECT c.id, c.keywords
  FROM certifications c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'certification'
  JOIN `_kw_cert` k ON k.slug = me.slug
 WHERE c.keywords IS NULL OR NOT (CAST(c.keywords AS JSON) = CAST(k.terms AS JSON));

UPDATE certifications c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'certification'
  JOIN `_kw_cert` k ON k.slug = me.slug
   SET c.keywords = k.terms, c.updated_at = NOW()
 WHERE c.keywords IS NULL OR NOT (CAST(c.keywords AS JSON) = CAST(k.terms AS JSON));
SELECT CONCAT('Filas de certifications con keywords actualizadas: ', ROW_COUNT()) AS resultado;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `_kw_cert`;

-- Verificación: certificaciones activas y su término principal
SELECT me.name, JSON_UNQUOTE(JSON_EXTRACT(c.keywords, '$[0]')) AS termino_principal, JSON_LENGTH(c.keywords) AS terminos
  FROM market_entities me
  JOIN certifications c ON c.market_entity_id = me.id AND c.enabled = 1
 WHERE me.entity_type = 'certification' AND me.is_active = 1
 ORDER BY me.name;
SELECT 'OK 12: términos de búsqueda de certificaciones listos' AS resultado;
