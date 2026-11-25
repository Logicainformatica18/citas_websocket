<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices para los autocompletados de filtros que siguen en vivo (Modalidad: regiones, países de
 * una región, ciudades de un país): el índice cubre la consulta y no se lee la tabla completa.
 * En línea (ALGORITHM=INPLACE, LOCK=NONE: no bloquea lecturas ni escrituras). Idempotente.
 * Equivale a docs/produccion/indicadores_optimizacion/04_indices_filtros.sql.
 */
return new class extends Migration
{
    public const INDEXES = [
        'idx_jo_region_country' => ['region', 'country'],
        'idx_jo_country_city' => ['country', 'city'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $cols) {
            if (! $this->exists($name)) {
                DB::statement('ALTER TABLE job_offers ADD INDEX ' . $name . ' (' . implode(', ', $cols) . '), ALGORITHM=INPLACE, LOCK=NONE');
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->exists($name)) {
                DB::statement("ALTER TABLE job_offers DROP INDEX {$name}, ALGORITHM=INPLACE, LOCK=NONE");
            }
        }
    }

    private function exists(string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'job_offers')
            ->where('index_name', $name)
            ->exists();
    }
};
