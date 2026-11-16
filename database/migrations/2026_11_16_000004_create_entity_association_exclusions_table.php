<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exclusiones de asociaciones oferta↔entidad que el matcher no confirma (ver config/ranking_exclusions.php).
 *
 * Idempotente: en producción la crea el DBA con DESPLIEGUE_RANKINGS_PRODUCCION.sql
 * (fuente: docs/produccion/ranking_comun/01_exclusiones_tabla.sql; misma definición que aquí).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entity_association_exclusions') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
CREATE TABLE `entity_association_exclusions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pivot_table`      VARCHAR(40)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_offer_id`     BIGINT UNSIGNED NOT NULL,
  `market_entity_id` BIGINT UNSIGNED NOT NULL,
  `pivot_id`         BIGINT UNSIGNED NULL,
  `source`           VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `motivo`           VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `vigente`          TINYINT(1) NOT NULL DEFAULT 1,
  `revisado_en`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eae_par` (`pivot_table`, `job_offer_id`, `market_entity_id`),
  KEY `idx_eae_filtro` (`pivot_table`, `job_offer_id`, `market_entity_id`, `vigente`),
  KEY `idx_eae_revisado` (`pivot_table`, `vigente`, `revisado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_association_exclusions');
    }
};
