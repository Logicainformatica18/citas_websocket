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
--   Se puede desactivar el filtro sin tocar la base: RANKING_EXCLUSIONS_ENABLED=false.
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

SELECT 'OK: tabla entity_association_exclusions lista' AS resultado;
