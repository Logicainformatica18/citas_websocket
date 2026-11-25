<?php

namespace App\Services\Indicators;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Demanda de un año para los indicadores curriculares (Alineamiento PE y CCTC). Criterio: solo
 * la exclusión por título (IndicatorScope::offersSql); las asociaciones oferta–entidad se usan
 * completas (sin el filtro del detector ni el de entidades inactivas, que son de los rankings).
 *
 * Una pasada por las ofertas del año, por tramos de job_offer_id: lee los pares (oferta, entidad
 * del catálogo) de language_job, technology_job y methodology_job. Se divide en PARTS partes por
 * rango de ids (cada una < 60 s en la cola) que luego se suman: los conteos son por oferta, así que
 * las partes no se solapan. Calcularlo en la petición tardaba 40-110 s en frío.
 *
 * Resultado (cacheado; la versión de los indicadores en la clave lo invalida al cambiar las
 * exclusiones; lo precalcula indicators:refresh-all):
 *   career_year[career_id]      ofertas del año con alguna entidad de los cursos de la carrera
 *                               (Alineamiento PE: vacantes analizadas)
 *   career_period[s1|s2][id]    ídem por semestre: published_at entre 'Y-01-01' y 'Y-06-30' /
 *                               'Y-07-01' y 'Y-12-31' como whereBetween con fechas, es decir
 *                               hasta las 00:00:00 del último día (CCTC: vacantes analizadas)
 *   demand[fuente][catalog_id]  entidades del catálogo con al menos una oferta en el año
 *                               (CCTC: "demanda activa" del curso)
 *   course_jobs[course_id]      ofertas distintas del año por curso, sumando sus tres fuentes sin
 *                               repetir oferta (Alineamiento PE: cursos de una competencia)
 *   competency_jobs[comp_id]    por fuente, ofertas que llegan a la competencia por sus cursos;
 *                               suma de las tres fuentes (pe:recalculate)
 * Aparte, entitiesWithOffers(): entidades del catálogo con alguna oferta válida en todo el
 * histórico (CCTC: brechas del curso).
 */
final class CurricularDemand
{
    /** fuente => [tabla pivote, alias, columna del catálogo, tabla del catálogo, tabla curso–catálogo] */
    public const SOURCES = [
        'language' => ['language_job', 'lj', 'language_id', 'languages', 'course_language'],
        'technology' => ['technology_job', 'tj', 'technology_id', 'technologies', 'course_technology'],
        'methodology' => ['methodology_job', 'mj', 'methodology_id', 'methodologies', 'course_methodology'],
    ];

    public const TTL = 26 * 3600;   // se recalcula a diario (indicators:refresh-all)

    /** Partes en que se divide la pasada del año (pasos de < 60 s en la cola). */
    public const PARTS = 4;

    public static function cacheKey(int $year, ?int $part = null): string
    {
        return 'indicators:curricular_demand:v' . IndicatorScope::cacheVersion() . ':' . implode(',', IndicatorScope::$off)
            . ":{$year}" . ($part === null ? '' : ":part{$part}");
    }

    /** Lee la caché; si no está, calcula (en la web puede tardar: la precalcula indicators:refresh-all). */
    public static function forYear(int $year): array
    {
        return Cache::remember(self::cacheKey($year), self::TTL, fn () => self::compute($year));
    }

    /** Recalcula todo y guarda (en un proceso). */
    public static function warm(int $year): array
    {
        $data = self::compute($year);
        Cache::put(self::cacheKey($year), $data, self::TTL);

        return $data;
    }

    /** Paso de la cola: calcula y guarda una parte. */
    public static function warmPart(int $year, int $part): array
    {
        [$lo, $hi] = self::partRange($year, $part);
        $data = self::computeRange($year, $lo, $hi);
        Cache::put(self::cacheKey($year, $part), $data, self::TTL);

        return ['parte' => $part, 'ofertas' => [$lo, $hi]];
    }

    /** Paso final de la cola: suma las partes guardadas (calcula las que falten) y guarda el año. */
    public static function finish(int $year): array
    {
        $parts = [];
        for ($i = 0; $i < self::PARTS; $i++) {
            $parts[] = Cache::get(self::cacheKey($year, $i)) ?? self::computeRange($year, ...self::partRange($year, $i));
        }
        $data = self::merge($year, $parts);
        Cache::put(self::cacheKey($year), $data, self::TTL);

        return ['carreras' => count($data['career_year'])];
    }

    public static function compute(int $year): array
    {
        $parts = [];
        for ($i = 0; $i < self::PARTS; $i++) {
            $parts[] = self::computeRange($year, ...self::partRange($year, $i));
        }

        return self::merge($year, $parts);
    }

    /** Rango [desde, hasta) de job_offer_id de la parte $part (ids de las ofertas publicadas en el año). */
    public static function partRange(int $year, int $part): array
    {
        $r = DB::table('job_offers')->where('published_at', '>=', "{$year}-01-01")->where('published_at', '<', ($year + 1) . '-01-01')
            ->selectRaw('MIN(id) AS lo, MAX(id) AS hi')->first();
        $lo = (int) ($r->lo ?? 0);
        $hi = (int) ($r->hi ?? -1) + 1;
        $size = (int) ceil(max(1, $hi - $lo) / self::PARTS);
        $from = $lo + $part * $size;

        return [$from, $part === self::PARTS - 1 ? $hi : min($hi, $from + $size)];
    }

    /** Conteos parciales de las ofertas con id en [$lo, $hi). */
    public static function computeRange(int $year, int $lo, int $hi, int $chunk = 5000): array
    {
        $from = "{$year}-01-01";
        $to = ($year + 1) . '-01-01';
        $s1End = "{$year}-06-30 00:00:00";
        $s2Start = "{$year}-07-01 00:00:00";
        $s2End = "{$year}-12-31 00:00:00";

        // Entidad del catálogo → carreras; id del catálogo → cursos y competencias (por fuente)
        $entityCareers = [];
        $catalogEntity = [];
        $catalogCourses = [];
        $catalogCompetencies = [];
        foreach (self::SOURCES as $src => [, , $col, $catalog, $courseTable]) {
            foreach (DB::table($catalog)->whereNotNull('market_entity_id')->get(['id', 'market_entity_id']) as $r) {
                $catalogEntity[$src][(int) $r->id] = (int) $r->market_entity_id;
            }
            foreach (DB::table($courseTable . ' as x')->distinct()->get(["x.{$col} as cat", 'x.course_id']) as $r) {
                $catalogCourses[$src][(int) $r->cat][(int) $r->course_id] = true;
            }
            foreach (DB::table($courseTable . ' as x')->join('competency_course as cc', 'cc.course_id', '=', 'x.course_id')
                ->distinct()->get(["x.{$col} as cat", 'cc.competency_id as c']) as $r) {
                $catalogCompetencies[$src][(int) $r->cat][(int) $r->c] = true;
            }
            foreach (DB::table('career_course as cc')->join($courseTable . ' as x', 'x.course_id', '=', 'cc.course_id')
                ->join($catalog . ' as e', 'e.id', '=', "x.{$col}")->whereNotNull('e.market_entity_id')
                ->distinct()->get(['e.market_entity_id as me', 'cc.career_id']) as $r) {
                $entityCareers[(int) $r->me][(int) $r->career_id] = true;
            }
        }

        $out = ['career_year' => [], 'career_period' => ['s1' => [], 's2' => []],
            'demand' => ['language' => [], 'technology' => [], 'methodology' => []], 'course_jobs' => [], 'competency_jobs' => []];

        for ($c0 = $lo; $c0 < $hi; $c0 += $chunk) {
            $c1 = min($hi, $c0 + $chunk);
            $published = [];   // oferta => published_at
            $hits = [];        // oferta => ['careers' => [id => true], 'courses' => [id => true]]

            foreach (self::SOURCES as $src => [$pivot, $a, $col]) {
                $comps = [];   // oferta => competencias por esta fuente
                $rows = DB::select(
                    "SELECT DISTINCT {$a}.job_offer_id AS o, {$a}.{$col} AS cat, jo.published_at AS p
                       FROM job_offers jo
                       JOIN {$pivot} {$a} ON {$a}.job_offer_id = jo.id
                      WHERE jo.id >= ? AND jo.id < ? AND jo.published_at >= ? AND jo.published_at < ?"
                        . IndicatorScope::offersSql('jo'),
                    [$c0, $c1, $from, $to]
                );
                foreach ($rows as $r) {
                    $cat = (int) $r->cat;
                    $o = (int) $r->o;
                    $published[$o] = $r->p;
                    $out['demand'][$src][$cat] = true;
                    $hits[$o]['courses'] = ($hits[$o]['courses'] ?? []) + ($catalogCourses[$src][$cat] ?? []);
                    $me = $catalogEntity[$src][$cat] ?? null;
                    if ($me !== null && isset($entityCareers[$me])) {
                        $hits[$o]['careers'] = ($hits[$o]['careers'] ?? []) + $entityCareers[$me];
                    }
                    if (isset($catalogCompetencies[$src][$cat])) {
                        $comps[$o] = ($comps[$o] ?? []) + $catalogCompetencies[$src][$cat];
                    }
                }
                unset($rows);
                foreach ($comps as $set) {
                    foreach ($set as $comp => $_) {
                        $out['competency_jobs'][$comp] = ($out['competency_jobs'][$comp] ?? 0) + 1;
                    }
                }
            }

            foreach ($hits as $o => $h) {
                foreach ($h['courses'] ?? [] as $course => $_) {
                    $out['course_jobs'][$course] = ($out['course_jobs'][$course] ?? 0) + 1;
                }
                $p = $published[$o];
                $period = $p <= $s1End ? 's1' : (($p >= $s2Start && $p <= $s2End) ? 's2' : null);
                foreach ($h['careers'] ?? [] as $career => $_) {
                    $out['career_year'][$career] = ($out['career_year'][$career] ?? 0) + 1;
                    if ($period !== null) {
                        $out['career_period'][$period][$career] = ($out['career_period'][$period][$career] ?? 0) + 1;
                    }
                }
            }
        }

        return $out;
    }

    /** Suma partes (conteos) y une conjuntos (demanda). */
    public static function merge(int $year, array $parts): array
    {
        $out = ['year' => $year, 'career_year' => [], 'career_period' => ['s1' => [], 's2' => []],
            'demand' => ['language' => [], 'technology' => [], 'methodology' => []], 'course_jobs' => [], 'competency_jobs' => []];
        $sum = function (array &$into, array $add) {
            foreach ($add as $k => $n) {
                $into[$k] = ($into[$k] ?? 0) + $n;
            }
        };
        foreach ($parts as $p) {
            $sum($out['career_year'], $p['career_year']);
            $sum($out['career_period']['s1'], $p['career_period']['s1']);
            $sum($out['career_period']['s2'], $p['career_period']['s2']);
            $sum($out['course_jobs'], $p['course_jobs']);
            $sum($out['competency_jobs'], $p['competency_jobs']);
            foreach ($p['demand'] as $src => $set) {
                $out['demand'][$src] += $set;
            }
        }
        foreach (['career_year', 'course_jobs', 'competency_jobs'] as $k) {
            ksort($out[$k]);
        }
        ksort($out['career_period']['s1']);
        ksort($out['career_period']['s2']);
        $out['calculated_at'] = now()->toDateTimeString();

        return $out;
    }

    /* ---------------- entidades con alguna oferta (todo el histórico) ---------------- */

    public static function entitiesKey(string $src): string
    {
        return 'indicators:curricular_entities:v' . IndicatorScope::cacheVersion() . ":{$src}";
    }

    /** Ids del catálogo de la fuente con al menos una oferta no excluida por título. */
    public static function entitiesWithOffers(string $src): array
    {
        return Cache::remember(self::entitiesKey($src), self::TTL, fn () => self::computeEntities($src));
    }

    public static function warmEntities(string $src): array
    {
        $ids = self::computeEntities($src);
        Cache::put(self::entitiesKey($src), $ids, self::TTL);

        return ['fuente' => $src, 'entidades' => count($ids)];
    }

    private static function computeEntities(string $src): array
    {
        [$pivot, , $col] = self::SOURCES[$src];

        return array_map('intval', IndicatorScope::offers(DB::table($pivot . ' as p')
            ->join('job_offers as jo', 'jo.id', '=', 'p.job_offer_id'), 'jo')
            ->distinct()->orderBy('p.' . $col)->pluck('p.' . $col)->all());
    }
}
