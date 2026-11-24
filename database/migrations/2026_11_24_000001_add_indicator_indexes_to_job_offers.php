<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para que los indicadores de análisis filtren job_offers.title_exclusion_id
 * (IndicatorScope) sin recorrer la tabla. Idempotente: solo crea los que faltan.
 *
 * Además deja INVISIBLE idx_jo_title_excl (title_exclusion_id sola): para "title_exclusion_id
 * IS NULL" (85 % de las filas) MySQL la elegía en vez del rango por fecha y los indicadores
 * tardaban el doble. Invisible no es borrar: se mantiene y se reactiva con VISIBLE; sus otras
 * consultas (conteo por patrón, excluidas) tardan casi lo mismo sin ella (~0,5 s).
 * Equivale a docs/produccion/ofertas_exclusion/03_indices_indicadores.sql.
 */
return new class extends Migration
{
    /** nombre => columnas */
    public const INDEXES = [
        // Datos Generales: conteo y última inserción por fuente (cubre el GROUP BY source)
        'idx_jo_source_created_excl' => ['source', 'created_at', 'title_exclusion_id'],
        // Datos Generales: ofertas del mes y del semestre por fecha de alta
        'idx_jo_created_excl' => ['created_at', 'title_exclusion_id'],
    ];

    /** Índice que queda invisible para el optimizador (ver arriba). */
    public const HIDDEN = 'idx_jo_title_excl';

    public function up(): void
    {
        if (! Schema::hasColumn('job_offers', 'title_exclusion_id')) {
            return;   // falta 2026_11_23_000001_create_job_title_exclusions
        }
        foreach (self::INDEXES as $name => $cols) {
            if (! $this->exists($name)) {
                DB::statement('ALTER TABLE job_offers ADD INDEX ' . $name . ' (' . implode(', ', $cols) . '), ALGORITHM=INPLACE, LOCK=NONE');
            }
        }
        if ($this->exists(self::HIDDEN)) {
            DB::statement('ALTER TABLE job_offers ALTER INDEX ' . self::HIDDEN . ' INVISIBLE');
        }
    }

    public function down(): void
    {
        if ($this->exists(self::HIDDEN)) {
            DB::statement('ALTER TABLE job_offers ALTER INDEX ' . self::HIDDEN . ' VISIBLE');
        }
        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->exists($name)) {
                DB::statement("ALTER TABLE job_offers DROP INDEX {$name}");
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
