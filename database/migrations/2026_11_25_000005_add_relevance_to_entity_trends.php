<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación con IA, estado ÚNICO del reporte de tendencia (PLAN_RANKINGS.md, Bloque 10):
 *
 *   relevance_status   pendiente | en_proceso | relevante | ruido | dudosa (los indicadores, cuando se active el
 *                      filtro de tendencias, ignoran solo ruido)
 *   relevance_origin   regla | ia | manual (NULL mientras está pendiente)
 *   ai_result_id       decisión aplicada (ai_classification_results.id)
 *   ai_batch_id        lote de la Batch API mientras está en_proceso
 *   source_snippet     extracto del resultado de Tavily (antes se descartaba)
 *
 * Todo queda pendiente; las reglas (gpt-search y URL) las aplica ai:classify. Idempotente; INSTANT / INPLACE.
 * php artisan migrate --path=database/migrations/2026_11_25_000005_add_relevance_to_entity_trends.php --force
 */
return new class extends Migration
{
    public const INDEXES = [
        'idx_et_relevance' => ['relevance_status', 'id'],
        'idx_et_year_q_relevance' => ['year', 'quarter', 'relevance_status', 'market_entity_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('entity_trends', 'relevance_status')) {
            DB::statement("ALTER TABLE entity_trends
                ADD COLUMN relevance_status ENUM('pendiente','en_proceso','relevante','ruido','dudosa') NOT NULL DEFAULT 'pendiente',
                ADD COLUMN relevance_origin ENUM('regla','ia','manual') NULL,
                ADD COLUMN ai_result_id BIGINT UNSIGNED NULL,
                ADD COLUMN ai_batch_id BIGINT UNSIGNED NULL,
                ADD COLUMN source_snippet TEXT NULL,
                ALGORITHM=INSTANT");
        }
        foreach (self::INDEXES as $name => $cols) {
            if (! $this->exists($name)) {
                DB::statement('ALTER TABLE entity_trends ADD INDEX ' . $name . ' (' . implode(', ', $cols) . '), ALGORITHM=INPLACE, LOCK=NONE');
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->exists($name)) {
                DB::statement("ALTER TABLE entity_trends DROP INDEX {$name}, ALGORITHM=INPLACE, LOCK=NONE");
            }
        }
        if (Schema::hasColumn('entity_trends', 'relevance_status')) {
            DB::statement('ALTER TABLE entity_trends DROP COLUMN relevance_status, DROP COLUMN relevance_origin,
                DROP COLUMN ai_result_id, DROP COLUMN ai_batch_id, DROP COLUMN source_snippet, ALGORITHM=INSTANT');
        }
    }

    private function exists(string $name): bool
    {
        return DB::table('information_schema.statistics')->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'entity_trends')->where('index_name', $name)->exists();
    }
};
