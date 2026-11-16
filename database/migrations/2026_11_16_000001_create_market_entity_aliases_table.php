<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas del matcher de entidades (App\Services\Matching\EntityTextMatcher).
 *
 * Idempotente: en producción lo aplica el DBA con
 * docs/produccion/ranking_lenguajes/11_matcher_aliases.sql (que además carga las reglas).
 * La estructura es la misma en ambos (pattern en utf8mb4_bin: "Go" y "go" son reglas distintas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('market_entity_aliases') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
CREATE TABLE `market_entity_aliases` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('market_entity_aliases');
    }
};
