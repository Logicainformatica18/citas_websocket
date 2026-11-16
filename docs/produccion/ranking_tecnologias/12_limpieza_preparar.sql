-- =====================================================================
-- 12_limpieza_preparar.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Crea las dos tablas de la limpieza de technology_job (no toca technology_job):
--     technology_job_limpieza            tabla de trabajo: filas que el matcher no confirma
--                                (accion = 'borrar') o duplicados que pasan a su
--                                entidad principal (accion = 'reasignar'). La llena
--                                la aplicación con el comando de abajo.
--     technology_job_respaldo_limpieza   respaldo de cada fila antes de borrarla o reasignarla.
--
-- ORDEN COMPLETO
--   1. Este script.
--   2. En el servidor de la aplicación:
--        php artisan entities:clean-associations --type=technologies --dry-run   (informe, opcional)
--        php artisan entities:clean-associations --type=technologies             (llena technology_job_limpieza)
--   3. 13_limpieza_aplicar.sql   (respaldo + borrado por lotes)
--   4. php artisan ranking:refresh-technologies
--   Reversión: 14_limpieza_rollback.sql
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s. Tablas nuevas.
--
-- RE-EJECUCIÓN
--   Idempotente (CREATE TABLE IF NOT EXISTS).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `technology_job_limpieza` (
  `pivot_id`               BIGINT UNSIGNED NOT NULL,
  `job_offer_id`           BIGINT UNSIGNED NOT NULL,
  `market_entity_id`       BIGINT UNSIGNED NULL,
  `accion`                 ENUM('borrar','reasignar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrar',
  `nuevo_catalog_id`       BIGINT UNSIGNED NULL,
  `nuevo_market_entity_id` BIGINT UNSIGNED NULL,
  `source`                 VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `motivo`                 VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `aplicado`               TINYINT NOT NULL DEFAULT 0,
  `created_at`             TIMESTAMP NULL,
  PRIMARY KEY (`pivot_id`),
  KEY `idx_technology_job_limp_aplicado` (`aplicado`, `pivot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `technology_job_respaldo_limpieza` (
  `id`                     BIGINT UNSIGNED NOT NULL,           -- id original en technology_job
  `technology_id`                  BIGINT UNSIGNED NULL,               -- certification_job admite NULL
  `market_entity_id`       BIGINT UNSIGNED NULL,
  `job_offer_id`           BIGINT UNSIGNED NOT NULL,
  `created_at`             TIMESTAMP NULL,
  `updated_at`             TIMESTAMP NULL,
  `accion`                 ENUM('borrar','reasignar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nuevo_catalog_id`       BIGINT UNSIGNED NULL,
  `nuevo_market_entity_id` BIGINT UNSIGNED NULL,
  `motivo`                 VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `respaldado_en`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `restaurado_en`          TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_technology_job_resp_entidad` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK 12: tablas technology_job_limpieza y technology_job_respaldo_limpieza listas' AS resultado;
