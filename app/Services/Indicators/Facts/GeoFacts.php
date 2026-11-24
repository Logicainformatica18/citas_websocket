<?php

namespace App\Services\Indicators\Facts;

use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demanda laboral geográfica. Criterio: solo la exclusión por título; asociaciones completas.
 *
 *  geo_career   MENCIONES por carrera: cada par (oferta, tecnología de los cursos de la carrera)
 *               suma 1 (KPIs y lista de carreras).
 *  geo_city     MENCIONES por (carrera, región, país, ciudad), de job_offer_alignment.mentions
 *               (tecnologías, lenguajes y metodologías de los cursos de la carrera). Sin filtro de
 *               carrera se suman todas las carreras. Ranking de ciudades.
 *  geo_heatmap  ofertas únicas por (país, ciudad) con su latitud/longitud promedio (6 decimales);
 *               region NULL = todas las regiones, si no, la región normalizada.
 *  geo_regions  regiones normalizadas con ofertas (año 0: no depende del periodo).
 *
 * Periodo: published_at entre 'Y-01-01' y 'Y-06-30' (o 'Y-07-01' y 'Y-12-31'), como whereBetween.
 */
final class GeoFacts
{
    public static function range(int $year, string $period): array
    {
        return $period === 's1' ? ["{$year}-01-01", "{$year}-06-30"] : ["{$year}-07-01", "{$year}-12-31"];
    }

    /** Región normalizada (mismo CASE que la página) sobre la columna $col. */
    public static function regionSql(string $col = 'region'): string
    {
        return "CASE
            WHEN {$col} IS NULL OR {$col} = '' THEN 'Desconocido'
            WHEN UPPER({$col}) IN ('AFRICA') THEN 'África'
            WHEN UPPER({$col}) IN ('ASIA') THEN 'Asia'
            WHEN UPPER({$col}) IN ('EUROPE', 'EUROPA') THEN 'Europa'
            WHEN UPPER({$col}) IN ('LATAM', 'LATINOAMERICA', 'LATINOAMÉRICA', 'LATIN AMERICA') THEN 'Latinoamérica'
            WHEN UPPER({$col}) IN ('NORTH_AMERICA', 'NORTEAMERICA', 'NORTEAMÉRICA', 'NORTH AMERICA') THEN 'Norteamérica'
            WHEN UPPER({$col}) IN ('OCEANIA') THEN 'Oceanía'
            WHEN UPPER({$col}) IN ('GLOBAL', 'WORLDWIDE') THEN 'Global'
            WHEN UPPER({$col}) IN ('REMOTE', 'REMOTO') THEN 'Remoto'
            ELSE 'Desconocido'
        END";
    }

    /**
     * Grafía que se muestra de un grupo. GROUP BY junta "Lima" y "LIMA" (la intercalación no
     * distingue mayúsculas ni tildes) y MySQL devolvía cualquiera de ellas; se toma la menor por
     * bytes para que la página, en vivo o precalculada, muestre siempre la misma.
     */
    public static function rep(string $col): string
    {
        return "CONVERT(MIN(BINARY {$col}) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    /* ---------------- consultas base ---------------- */

    private static function careerDemandQuery(array $range): Builder
    {
        return IndicatorScope::offers(DB::table('job_offers as jo'), 'jo')
            ->join('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
            ->joinSub(SeniorityFacts::careerTechnology(), 'm', 'm.technology_id', '=', 'tj.technology_id')
            ->whereBetween('jo.published_at', $range)
            ->groupBy('m.career_id');
    }

    private static function cityQuery(array $range): Builder
    {
        $mentions = \Illuminate\Support\Facades\Schema::hasColumn('job_offer_alignment', 'mentions') ? 'a.mentions' : '1';

        return IndicatorScope::offers(DB::table('job_offers as jo'), 'jo')
            ->join('job_offer_alignment as a', 'a.job_offer_id', '=', 'jo.id')
            ->whereBetween('jo.published_at', $range)
            ->whereNotNull('jo.city')
            ->whereRaw(self::regionSql('jo.region') . " <> 'Desconocido'")
            ->where('jo.city', '<>', 'Desconocido')
            ->selectRaw("SUM({$mentions}) AS n");
    }

    private static function heatmapQuery(array $range): Builder
    {
        return IndicatorScope::offers(DB::table('job_offers as jo'), 'jo')
            ->whereBetween('jo.published_at', $range)
            ->whereNotNull('jo.latitude')
            ->whereNotNull('jo.longitude')
            ->whereRaw(self::regionSql('jo.region') . " <> 'Desconocido'");
    }

    /* ---------------- precálculo ---------------- */

    /** Partes del precálculo de un periodo (un paso de < 60 s cada una). */
    public const PARTS = ['career', 'city', 'heatmap'];

    public static function refresh(int $year, string $period, ?string $part = null): array
    {
        $range = self::range($year, $period);
        $out = [];

        if ($part === null || $part === 'career') {
            $out['geo_career'] = IndicatorFacts::replace('geo_career', $year, $period,
                self::careerDemandQuery($range)->select('m.career_id AS career_id', DB::raw('COUNT(*) AS n')),
                ['career_id', 'n']);
        }

        if ($part === null || $part === 'city') {
            $out['geo_city'] = IndicatorFacts::replace('geo_city', $year, $period,
                self::cityQuery($range)->addSelect('a.career_id AS career_id')
                    ->selectRaw(self::rep('jo.region') . ' AS region, ' . self::rep('jo.country') . ' AS country, ' . self::rep('jo.city') . ' AS city')
                    ->groupBy('a.career_id', 'jo.region', 'jo.country', 'jo.city'),
                ['career_id', 'region', 'country', 'city', 'n']);
        }

        if ($part === null || $part === 'heatmap') {
            $heat = fn (Builder $q) => $q->selectRaw('COUNT(DISTINCT jo.id) AS n, ROUND(AVG(jo.latitude), 6) AS lat_sum, ROUND(AVG(jo.longitude), 6) AS lng_sum')
                ->selectRaw(self::rep('jo.country') . ' AS country, ' . self::rep('jo.city') . ' AS city');
            $all = $heat(self::heatmapQuery($range))->selectRaw('NULL AS region')->groupBy('jo.country', 'jo.city');
            $byRegion = $heat(self::heatmapQuery($range))->selectRaw(self::regionSql('jo.region') . ' AS region')
                ->groupByRaw(self::regionSql('jo.region') . ', jo.country, jo.city');
            $out['geo_heatmap'] = IndicatorFacts::replace('geo_heatmap', $year, $period, $all->unionAll($byRegion),
                ['region', 'country', 'city', 'n', 'lat_sum', 'lng_sum']);
        }

        return $out;
    }

    public static function refreshRegions(): array
    {
        return ['geo_regions' => IndicatorFacts::replace('geo_regions', 0, 'na',
            DB::table('job_offers')->selectRaw(self::regionSql() . ' AS region')
                ->whereRaw(self::regionSql() . " <> 'Desconocido'")->distinct(),
            ['region'])];
    }

    /* ---------------- lectura (precalculado o en vivo) ---------------- */

    /** Carreras con menciones en el periodo: Collection de {id, name, total_jobs} (desc). */
    public static function careerDemand(int $year, string $period): Collection
    {
        $q = IndicatorFacts::ready('geo_career', $year, $period)
            ? DB::query()->fromSub(IndicatorFacts::rows('geo_career', $year, $period)->select('career_id', 'n'), 'm')
                ->selectRaw('m.n AS total_jobs')
            : DB::query()->fromSub(self::careerDemandQuery(self::range($year, $period))
                ->select('m.career_id', DB::raw('COUNT(*) AS total_jobs')), 'm')->selectRaw('m.total_jobs');

        return $q->join('careers as c', 'c.id', '=', 'm.career_id')
            ->addSelect('c.id', 'c.name')
            ->orderByDesc('total_jobs')->orderBy('c.id')
            ->get()
            ->map(fn ($r) => (object) ['id' => (int) $r->id, 'name' => $r->name, 'total_jobs' => (int) $r->total_jobs]);
    }

    /** Ranking de ciudades: Collection de {city, region, country, total_jobs} (desc). */
    public static function cityRows(int $year, string $period, $careerId, ?string $region, ?string $country): Collection
    {
        if (IndicatorFacts::ready('geo_city', $year, $period)) {
            $q = IndicatorFacts::rows('geo_city', $year, $period)->selectRaw('SUM(n) AS n');
            $col = fn ($c) => $c;
        } else {
            $q = self::cityQuery(self::range($year, $period));
            $col = fn ($c) => $c === 'career_id' ? 'a.career_id' : "jo.{$c}";
        }
        if ($careerId) {
            $q->where($col('career_id'), $careerId);
        }
        if ($region) {
            $q->whereRaw(self::regionSql($col('region')) . ' = ?', [$region]);
        }
        if ($country) {
            $q->where($col('country'), $country);
        }

        return $q->selectRaw(self::rep($col('city')) . ' AS city, ' . self::rep($col('region')) . ' AS region, ' . self::rep($col('country')) . ' AS country')
            ->groupBy($col('city'), $col('region'), $col('country'))
            ->orderByDesc('n')->orderBy('city')->orderBy('region')->orderBy('country')
            ->get()
            ->map(fn ($r) => (object) ['city' => $r->city, 'region' => $r->region, 'country' => $r->country, 'total_jobs' => (int) $r->n]);
    }

    /** Mapa de calor: Collection de {country, city, lat, lng, total}. */
    public static function heatmap(int $year, string $period, ?string $region, ?string $country): Collection
    {
        if (IndicatorFacts::ready('geo_heatmap', $year, $period)) {
            $q = IndicatorFacts::rows('geo_heatmap', $year, $period);
            $region ? $q->where('region', $region) : $q->whereNull('region');
            if ($country) {
                $q->where('country', $country);
            }
            $rows = $q->get(['country', 'city', 'lat_sum as lat', 'lng_sum as lng', 'n as total']);
        } else {
            $q = self::heatmapQuery(self::range($year, $period));
            if ($country) {
                $q->where('jo.country', $country);
            }
            if ($region) {
                $q->whereRaw(self::regionSql('jo.region') . ' = ?', [$region]);
            }
            $rows = $q->selectRaw(self::rep('jo.country') . ' AS country, ' . self::rep('jo.city') . ' AS city, ROUND(AVG(jo.latitude), 6) AS lat, ROUND(AVG(jo.longitude), 6) AS lng, COUNT(DISTINCT jo.id) AS total')
                ->groupBy('jo.country', 'jo.city')->get();
        }

        return $rows->map(fn ($r) => (object) ['country' => $r->country, 'city' => $r->city, 'lat' => (float) $r->lat, 'lng' => (float) $r->lng, 'total' => (int) $r->total])
            ->sortBy(fn ($r) => [$r->country, $r->city])->values();
    }

    /** Regiones normalizadas con ofertas. */
    public static function regions(): Collection
    {
        if (IndicatorFacts::ready('geo_regions', 0, 'na')) {
            return IndicatorFacts::rows('geo_regions', 0, 'na')->orderBy('region')->pluck('region');
        }

        return DB::table('job_offers')->selectRaw(self::regionSql() . ' AS region')
            ->whereRaw(self::regionSql() . " <> 'Desconocido'")->distinct()->orderBy('region')->pluck('region');
    }
}
