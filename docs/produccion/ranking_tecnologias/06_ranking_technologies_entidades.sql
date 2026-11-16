-- =====================================================================
-- 06_ranking_technologies_entidades.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Desactiva (market_entities.is_active = 0) las entidades de tipo
--   'technology' que no corresponden al indicador, según
--   entidades_propuesta.csv (misma carpeta):
--     - organizaciones y reportes, normas y marcos, conceptos genéricos,
--       protocolos y algoritmos, y las que pertenecen a otro indicador;
--     - duplicados: quedan activas solo las principales; las demás llevan el
--       motivo "duplicado de {principal}".
--   inactive_reason empieza con "[Tecnologías 2026-11]" para poder revertirlo con 07.
--   NO borra nada ni reasigna ofertas (la reasignación de duplicados es de la
--   limpieza de la Fase 4). Solo toca market_entities.is_active (la fuente de
--   verdad): los scrapers ya ignoran esas tecnologías porque Technology::active()
--   exige la entidad activa. No toca technologies.enabled, para que el rollback
--   no reactive filas que ya estaban deshabilitadas por otro motivo.
--
--   Se identifica por slug + entity_type (los ids pueden variar entre entornos).
--
-- REQUISITOS
--   05_ranking_technologies_activos.sql aplicado. MySQL 8.0+.
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s (~600 filas en market_entities). Bloqueo de fila breve.
--
-- RE-EJECUCIÓN
--   Idempotente: solo toca entidades que siguen activas.
--
-- DESPUÉS
--   php artisan ranking:refresh-technologies
--
-- REVERSIÓN
--   07_ranking_technologies_entidades_rollback.sql
-- =====================================================================

-- Organización o reporte, no es una tecnología (11)
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

-- Ambiguo: se reactiva en la Fase 3 con alias estrictos (1)
UPDATE market_entities
   SET is_active = 0, inactive_reason = '[Tecnologías 2026-11] Ambiguo: se reactiva en la Fase 3 con alias estrictos', inactive_at = NOW()
 WHERE entity_type = 'technology' AND is_active = 1
   AND slug IN (
  'transformers'
);
SELECT CONCAT('Ambiguo: se reactiva en la Fase 3 con alias estrictos: ', ROW_COUNT(), ' desactivadas') AS resultado;

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
