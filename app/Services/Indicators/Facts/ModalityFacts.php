<?php

namespace App\Services\Indicators\Facts;

use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Modalidad laboral. Criterio: solo la exclusión por título; cuenta ofertas.
 *
 *  modality  ofertas por (región, país, ciudad, fuente, modalidad guardada, mes) del periodo con
 *            región distinta de 'Desconocido' y modalidad distinta de NULL y 'no_precisa'. De ahí
 *            salen la clasificación remoto/híbrido/presencial (con los filtros de la página) y la
 *            tendencia mensual (sin filtros, como antes).
 *
 * Periodo: published_at entre 'Y-01-01' y 'Y-06-30' (o 'Y-07-01' y 'Y-12-31'), como whereBetween.
 */
final class ModalityFacts
{
    public const CLASSIFIED = ['remote', 'fully_remote', 'remote_local', 'hybrid', 'no_remote'];

    public const CLASS_SQL = "CASE
        WHEN %s IN ('remote','fully_remote','remote_local') THEN 'remoto'
        WHEN %s = 'hybrid' THEN 'híbrido'
        WHEN %s = 'no_remote' THEN 'presencial'
    END";

    public static function range(int $year, string $period): array
    {
        return $period === 's1' ? ["{$year}-01-01", "{$year}-06-30"] : ["{$year}-07-01", "{$year}-12-31"];
    }

    private static function base(int $year, string $period): Builder
    {
        return IndicatorScope::offers(DB::table('job_offers'))
            ->whereBetween('published_at', self::range($year, $period))
            ->where('region', '!=', 'Desconocido')
            ->whereNotNull('modality')
            ->where('modality', '!=', 'no_precisa');
    }

    public static function refresh(int $year, string $period): array
    {
        return ['modality' => IndicatorFacts::replace('modality', $year, $period,
            self::base($year, $period)
                ->selectRaw('region, country, city, source, modality, MONTH(published_at) AS month, COUNT(*) AS n')
                ->groupBy('region', 'country', 'city', 'source', 'modality', DB::raw('MONTH(published_at)')),
            ['region', 'country', 'city', 'source', 'modality', 'month', 'n'])];
    }

    /**
     * [total, filas {modalidad, vacantes}] de la clasificación con los filtros de la página.
     */
    public static function classified(int $year, string $period, array $filters): array
    {
        $ready = IndicatorFacts::ready('modality', $year, $period);
        $q = $ready ? IndicatorFacts::rows('modality', $year, $period) : self::base($year, $period);
        $q->whereIn('modality', self::CLASSIFIED);
        foreach (['region', 'country', 'city', 'source'] as $f) {
            if ($filters[$f] ?? null) {
                $q->where($f, $filters[$f]);
            }
        }
        $count = $ready ? 'SUM(n)' : 'COUNT(*)';
        $rows = $q->selectRaw(sprintf(self::CLASS_SQL, 'modality', 'modality', 'modality') . " AS modalidad, {$count} AS vacantes")
            ->groupBy('modalidad')
            ->orderByDesc('vacantes')->orderBy('modalidad')
            ->get();

        return [(int) $rows->sum('vacantes'), $rows];
    }

    /** Tendencia mensual (% remoto, híbrido y presencial), sin filtros, hasta 6 meses. */
    public static function trend(int $year, string $period)
    {
        if (IndicatorFacts::ready('modality', $year, $period)) {
            return IndicatorFacts::rows('modality', $year, $period)
                ->selectRaw("? AS year, month AS month_num,
                    ROUND(SUM(CASE WHEN modality IN ('remote','fully_remote','remote_local') THEN n ELSE 0 END) / SUM(n) * 100, 1) AS remoto,
                    ROUND(SUM(CASE WHEN modality = 'hybrid' THEN n ELSE 0 END) / SUM(n) * 100, 1) AS hibrido,
                    ROUND(SUM(CASE WHEN modality = 'no_remote' THEN n ELSE 0 END) / SUM(n) * 100, 1) AS presencial", [$year])
                ->groupBy('month')->orderBy('month')->limit(6)->get();
        }

        return self::base($year, $period)
            ->selectRaw("YEAR(published_at) AS year, MONTH(published_at) AS month_num,
                ROUND(SUM(modality IN ('remote','fully_remote','remote_local')) / COUNT(*) * 100, 1) AS remoto,
                ROUND(SUM(modality = 'hybrid') / COUNT(*) * 100, 1) AS hibrido,
                ROUND(SUM(modality = 'no_remote') / COUNT(*) * 100, 1) AS presencial")
            ->groupByRaw('YEAR(published_at), MONTH(published_at)')
            ->orderByRaw('YEAR(published_at), MONTH(published_at)')
            ->limit(6)->get();
    }
}
