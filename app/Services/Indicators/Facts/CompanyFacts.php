<?php

namespace App\Services\Indicators\Facts;

use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indicador de Empresas. Criterio: solo la exclusión por título. Cuenta ofertas (no pasa por
 * carreras ni tecnologías).
 *
 *  companies  ofertas por (región guardada, país, empresa) del periodo; la página agrupa por
 *             UPPER(TRIM(empresa)) y filtra región (normalizada) y país al leer.
 *
 * Periodo: published_at entre 'Y-01-01' y 'Y-06-30' (o 'Y-07-01' y 'Y-12-31'), como whereBetween.
 * Solo ofertas con empresa y región conocida.
 */
final class CompanyFacts
{
    public static function range(int $year, string $period): array
    {
        return $period === 's1' ? ["{$year}-01-01", "{$year}-06-30"] : ["{$year}-07-01", "{$year}-12-31"];
    }

    private static function base(array $range, string $alias = 'jo'): Builder
    {
        return IndicatorScope::offers(DB::table("job_offers as {$alias}"), $alias)
            ->whereNotNull("{$alias}.company")
            ->whereBetween("{$alias}.published_at", $range)
            ->whereRaw(GeoFacts::regionSql("{$alias}.region") . " <> 'Desconocido'");
    }

    public static function refresh(int $year, string $period): array
    {
        return ['companies' => IndicatorFacts::replace('companies', $year, $period,
            self::base(self::range($year, $period))
                ->selectRaw(GeoFacts::rep('jo.region') . ' AS region, ' . GeoFacts::rep('jo.country') . ' AS country, '
                    . GeoFacts::rep('jo.company') . ' AS company, COUNT(*) AS n')
                ->groupBy('jo.region', 'jo.country', 'jo.company'),
            ['region', 'country', 'company', 'n'])];
    }

    /**
     * Consulta de ranking por empresa (company_normalized, company, total_vacancies) con los
     * filtros de la página, desde el precálculo o en vivo. Orden: vacantes desc, nombre normalizado.
     */
    public static function ranking(int $year, string $period, ?string $region, ?string $country): Builder
    {
        if (IndicatorFacts::ready('companies', $year, $period)) {
            $q = IndicatorFacts::rows('companies', $year, $period);
            $c = fn ($x) => $x;
            $count = 'CAST(SUM(n) AS UNSIGNED)';   // SUM devuelve DECIMAL (texto en JSON)
        } else {
            $q = self::base(self::range($year, $period));
            $c = fn ($x) => "jo.{$x}";
            $count = 'COUNT(*)';
        }
        if ($region) {
            $q->whereRaw(GeoFacts::regionSql($c('region')) . ' = ?', [$region]);
        }
        if ($country) {
            $q->where($c('country'), $country);
        }

        return $q->selectRaw('UPPER(TRIM(' . $c('company') . ')) AS company_normalized, '
                . GeoFacts::rep($c('company')) . " AS company, {$count} AS total_vacancies")
            ->groupBy('company_normalized')
            ->orderByDesc('total_vacancies')
            ->orderBy('company_normalized');
    }

    /**
     * Grafías guardadas de una empresa (para buscar sus ofertas por el índice de company en vez de
     * UPPER(TRIM(company)) sobre toda la tabla). null si el periodo no está precalculado.
     */
    public static function spellings(int $year, string $period, string $companyNormalized): ?array
    {
        if (! IndicatorFacts::ready('companies', $year, $period)) {
            return null;
        }

        return IndicatorFacts::rows('companies', $year, $period)
            ->whereRaw('UPPER(TRIM(company)) = ?', [$companyNormalized])
            ->distinct()->pluck('company')->all();
    }
}
