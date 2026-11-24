<?php

namespace App\Services\Indicators;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Demanda de un año para los indicadores curriculares (Alineamiento PE y CCTC), con el criterio
 * común de los indicadores (IndicatorScope): sin ofertas excluidas por título, sin asociaciones
 * excluidas por el detector y sin asociaciones a entidades inactivas.
 *
 * Una sola pasada por las ofertas del año, por tramos de job_offer_id: lee los pares
 * (oferta, entidad del catálogo, entidad de la asociación) de language_job, technology_job y
 * methodology_job y descarta en memoria las asociaciones no válidas (las exclusiones de cada tramo
 * se leen de una vez). Con un NOT EXISTS por asociación, cada página tardaba 40-110 s en frío.
 *
 * Resultado (cacheado; la versión de los indicadores en la clave lo invalida al cambiar las
 * exclusiones; lo precalcula indicators:curricular-demand, en RunAllJobs):
 *   career_year[career_id]      ofertas del año con alguna entidad de los cursos de la carrera
 *                               (Alineamiento PE: vacantes analizadas)
 *   career_period[s1|s2][id]    ídem por semestre: published_at entre 'Y-01-01' y 'Y-06-30' /
 *                               'Y-07-01' y 'Y-12-31' como whereBetween con fechas, es decir
 *                               hasta las 00:00:00 del último día (CCTC: vacantes analizadas)
 *   demand[fuente][catalog_id]  entidades del catálogo con al menos una oferta válida en el año
 *                               (CCTC: "demanda activa" del curso)
 *   course_jobs[course_id]      ofertas distintas del año por curso, sumando sus tres fuentes sin
 *                               repetir oferta (Alineamiento PE: cursos de una competencia)
 */
final class CurricularDemand
{
    /** fuente => [tabla pivote, alias, columna del catálogo, tabla del catálogo, tabla curso–catálogo] */
    public const SOURCES = [
        'language' => ['language_job', 'lj', 'language_id', 'languages', 'course_language'],
        'technology' => ['technology_job', 'tj', 'technology_id', 'technologies', 'course_technology'],
        'methodology' => ['methodology_job', 'mj', 'methodology_id', 'methodologies', 'course_methodology'],
    ];

    public const TTL = 26 * 3600;   // se recalcula a diario con jobs:run-all

    public static function cacheKey(int $year): string
    {
        return 'indicators:curricular_demand:v' . IndicatorScope::cacheVersion() . ':' . implode(',', IndicatorScope::$off) . ":{$year}";
    }

    /** Lee la caché; si no está, calcula (en la web puede tardar: la precalcula el comando). */
    public static function forYear(int $year): array
    {
        return Cache::remember(self::cacheKey($year), self::TTL, fn () => self::compute($year));
    }

    /** Recalcula y guarda (indicators:curricular-demand). */
    public static function warm(int $year): array
    {
        $data = self::compute($year);
        Cache::put(self::cacheKey($year), $data, self::TTL);

        return $data;
    }

    public static function compute(int $year, int $chunk = 5000): array
    {
        $from = "{$year}-01-01";
        $to = ($year + 1) . '-01-01';
        $s1End = "{$year}-06-30 00:00:00";
        $s2Start = "{$year}-07-01 00:00:00";
        $s2End = "{$year}-12-31 00:00:00";

        // Entidad del catálogo → carreras; id del catálogo → cursos (por fuente)
        $entityCareers = [];
        $catalogEntity = [];
        $catalogCourses = [];
        foreach (self::SOURCES as $src => [, , $col, $catalog, $courseTable]) {
            foreach (DB::table($catalog)->whereNotNull('market_entity_id')->get(['id', 'market_entity_id']) as $r) {
                $catalogEntity[$src][(int) $r->id] = (int) $r->market_entity_id;
            }
            foreach (DB::table($courseTable . ' as x')->distinct()->get(["x.{$col} as cat", 'x.course_id']) as $r) {
                $catalogCourses[$src][(int) $r->cat][(int) $r->course_id] = true;
            }
            foreach (DB::table('career_course as cc')->join($courseTable . ' as x', 'x.course_id', '=', 'cc.course_id')
                ->join($catalog . ' as e', 'e.id', '=', "x.{$col}")->whereNotNull('e.market_entity_id')
                ->distinct()->get(['e.market_entity_id as me', 'cc.career_id']) as $r) {
                $entityCareers[(int) $r->me][(int) $r->career_id] = true;
            }
        }

        $active = [];
        foreach (self::SOURCES as $src => [$pivot]) {
            $active[$src] = IndicatorScope::activeIdSet(IndicatorScope::entityType($pivot));
        }

        $careerYear = [];
        $careerPeriod = ['s1' => [], 's2' => []];
        $demand = ['language' => [], 'technology' => [], 'methodology' => []];
        $courseJobs = [];
        $maxId = (int) DB::table('job_offers')->max('id');

        for ($lo = 0; $lo <= $maxId; $lo += $chunk) {
            $published = [];   // oferta => published_at
            $hits = [];        // oferta => ['careers' => [id => true], 'courses' => [id => true]]

            foreach (self::SOURCES as $src => [$pivot, $a, $col]) {
                $excluded = IndicatorScope::excludedPairSet($pivot, $lo, $lo + $chunk);
                $rows = DB::select(
                    "SELECT DISTINCT {$a}.job_offer_id AS o, {$a}.{$col} AS cat, {$a}.market_entity_id AS me, jo.published_at AS p
                       FROM job_offers jo
                       JOIN {$pivot} {$a} ON {$a}.job_offer_id = jo.id
                      WHERE jo.id >= ? AND jo.id < ? AND jo.published_at >= ? AND jo.published_at < ?"
                        . IndicatorScope::offersSql('jo'),
                    [$lo, $lo + $chunk, $from, $to]
                );
                foreach ($rows as $r) {
                    if (($active[$src] !== null && ! isset($active[$src][(int) $r->me]))
                        || isset($excluded[$r->o . ':' . $r->me])) {
                        continue;
                    }
                    $cat = (int) $r->cat;
                    $o = (int) $r->o;
                    $published[$o] = $r->p;
                    $demand[$src][$cat] = true;
                    $hits[$o]['courses'] = ($hits[$o]['courses'] ?? []) + ($catalogCourses[$src][$cat] ?? []);
                    $me = $catalogEntity[$src][$cat] ?? null;
                    if ($me !== null && isset($entityCareers[$me])) {
                        $hits[$o]['careers'] = ($hits[$o]['careers'] ?? []) + $entityCareers[$me];
                    }
                }
                unset($rows, $excluded);
            }

            foreach ($hits as $o => $h) {
                foreach ($h['courses'] ?? [] as $course => $_) {
                    $courseJobs[$course] = ($courseJobs[$course] ?? 0) + 1;
                }
                $p = $published[$o];
                $period = $p <= $s1End ? 's1' : (($p >= $s2Start && $p <= $s2End) ? 's2' : null);
                foreach ($h['careers'] ?? [] as $career => $_) {
                    $careerYear[$career] = ($careerYear[$career] ?? 0) + 1;
                    if ($period !== null) {
                        $careerPeriod[$period][$career] = ($careerPeriod[$period][$career] ?? 0) + 1;
                    }
                }
            }
        }

        return [
            'year' => $year,
            'career_year' => $careerYear,
            'career_period' => $careerPeriod,
            'demand' => $demand,
            'course_jobs' => $courseJobs,
            'calculated_at' => now()->toDateTimeString(),
        ];
    }
}
