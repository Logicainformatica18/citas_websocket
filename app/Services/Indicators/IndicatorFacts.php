<?php

namespace App\Services\Indicators;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas resumen de los indicadores de análisis (indicator_facts / indicator_fact_runs).
 *
 * Cada indicador guarda por (año, semestre) filas agregadas en SQL con las dimensiones que
 * ofrece su página (carrera, país, ciudad, fuente, modalidad…) y sus conteos; la página filtra y
 * suma esas filas al leer. Si un periodo no está calculado (o $forceLive), la página calcula en
 * vivo con el mismo criterio. Lo llena indicators:refresh-all (IndicatorRefresher).
 */
final class IndicatorFacts
{
    public const TABLE = 'indicator_facts';

    public const RUNS = 'indicator_fact_runs';

    /** Primer año precalculado (los rankings también empiezan en 2024). */
    public const FIRST_YEAR = 2024;

    /** Columnas de dimensión y de conteo que puede llenar un indicador. */
    public const COLUMNS = ['month', 'career_id', 'seniority', 'modality', 'region', 'country', 'city', 'source', 'company', 'n', 'n2', 'lat_sum', 'lng_sum'];

    /** true = ignorar las tablas resumen y calcular en vivo (pruebas de equivalencia). */
    public static bool $forceLive = false;

    /** @var array<string, ?object> */
    private static array $runs = [];

    /** Semestres que se precalculan: desde 2024-s1 hasta el semestre en curso. */
    public static function periods(): array
    {
        $now = now();
        $out = [];
        for ($y = self::FIRST_YEAR; $y <= $now->year; $y++) {
            $out[] = [$y, 's1'];
            if ($y < $now->year || $now->month >= 7) {
                $out[] = [$y, 's2'];
            }
        }

        return $out;
    }

    public static function available(): bool
    {
        static $ok = null;

        return $ok ??= Schema::hasTable(self::TABLE) && Schema::hasTable(self::RUNS);
    }

    /** ¿Hay datos precalculados de este indicador y periodo? */
    public static function ready(string $indicator, int $year, string $period): bool
    {
        return ! self::$forceLive && self::run($indicator, $year, $period) !== null;
    }

    public static function rows(string $indicator, int $year, string $period): Builder
    {
        return DB::table(self::TABLE)->where('indicator', $indicator)->where('year', $year)->where('period', $period);
    }

    /**
     * Reemplaza las filas de (indicador, año, periodo) por las que devuelve $select, que debe
     * seleccionar exactamente las columnas $cols (subconjunto de COLUMNS, con esos alias). Una
     * transacción: la página nunca ve el periodo a medias.
     */
    public static function replace(string $indicator, int $year, string $period, Builder $select, array $cols): int
    {
        $t = microtime(true);
        $cols = array_values($cols);
        if (array_diff($cols, self::COLUMNS) !== []) {
            throw new \InvalidArgumentException('Columnas no válidas: ' . implode(', ', array_diff($cols, self::COLUMNS)));
        }
        $now = now()->toDateTimeString();
        $insert = DB::query()->fromSub($select, 'f')
            ->select(array_map(fn ($c) => "f.{$c}", $cols))
            ->selectRaw('? AS indicator, ? AS year, ? AS period, ? AS calculated_at', [$indicator, $year, $period, $now]);

        $rows = DB::transaction(function () use ($indicator, $year, $period, $insert, $cols) {
            self::rows($indicator, $year, $period)->delete();
            DB::table(self::TABLE)->insertUsing(array_merge($cols, ['indicator', 'year', 'period', 'calculated_at']), $insert);

            return self::rows($indicator, $year, $period)->count();
        });

        self::logRun($indicator, $year, $period, $rows, $t, $now);

        return $rows;
    }

    /** Guarda filas ya calculadas en PHP (pocas) para (indicador, año, periodo). */
    public static function replaceRows(string $indicator, int $year, string $period, array $rows): int
    {
        $t = microtime(true);
        $now = now()->toDateTimeString();
        DB::transaction(function () use ($indicator, $year, $period, $rows, $now) {
            self::rows($indicator, $year, $period)->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table(self::TABLE)->insert(array_map(
                    fn ($r) => $r + ['indicator' => $indicator, 'year' => $year, 'period' => $period, 'calculated_at' => $now],
                    $chunk
                ));
            }
        });
        self::logRun($indicator, $year, $period, count($rows), $t, $now);

        return count($rows);
    }

    /** Marca un cálculo que no guarda filas en indicator_facts (p. ej. tablas de evolución). */
    public static function logRun(string $indicator, int $year, string $period, int $rows, float $start, ?string $at = null): void
    {
        DB::table(self::RUNS)->updateOrInsert(
            ['indicator' => $indicator, 'year' => $year, 'period' => $period],
            ['calculated_at' => $at ?? now()->toDateTimeString(), 'rows' => $rows,
                'ms' => (int) round((microtime(true) - $start) * 1000), 'peak_mb' => (int) round(memory_get_peak_usage(true) / 1048576)]
        );
        unset(self::$runs["{$indicator}|{$year}|{$period}"]);
    }

    /** Fecha y hora (Perú) del último cálculo de un indicador (de un periodo o del más reciente). */
    public static function updatedAt(string $indicator, ?int $year = null, ?string $period = null): ?string
    {
        if (! self::available()) {
            return null;
        }
        $q = DB::table(self::RUNS)->where('indicator', $indicator);
        if ($year !== null) {
            $q->where('year', $year)->where('period', $period);
        }
        $at = $q->max('calculated_at');

        return $at ? Carbon::parse($at, 'America/Lima')->format('d/m/Y H:i') . ' (hora de Perú)' : null;
    }

    private static function run(string $indicator, int $year, string $period): ?object
    {
        if (! self::available()) {
            return null;
        }
        $k = "{$indicator}|{$year}|{$period}";
        if (! array_key_exists($k, self::$runs) || app()->runningInConsole()) {
            self::$runs[$k] = DB::table(self::RUNS)->where('indicator', $indicator)->where('year', $year)->where('period', $period)->first();
        }

        return self::$runs[$k];
    }
}
