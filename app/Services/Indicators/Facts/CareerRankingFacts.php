<?php

namespace App\Services\Indicators\Facts;

use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ranking de carreras (demanda por puestos técnicos: tech_position_job_offer → career_tech_position).
 * Criterio: solo la exclusión por título; cuenta ofertas únicas (no pasa por tecnologías).
 *
 *  careers_ranking  ofertas únicas por (carrera, país) del periodo; una oferta tiene un solo país,
 *                   así que filtrar varios países suma filas sin repetir ofertas. career_id NULL =
 *                   vacantes analizadas (ofertas con algún puesto vinculado a alguna carrera).
 *
 * Periodo: published_at entre 'Y-01-01' y 'Y-06-30' (o 'Y-07-01' y 'Y-12-31'), como whereBetween.
 */
final class CareerRankingFacts
{
    public static function range(int $year, string $period): array
    {
        return $period === 's1' ? ["{$year}-01-01", "{$year}-06-30"] : ["{$year}-07-01", "{$year}-12-31"];
    }

    private static function base(array $range): Builder
    {
        return IndicatorScope::offers(DB::table('tech_position_job_offer as tpo')
            ->join('job_offers as jo', 'jo.id', '=', 'tpo.job_offer_id'), 'jo')
            ->join('career_tech_position as ctp', 'ctp.tech_position_id', '=', 'tpo.tech_position_id')
            ->whereBetween('jo.published_at', $range);
    }

    public static function refresh(int $year, string $period): array
    {
        $range = self::range($year, $period);
        $byCareer = self::base($range)
            ->selectRaw('ctp.career_id AS career_id, jo.country AS country, COUNT(DISTINCT tpo.job_offer_id) AS n')
            ->groupBy('ctp.career_id', 'jo.country');
        $total = self::base($range)->selectRaw('NULL AS career_id, NULL AS country, COUNT(DISTINCT tpo.job_offer_id) AS n');

        return ['careers_ranking' => IndicatorFacts::replace('careers_ranking', $year, $period,
            $byCareer->unionAll($total), ['career_id', 'country', 'n'])];
    }

    /** Ranking (id, name, total_jobs) con los filtros de países y carreras (slugs); desc, luego id. */
    public static function ranking(int $year, string $period, array $countries, array $careerSlugs): Builder
    {
        if (IndicatorFacts::ready('careers_ranking', $year, $period)) {
            $q = IndicatorFacts::rows('careers_ranking', $year, $period)->whereNotNull('career_id')
                ->join('careers as c', 'c.id', '=', 'career_id')
                ->selectRaw('c.id, c.name, CAST(SUM(n) AS UNSIGNED) AS total_jobs');
            $countryCol = 'country';
        } else {
            $q = self::base(self::range($year, $period))
                ->join('careers as c', 'c.id', '=', 'ctp.career_id')
                ->selectRaw('c.id, c.name, COUNT(DISTINCT tpo.job_offer_id) AS total_jobs');
            $countryCol = 'jo.country';
        }
        if ($countries) {
            $q->whereIn($countryCol, $countries);
        }
        if ($careerSlugs) {
            $q->whereIn('c.slug', $careerSlugs);
        }

        return $q->groupBy('c.id', 'c.name')->orderByDesc('total_jobs')->orderBy('c.id');
    }

    /** Vacantes analizadas (ofertas con algún puesto vinculado a alguna carrera), sin filtros. */
    public static function analysed(int $year, string $period): int
    {
        if (IndicatorFacts::ready('careers_ranking', $year, $period)) {
            return (int) IndicatorFacts::rows('careers_ranking', $year, $period)->whereNull('career_id')->value('n');
        }

        return (int) self::base(self::range($year, $period))->distinct('tpo.job_offer_id')->count('tpo.job_offer_id');
    }
}
