<?php

namespace App\Services\Indicators\Facts;

use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicador de Seniority (demanda por nivel profesional). Criterio: solo la exclusión por título
 * (IndicatorScope::offers); las asociaciones oferta–tecnología se usan completas.
 *
 *  seniority          ofertas del periodo por seniority (tal como está guardado), sin filtro de
 *                     carrera (career_id NULL) y por cada carrera (career_id: la oferta tiene alguna
 *                     tecnología de los cursos de la carrera). Vacantes analizadas y gráfico de niveles.
 *  seniority_career   MENCIONES por carrera y seniority: cada par (oferta, tecnología de los cursos
 *                     de la carrera) suma 1. "Distribución de nivel profesional" por carrera.
 *  seniority_modality ofertas por modalidad (por fecha de alta, como el endpoint original).
 *
 * Periodo: published_at entre 'Y-01-01' y 'Y-06-30' (o 'Y-07-01' y 'Y-12-31') como whereBetween
 * con fechas (hasta las 00:00:00 del último día), o sin published_at y created_at en ese rango.
 */
final class SeniorityFacts
{
    public const LEVELS = ['junior', 'mid', 'senior'];

    public static function range(int $year, string $period): array
    {
        return $period === 's1'
            ? ['start' => "{$year}-01-01", 'end' => "{$year}-06-30"]
            : ['start' => "{$year}-07-01", 'end' => "{$year}-12-31"];
    }

    /* ---------------- consultas base (vivo y precálculo) ---------------- */

    /** Ofertas del periodo con seniority junior/mid/senior (sin excluidas por título). */
    public static function offers(array $range): Builder
    {
        return IndicatorScope::offers(DB::table('job_offers as jo'), 'jo')
            ->where(function ($q) use ($range) {
                $q->whereBetween('jo.published_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->whereNull('jo.published_at')
                         ->whereBetween('jo.created_at', [$range['start'], $range['end']]);
                  });
            })
            ->whereIn(DB::raw('LOWER(TRIM(jo.seniority))'), self::LEVELS);
    }

    /**
     * Condición "la oferta jo tiene alguna tecnología de los cursos de la carrera": $careers es una
     * columna (p. ej. 'c.id', en el precálculo) o una lista de ids (filtro de la página).
     */
    public static function whereCareer(Builder $q, string|array $careers): Builder
    {
        return $q->whereExists(function ($sub) use ($careers) {
            $sub->from('technology_job as tj')
                ->join('course_technology as ct', 'ct.technology_id', '=', 'tj.technology_id')
                ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
                ->whereColumn('tj.job_offer_id', 'jo.id');
            is_array($careers) ? $sub->whereIn('cc.career_id', $careers) : $sub->whereColumn('cc.career_id', $careers);
        });
    }

    /** Pares (carrera, tecnología) sin duplicados: una tecnología en varios cursos de la carrera cuenta una vez. */
    public static function careerTechnology(): Builder
    {
        return DB::table('career_course as cc')
            ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
            ->select('cc.career_id', 'ct.technology_id')
            ->distinct();
    }

    /** Menciones por (carrera, seniority guardado). */
    public static function mentions(array $range): Builder
    {
        return self::offers($range)
            ->join('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
            ->joinSub(self::careerTechnology(), 'm', 'm.technology_id', '=', 'tj.technology_id')
            ->groupBy('m.career_id', 'jo.seniority')
            ->select('m.career_id', 'jo.seniority', DB::raw('COUNT(*) AS n'));
    }

    /* ---------------- precálculo ---------------- */

    public static function refresh(int $year, string $period): array
    {
        $range = self::range($year, $period);

        // Sin filtro de carrera (career_id NULL) y por carrera. Por carrera sale de job_offer_alignment
        // (fuente tecnología: una fila por oferta y carrera si la oferta tiene alguna tecnología de los
        // cursos de la carrera; mentions = cuántas), que indicators:refresh-all reconstruye antes:
        // mismo resultado que el EXISTS / cruce de la lectura en vivo, sin recorrer carrera × oferta.
        $all = self::offers($range)->groupBy('jo.seniority')
            ->select(DB::raw('NULL AS career_id'), 'jo.seniority AS seniority', DB::raw('COUNT(*) AS n'));
        $byCareer = self::offers($range)
            ->join('job_offer_alignment as a', function ($j) {
                $j->on('a.job_offer_id', '=', 'jo.id')->where('a.source', '=', 'technology');
            })
            ->groupBy('a.career_id', 'jo.seniority')
            ->select('a.career_id AS career_id', 'jo.seniority AS seniority', DB::raw('COUNT(*) AS n'));
        $a = IndicatorFacts::replace('seniority', $year, $period, $all->unionAll($byCareer), ['career_id', 'seniority', 'n']);

        $b = IndicatorFacts::replace('seniority_career', $year, $period,
            self::offers($range)
                ->join('job_offer_alignment as a', function ($j) {
                    $j->on('a.job_offer_id', '=', 'jo.id')->where('a.source', '=', 'technology');
                })
                ->groupBy('a.career_id', 'jo.seniority')
                ->select('a.career_id AS career_id', 'jo.seniority AS seniority', DB::raw('SUM(a.mentions) AS n')),
            ['career_id', 'seniority', 'n']);

        $c = IndicatorFacts::replace('seniority_modality', $year, $period,
            IndicatorScope::offers(DB::table('job_offers as jo'), 'jo')
                ->whereBetween('jo.created_at', [$range['start'], $range['end']])
                ->groupBy('jo.modality')
                ->select('jo.modality AS modality', DB::raw('COUNT(*) AS n')),
            ['modality', 'n']);

        return ['seniority' => $a, 'seniority_career' => $b, 'seniority_modality' => $c];
    }

    /* ---------------- lectura (precalculado o en vivo) ---------------- */

    /**
     * Ofertas por seniority guardado (Collection de {seniority, total}) para el filtro de carreras
     * (ids). Varias carreras: en vivo (la unión no se puede sumar desde filas por carrera).
     */
    public static function bySeniority(int $year, string $period, array $careerIds): Collection
    {
        if (count($careerIds) <= 1 && IndicatorFacts::ready('seniority', $year, $period)) {
            $q = IndicatorFacts::rows('seniority', $year, $period);
            $careerIds ? $q->where('career_id', $careerIds[0]) : $q->whereNull('career_id');

            return $q->get(['seniority', 'n'])->map(fn ($r) => (object) ['seniority' => $r->seniority, 'total' => (int) $r->n]);
        }

        $q = self::offers(self::range($year, $period));
        if ($careerIds) {
            self::whereCareer($q, $careerIds);
        }

        return $q->groupBy('jo.seniority')->select('jo.seniority', DB::raw('COUNT(*) as total'))->get()
            ->map(fn ($r) => (object) ['seniority' => $r->seniority, 'total' => (int) $r->total]);
    }

    /** Menciones por carrera y seniority guardado: Collection agrupada por career_id de {seniority, jobs}. */
    public static function mentionsByCareer(int $year, string $period, array $careerIds): Collection
    {
        $rows = IndicatorFacts::ready('seniority_career', $year, $period)
            ? IndicatorFacts::rows('seniority_career', $year, $period)->whereIn('career_id', $careerIds)
                ->get(['career_id', 'seniority', 'n as jobs'])
            : self::mentions(self::range($year, $period))->whereIn('m.career_id', $careerIds)
                ->select('m.career_id', 'jo.seniority', DB::raw('COUNT(*) AS jobs'))->get();

        return $rows->map(fn ($r) => (object) ['career_id' => (int) $r->career_id, 'seniority' => $r->seniority, 'jobs' => (int) $r->jobs])
            ->groupBy('career_id');
    }

    /** Ofertas por modalidad (Collection de {modality, total}). */
    public static function byModality(int $year, string $period): Collection
    {
        if (IndicatorFacts::ready('seniority_modality', $year, $period)) {
            return IndicatorFacts::rows('seniority_modality', $year, $period)->get(['modality', 'n'])
                ->map(fn ($r) => (object) ['modality' => $r->modality, 'total' => (int) $r->n]);
        }
        $range = self::range($year, $period);

        return IndicatorScope::offers(DB::table('job_offers as jo'), 'jo')
            ->whereBetween('jo.created_at', [$range['start'], $range['end']])
            ->groupBy('jo.modality')->select('jo.modality', DB::raw('COUNT(*) as total'))->get()
            ->map(fn ($r) => (object) ['modality' => $r->modality, 'total' => (int) $r->total]);
    }
}
