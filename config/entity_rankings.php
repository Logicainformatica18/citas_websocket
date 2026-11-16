<?php

/*
|--------------------------------------------------------------------------
| Indicadores de ranking de entidades de mercado
|--------------------------------------------------------------------------
| Configuración de App\Services\Ranking\EntityRankingService por indicador.
|
|   entity_type          market_entities.entity_type
|   pivot_table          tabla oferta↔entidad con columnas job_offer_id y market_entity_id
|   tables               tablas resumen: ranking por semestre, su meta, y la evolución
|                        (fotos por semana/quincena/mes) con su meta
|   windows_count_column columna de *_weekly_meta con el número de entidades de la foto
|   weights_context      Prueba::getActive($context): ponderaciones laboral/tendencias
|   career_table         tabla entidad↔carrera para el filtro por carrera. Fuente única:
|                        market_entity_career (la escribe la administración de entidades).
|                        career_market_entity queda sin uso (no se borra todavía).
|   lock_name            Cache::lock que impide dos recálculos a la vez (y clave de estado)
|   log_prefix           prefijo de las líneas de log
|   label                nombre en español para mensajes
|   catalog_table        catálogo que usan los scrapers (languages, technologies…), con enabled y market_entity_id
|   offer_relation       relación de JobOffer hacia el catálogo (escribe el pivote)
|   catalog_fk           columna del pivote que apunta al catálogo (language_id, technology_id…)
|   duplicates           slug de entidad duplicada desactivada => slug de su principal (limpieza: reasignar)
*/

return [

    'languages' => [
        'service' => App\Services\LanguageRankingService::class,
        'entity_type' => 'language',
        'pivot_table' => 'language_job',
        'tables' => [
            'rankings' => 'language_rankings',
            'meta' => 'language_ranking_meta',
            'windows' => 'language_ranking_weekly',
            'windows_meta' => 'language_ranking_weekly_meta',
        ],
        'windows_count_column' => 'languages_count',
        'weights_context' => 'languages',
        'career_table' => 'market_entity_career',
        'lock_name' => 'ranking-languages-refresh',
        'log_prefix' => '[LANGUAGE_RANKING]',
        'label' => 'lenguajes',
        'catalog_table' => 'languages',
        'offer_relation' => 'languages',
        'catalog_fk' => 'language_id',
    ],

    'technologies' => [
        'service' => App\Services\TechnologyRankingService::class,
        'entity_type' => 'technology',
        'pivot_table' => 'technology_job',
        'tables' => [
            'rankings' => 'technology_rankings',
            'meta' => 'technology_ranking_meta',
            'windows' => 'technology_ranking_weekly',
            'windows_meta' => 'technology_ranking_weekly_meta',
        ],
        'windows_count_column' => 'entities_count',
        'weights_context' => 'technologies',
        // Fuente única desde 2026-11 (antes career_market_entity; sus pares de tecnologías se
        // copiaron con docs/produccion/ranking_tecnologias/08_ranking_technologies_carreras.sql)
        'career_table' => 'market_entity_career',
        'lock_name' => 'ranking-technologies-refresh',
        'log_prefix' => '[TECHNOLOGY_RANKING]',
        'label' => 'tecnologías',
        'catalog_table' => 'technologies',
        'offer_relation' => 'technologies',
        'catalog_fk' => 'technology_id',
        // Duplicados desactivados (script 06) => su principal. La limpieza (Fase 4) reasigna sus ofertas a la
        // principal; el matcher los trata como alias de la principal (database/data/entity_aliases/technologies.php).
        'duplicates' => [
        'aws-s3' => 'amazon-s3',   // AWS S3 → Amazon S3
        's3' => 'amazon-s3',   // S3 → Amazon S3
        'simple-storage-service-s3' => 'amazon-s3',   // Simple Storage Service (S3) → Amazon S3
        'amazon-simple-storage-service-s3' => 'amazon-s3',   // Amazon Simple Storage Service (S3) → Amazon S3
        'aws-s3-glacier' => 'amazon-s3-glacier',   // AWS S3 Glacier → Amazon S3 Glacier
        'amazon-simple-notification-service-sns' => 'amazon-sns',   // Amazon Simple Notification Service (SNS) → Amazon SNS
        'simple-notification-service-sns' => 'amazon-sns',   // Simple Notification Service (SNS) → Amazon SNS
        'amazon-simple-queue-service-sqs' => 'amazon-sqs',   // Amazon Simple Queue Service (SQS) → Amazon SQS
        'simple-queue-service-sqs' => 'amazon-sqs',   // Simple Queue Service (SQS) → Amazon SQS
        'ec2' => 'amazon-ec2',   // EC2 → Amazon EC2
        'elastic-computing-cloud-ec2' => 'amazon-ec2',   // Elastic Computing Cloud (EC2) → Amazon EC2
        'aws-ec2-auto-scaling' => 'amazon-ec2-auto-scaling',   // AWS EC2 Auto Scaling → Amazon EC2 Auto Scaling
        'aws-cloudfront' => 'amazon-cloudfront',   // AWS CloudFront → Amazon CloudFront
        'aws-vpc' => 'amazon-vpc',   // AWS VPC → Amazon VPC
        'vpc' => 'amazon-vpc',   // VPC → Amazon VPC
        'elasticache' => 'amazon-elasticache',   // Elasticache → Amazon ElastiCache
        'redshift' => 'amazon-redshift',   // Redshift → Amazon Redshift
        'rds' => 'amazon-rds',   // RDS → Amazon RDS
        'sagemaker' => 'amazon-sagemaker',   // SageMaker → Amazon SageMaker
        'elastic-load-balancer-elb' => 'elastic-load-balancing',   // Elastic Load Balancer (ELB) → Elastic Load Balancing
        'internet-information-server-iis' => 'iis',   // Internet Information Server (IIS) → IIS
        'internet-information-services-iis' => 'iis',   // Internet Information Services (IIS) → IIS
        'iam-identity-and-access-management' => 'identity-and-access-management-iam',   // IAM (Identity and Access Management) → Identity and Access Management (IAM)
        'microsoft-power-bi' => 'power-bi',   // Microsoft Power BI → Power BI
        'microsoft-sql-server' => 'sql-server',   // Microsoft SQL Server → SQL Server
        'photoshop' => 'adobe-photoshop',   // Photoshop → Adobe Photoshop
        'adwords' => 'google-adwords',   // Adwords → Google AdWords
        'google-analytics-4' => 'google-analytics',   // Google Analytics 4 → Google Analytics
        'virtual-box' => 'virtualbox',   // Virtual Box → VirtualBox
        'hadoop-distributed-file-system-hdfs' => 'hdfs',   // Hadoop Distributed File System (HDFS) → HDFS
        'metasploit-framework' => 'metasploit',   // Metasploit Framework → Metasploit
        'azure' => 'microsoft-azure',   // Azure → Microsoft Azure
        'gcp' => 'google-cloud-platform',   // GCP → Google Cloud Platform
        'unity-3d' => 'unity',   // Unity 3D → Unity
        'unity3d' => 'unity',   // Unity3D → Unity
        'unityeditor' => 'unity',   // UnityEditor → Unity
        'unityengine' => 'unity',   // UnityEngine → Unity
        'unity-ui' => 'unity',   // Unity UI → Unity
        'windows-server-2019' => 'windows-server',   // Windows Server 2019 → Windows Server
        'windows-server-2022' => 'windows-server',   // Windows Server 2022 → Windows Server
        'cms-reactjs' => 'reactjs',   // CMS React.js → React.js
        ],
    ],

];
