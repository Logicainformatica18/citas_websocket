<?php

namespace App\Services\Indicators\Facts;

use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorScope;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demanda por ciudad (mapa del dashboard de IA). Criterio: solo la exclusión por título; cuenta
 * ofertas. Se calcula por AÑO (periodo 'yr').
 *
 *  city_demand      ofertas con coordenadas y ubicación no remota por (país, ciudad, fuente,
 *                   modalidad, mes) y la suma de latitudes y longitudes: el mapa, con los filtros
 *                   de fuente, país, modalidad y trimestre aplicados al leer.
 *  city_demand_kpi  ofertas del año por (fuente, modalidad), sin otros filtros (KPIs).
 * Un rango de fechas exacto (start_date/end_date) se calcula en vivo.
 */
final class CityDemandFacts
{
    public const PERIOD = 'yr';

    /**
     * Coordenadas en millonésimas de grado (enteros) y división en DECIMAL: da el mismo promedio en
     * vivo y desde las sumas parciales precalculadas (en coma flotante variaba el 6.º decimal).
     */
    public const MICRO_SUM = 'SUM(ROUND(latitude * 1000000)) AS lat_sum, SUM(ROUND(longitude * 1000000)) AS lng_sum';

    public const QUARTERS = ['Q1' => [1, 2, 3], 'Q2' => [4, 5, 6], 'Q3' => [7, 8, 9], 'Q4' => [10, 11, 12]];

    /** Ofertas del año con coordenadas y ubicación no remota (sin excluidas por título). */
    private static function base(int $year): Builder
    {
        return IndicatorScope::offers(DB::table('job_offers'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($q) {
                $q->where('country', 'NOT LIKE', '%remote%')
                  ->where('country', 'NOT LIKE', '%remoto%')
                  ->where('city', 'NOT LIKE', '%remote%')
                  ->where('city', 'NOT LIKE', '%remoto%');
            })
            ->whereYear('published_at', $year);
    }

    public static function years(): array
    {
        return range(IndicatorFacts::FIRST_YEAR, (int) now()->year);
    }

    public static function refresh(int $year): array
    {
        $a = IndicatorFacts::replace('city_demand', $year, self::PERIOD,
            self::base($year)
                ->selectRaw(GeoFacts::rep('country') . ' AS country, ' . GeoFacts::rep('city') . ' AS city, '
                    . GeoFacts::rep('source') . ' AS source, ' . GeoFacts::rep('modality') . ' AS modality,
                    MONTH(published_at) AS month, COUNT(*) AS n, ' . self::MICRO_SUM)
                ->groupBy('country', 'city', 'source', 'modality', DB::raw('MONTH(published_at)')),
            ['country', 'city', 'source', 'modality', 'month', 'n', 'lat_sum', 'lng_sum']);

        $b = IndicatorFacts::replace('city_demand_kpi', $year, self::PERIOD,
            IndicatorScope::offers(DB::table('job_offers'))->whereYear('published_at', $year)
                ->selectRaw(GeoFacts::rep('source') . ' AS source, ' . GeoFacts::rep('modality') . ' AS modality, COUNT(*) AS n')
                ->groupBy('source', 'modality'),
            ['source', 'modality', 'n']);

        return ['city_demand' => $a, 'city_demand_kpi' => $b];
    }

    /**
     * Puntos del mapa: Collection de {country, city, lat, lng, total} (lat/lng promedio, 6 decimales).
     */
    public static function points(int $year, array $f): Collection
    {
        $exactDates = ! empty($f['start_date']) && ! empty($f['end_date']);
        $ready = ! $exactDates && IndicatorFacts::ready('city_demand', $year, self::PERIOD);
        $q = $ready ? IndicatorFacts::rows('city_demand', $year, self::PERIOD) : self::base($year);

        if ($exactDates) {
            try {
                $q->whereBetween('published_at', [Carbon::parse($f['start_date'])->startOfDay(), Carbon::parse($f['end_date'])->endOfDay()]);
            } catch (\Exception $e) {
                // como antes: fechas inválidas se ignoran
            }
        }
        if (! empty($f['quarter']) && isset(self::QUARTERS[$f['quarter']])) {
            $ready ? $q->whereIn('month', self::QUARTERS[$f['quarter']])
                   : $q->whereIn(DB::raw('MONTH(published_at)'), self::QUARTERS[$f['quarter']]);
        }
        foreach (['sources' => 'source', 'countries' => 'country', 'modalities' => 'modality'] as $k => $col) {
            if (! empty($f[$k])) {
                $q->whereIn($col, (array) $f[$k]);
            }
        }

        $agg = $ready
            ? 'SUM(n) AS total, ROUND(CAST(SUM(lat_sum) AS DECIMAL(20,0)) / SUM(n) / 1000000, 6) AS lat, '
              . 'ROUND(CAST(SUM(lng_sum) AS DECIMAL(20,0)) / SUM(n) / 1000000, 6) AS lng'
            : 'COUNT(*) AS total, ROUND(CAST(SUM(ROUND(latitude * 1000000)) AS DECIMAL(20,0)) / COUNT(*) / 1000000, 6) AS lat, '
              . 'ROUND(CAST(SUM(ROUND(longitude * 1000000)) AS DECIMAL(20,0)) / COUNT(*) / 1000000, 6) AS lng';

        return $q->selectRaw(GeoFacts::rep('country') . ' AS country, ' . GeoFacts::rep('city') . " AS city, {$agg}")
            ->groupBy('country', 'city')
            ->orderBy('country')->orderBy('city')
            ->get()
            ->map(fn ($r) => (object) ['country' => $r->country, 'city' => $r->city, 'lat' => (float) $r->lat, 'lng' => (float) $r->lng, 'total' => (int) $r->total]);
    }

    /** Ofertas del año por modalidad o por fuente (desc): Collection de {<col>, total}. */
    public static function kpi(int $year, string $col): Collection
    {
        $q = IndicatorFacts::ready('city_demand_kpi', $year, self::PERIOD)
            ? IndicatorFacts::rows('city_demand_kpi', $year, self::PERIOD)->selectRaw("{$col}, SUM(n) AS total")
            : IndicatorScope::offers(DB::table('job_offers'))->whereYear('published_at', $year)->selectRaw(GeoFacts::rep($col) . " AS {$col}, COUNT(*) AS total");

        return $q->groupBy($col)->orderByDesc('total')->orderBy($col)->get()
            ->map(fn ($r) => (object) [$col => $r->{$col}, 'total' => (int) $r->total]);
    }
}
