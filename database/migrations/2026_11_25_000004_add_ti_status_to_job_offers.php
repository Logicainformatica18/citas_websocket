<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación con IA, estado ÚNICO de la oferta para los indicadores (PLAN_RANKINGS.md, Bloque 10):
 *
 *   ti_status   pendiente | en_proceso | ti | no_ti | dudosa. Los indicadores ignoran solo no_ti.
 *   ti_origin   keyword (exclusión por título) | ia | manual. NULL mientras está pendiente.
 *   title_key   md5 del título normalizado (App\Services\AiClassification\TitleKey); lo llenan los comandos.
 *   ti_result_id  decisión aplicada (ai_classification_results.id).
 *   ti_batch_id   lote de la Batch API mientras está en_proceso.
 *
 * Migra los valores actuales: title_exclusion_id NOT NULL → no_ti / keyword; el resto queda pendiente
 * (title_exclusion_id se mantiene como detalle del patrón). Índices nuevos con ti_status en lugar de
 * title_exclusion_id; los tres *_excl anteriores quedan INVISIBLES (no se borran).
 *
 * Idempotente. Columnas: job_offers tiene un índice FULLTEXT, así que MySQL no admite INSTANT y reconstruye la
 * tabla (en local ~2-4 min; bloquea escrituras: detener los scrapers). Índices INPLACE/LOCK=NONE.
 * php artisan migrate --path=database/migrations/2026_11_25_000004_add_ti_status_to_job_offers.php --force
 */
return new class extends Migration
{
    public const INDEXES = [
        'idx_jo_pub_ti' => ['published_at', 'ti_status'],
        'idx_jo_created_ti' => ['created_at', 'ti_status'],
        'idx_jo_source_created_ti' => ['source', 'created_at', 'ti_status'],
        'idx_jo_ti_status_key' => ['ti_status', 'title_key'],
        'idx_jo_title_key' => ['title_key'],
    ];

    /** Índices con title_exclusion_id que los indicadores dejan de usar: invisibles, no se borran. */
    public const OLD_INDEXES = ['idx_jo_pub_excl', 'idx_jo_created_excl', 'idx_jo_source_created_excl'];

    public function up(): void
    {
        if (! Schema::hasColumn('job_offers', 'ti_status')) {
            DB::statement("ALTER TABLE job_offers
                ADD COLUMN ti_status ENUM('pendiente','en_proceso','ti','no_ti','dudosa') NOT NULL DEFAULT 'pendiente',
                ADD COLUMN ti_origin ENUM('keyword','ia','manual') NULL,
                ADD COLUMN title_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
                ADD COLUMN ti_result_id BIGINT UNSIGNED NULL,
                ADD COLUMN ti_batch_id BIGINT UNSIGNED NULL
                ");
        }

        // Valores actuales: excluidas por título → no_ti / keyword (por tramos de id, sin transacción larga)
        if (Schema::hasColumn('job_offers', 'title_exclusion_id')) {
            $max = (int) DB::table('job_offers')->max('id');
            for ($from = 0; $from <= $max; $from += 20000) {
                DB::update("UPDATE job_offers SET ti_status = 'no_ti', ti_origin = 'keyword'
                             WHERE id > ? AND id <= ? AND title_exclusion_id IS NOT NULL AND ti_origin IS NULL",
                    [$from, $from + 20000]);
            }
        }

        foreach (self::INDEXES as $name => $cols) {
            if (! $this->index($name)) {
                DB::statement('ALTER TABLE job_offers ADD INDEX ' . $name . ' (' . implode(', ', $cols) . '), ALGORITHM=INPLACE, LOCK=NONE');
            }
        }
        foreach (self::OLD_INDEXES as $name) {
            if ($this->index($name) === 'YES') {
                DB::statement("ALTER TABLE job_offers ALTER INDEX {$name} INVISIBLE");
            }
        }
    }

    public function down(): void
    {
        foreach (self::OLD_INDEXES as $name) {
            if ($this->index($name) === 'NO') {
                DB::statement("ALTER TABLE job_offers ALTER INDEX {$name} VISIBLE");
            }
        }
        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->index($name)) {
                DB::statement("ALTER TABLE job_offers DROP INDEX {$name}, ALGORITHM=INPLACE, LOCK=NONE");
            }
        }
        if (Schema::hasColumn('job_offers', 'ti_status')) {
            DB::statement('ALTER TABLE job_offers DROP COLUMN ti_status, DROP COLUMN ti_origin, DROP COLUMN title_key,
                DROP COLUMN ti_result_id, DROP COLUMN ti_batch_id');
        }
    }

    /** 'YES' / 'NO' (visible) o null si no existe. */
    private function index(string $name): ?string
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'job_offers')
            ->where('index_name', $name)
            ->value('is_visible');
    }
};
