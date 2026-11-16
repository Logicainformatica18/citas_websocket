-- =====================================================================
-- 01_exclusiones_tabla.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Crea entity_association_exclusions: pares (oferta, entidad) de
--   language_job / technology_job / certification_job que el matcher NO
--   confirma en el texto de la oferta. Los rankings (EntityRankingService) los
--   ignoran al calcular; las tablas pivote NO se modifican.
--   La llena la aplicación: php artisan entities:exclude-associations
--   Una exclusión que deja de aplicar (p. ej. tras ajustar reglas) se marca
--   vigente = 0; nunca se borra.
--   Crea también entity_association_exclusion_settings (una sola fila, id = 1):
--     enabled      1 = los rankings aplican las exclusiones (por defecto);
--                  0 = apagado de emergencia: php artisan entities:exclusions --disable
--     trust_since  fecha y hora de la PRIMERA ejecución de entities:exclude-associations
--                  (la guarda el comando); las asociaciones creadas desde entonces ya las
--                  confirmó el matcher al asociarlas y no se vuelven a evaluar.
--
-- REQUISITOS
--   MySQL 8.0+.
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s. Tabla nueva; no bloquea tablas existentes.
--
-- RE-EJECUCIÓN
--   Idempotente (CREATE TABLE IF NOT EXISTS).
--
-- REVERSIÓN
--   02_exclusiones_tabla_rollback.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `entity_association_exclusions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- tabla pivote: language_job, technology_job o certification_job
  `pivot_table`      VARCHAR(40)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_offer_id`     BIGINT UNSIGNED NOT NULL,
  `market_entity_id` BIGINT UNSIGNED NOT NULL,
  -- una fila del pivote de referencia (puede haber varias por par)
  `pivot_id`         BIGINT UNSIGNED NULL,
  `source`           VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  -- "sin mención" o "regla: case / negative / weak_no_context"
  `motivo`           VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  -- 1 = se excluye del ranking; 0 = ya no aplica (se conserva como historial)
  `vigente`          TINYINT(1) NOT NULL DEFAULT 1,
  -- última corrida del comando que la evaluó
  `revisado_en`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eae_par` (`pivot_table`, `job_offer_id`, `market_entity_id`),
  -- cubre el NOT EXISTS del ranking (sin leer filas)
  KEY `idx_eae_filtro` (`pivot_table`, `job_offer_id`, `market_entity_id`, `vigente`),
  KEY `idx_eae_revisado` (`pivot_table`, `vigente`, `revisado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entity_association_exclusion_settings` (
  `id`          TINYINT UNSIGNED NOT NULL,
  `enabled`     TINYINT(1) NOT NULL DEFAULT 1,
  `trust_since` DATETIME NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila única con los valores por defecto (no modifica una fila existente)
INSERT INTO `entity_association_exclusion_settings` (id, enabled, trust_since, updated_at)
SELECT 1, 1, NULL, NOW() FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `entity_association_exclusion_settings` WHERE id = 1);

SELECT 'OK: tablas entity_association_exclusions y entity_association_exclusion_settings listas' AS resultado;
