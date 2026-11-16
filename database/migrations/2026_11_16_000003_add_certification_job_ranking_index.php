<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Índice de certification_job para el cálculo del Ranking de Certificaciones (equivalente al de language_job). Los de job_offers y scraper_runs ya los crea 2026_11_12_000002.
 *
 * El esquema real de estas tablas no coincide con las migraciones del repo
 * (se modificó a mano), así que cada índice se crea solo si:
 *   - la tabla y todas sus columnas existen, y
 *   - no hay ya un índice que empiece por esas mismas columnas (con otro nombre).
 *
 * En MySQL se crean en línea (ALGORITHM=INPLACE, LOCK=NONE): la tabla sigue
 * aceptando lecturas y escrituras mientras se construye el índice.
 *
 * down() solo elimina los índices con los nombres de esta migración.
 */
return new class extends Migration
{
    private const INDEXES = [
        // Join certification_job ⨝ job_offers y GROUP BY market_entity_id sin leer filas (31 500 filas en local)
        ['certification_job', 'idx_cj_job_entity', ['job_offer_id', 'market_entity_id']],
    ];

    public function up(): void
    {
        // Índices con ALGORITHM=INPLACE e information_schema: solo MySQL (las pruebas usan SQLite)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as [$table, $name, $columns]) {
            if (! $this->tableHasColumns($table, $columns)) {
                $this->report("Omitido {$table}.{$name}: falta la tabla o alguna columna (" . implode(', ', $columns) . ').');
                continue;
            }

            if ($existing = $this->equivalentIndex($table, $columns)) {
                $this->report("Omitido {$table}.{$name}: ya existe el índice equivalente `{$existing}`.");
                continue;
            }

            $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
            $sql = "ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$cols})";

            if (DB::getDriverName() === 'mysql') {
                $sql .= ', ALGORITHM=INPLACE, LOCK=NONE';
            }

            $start = microtime(true);
            DB::statement($sql);
            $this->report(sprintf('Creado %s.%s en %.1f s.', $table, $name, microtime(true) - $start));
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES) as [$table, $name]) {
            if ($this->indexExists($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }
        }
    }

    private function tableHasColumns(string $table, array $columns): bool
    {
        $found = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->whereIn('column_name', $columns)
            ->count();

        return $found === count($columns);
    }

    /** Nombre de un índice cuyas primeras columnas son exactamente $columns, o null. */
    private function equivalentIndex(string $table, array $columns): ?string
    {
        $rows = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            // Alias explícitos: MySQL 8 devuelve estas columnas en mayúsculas.
            ->selectRaw('index_name AS index_name, column_name AS column_name, sub_part AS sub_part')
            ->get();

        foreach ($rows->groupBy('index_name') as $index => $parts) {
            $leading = $parts->take(count($columns));

            // Un índice de prefijo (p. ej. url(255)) no sirve como equivalente.
            if ($leading->contains(fn ($p) => $p->sub_part !== null)) {
                continue;
            }

            if ($leading->pluck('column_name')->map(fn ($c) => strtolower($c))->all() === array_map('strtolower', $columns)) {
                return $index;
            }
        }

        return null;
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }

    private function report(string $message): void
    {
        Log::info("[MIGRATION add_certification_job_ranking_index] {$message}");
        echo "  {$message}\n";
    }
};
