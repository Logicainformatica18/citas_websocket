<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\CCTCService;

class PeAlignmentIndicatorController extends Controller
{
    protected CCTCService $cctc;

    public function __construct(CCTCService $cctc)
    {
        $this->cctc = $cctc;
    }

    /* =====================================================
       INDEX
    ===================================================== */

    public function index(Request $request)
    {
        $careerId = (int) $request->get('career_id');
        $year     = (int) $request->get('year', now()->year);
        $period   = $request->get('period', 's1');

        [$laborWeight, $trendWeight] = $this->getWeights();

        $availableCareers = $this->getAvailableCareers();
    $meta = $careerId
    ? $this->getGlobalMeta($careerId, $year)
    : [
        'year' => $year,
        'vacantes_analizadas' => 0,
        'reportes_analizados' => 0,
        'actualizado' => now()->toDateTimeString(),
    ];


        if (!$careerId) {
            return $this->renderEmpty(
                $availableCareers,
                $meta,
                $laborWeight,
                $trendWeight,
                $year,
                $period
            );
        }

        /* =====================================================
           🔥 1️⃣ OBTENER CURSOS (ÚNICA FUENTE DE VERDAD)
        ===================================================== */

        $courses = $this->cctc->getCourses($careerId, $year);

        /* =====================================================
           🔥 2️⃣ AGRUPAR POR COMPETENCIA
        ===================================================== */

       $competencies = $this->groupCoursesByCompetency(
    $courses,
    $careerId,
    $year
);


        /* =====================================================
           🔥 3️⃣ RESUMEN
        ===================================================== */
$summary = $this->calculateCareerAlignmentSummaryQuartile(
    $careerId,
    $year,
    $laborWeight,
    $trendWeight
);


//       $summary = $this->calculateCareerAlignmentSummary(
//     $careerId,
//     $year,
//     $laborWeight,
//     $trendWeight
// );


        return Inertia::render(
            'DashboardAlignCompetence/PeAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => $careerId,
                    'year'      => $year,
                    'period'    => $period,
                ],
                'availableCareers' => $availableCareers,
                'weights' => [
                    'laborWeight'  => round($laborWeight * 100, 1),
                    'trendsWeight' => round($trendWeight * 100, 1),
                ],
                'summary'      => $summary,
                'competencies' => $competencies,
                'meta'         => $meta,
            ]
        );
    }
private function calculateCareerAlignmentSummaryQuartile(
    int $careerId,
    int $year,
    float $laborWeight,
    float $trendWeight
): array {

    $result = DB::selectOne("
        WITH market_base AS (
            SELECT
                cc.competency_id,
                COUNT(DISTINCT jo.id) AS job_count
            FROM competency_course cc
            JOIN course_technology ct ON ct.course_id = cc.course_id
            JOIN technology_job tj ON tj.technology_id = ct.technology_id
            JOIN job_offers jo ON jo.id = tj.job_offer_id
            WHERE YEAR(jo.published_at) = ?
            GROUP BY cc.competency_id
        ),

        market_ranked AS (
            SELECT
                competency_id,
                job_count,
                NTILE(4) OVER (ORDER BY job_count DESC) AS quartile
            FROM market_base
        ),

        trend_base AS (
            SELECT
                cc.competency_id,
                COUNT(DISTINCT et.id) AS trend_count
            FROM competency_course cc
            JOIN course_technology ct ON ct.course_id = cc.course_id
            JOIN technologies t ON t.id = ct.technology_id
            JOIN entity_trends et
                ON et.market_entity_id = t.market_entity_id
               AND et.year = ?
            GROUP BY cc.competency_id
        ),

        trend_ranked AS (
            SELECT
                competency_id,
                trend_count,
                NTILE(4) OVER (ORDER BY trend_count DESC) AS quartile
            FROM trend_base
        )

        SELECT
            COUNT(DISTINCT comp.id) AS total_competencies,

            AVG(
                CASE 
                    WHEN mr.quartile = 1 THEN 1
                    WHEN mr.quartile = 2 THEN 0.75
                    WHEN mr.quartile = 3 THEN 0.5
                    ELSE 0
                END
            ) AS market_score,

            AVG(
                CASE 
                    WHEN tr.quartile = 1 THEN 1
                    WHEN tr.quartile = 2 THEN 0.75
                    WHEN tr.quartile = 3 THEN 0.5
                    ELSE 0
                END
            ) AS trend_score

        FROM competencies comp
        LEFT JOIN market_ranked mr ON mr.competency_id = comp.id
        LEFT JOIN trend_ranked tr ON tr.competency_id = comp.id
        WHERE comp.career_id = ?
    ", [$year, $year, $careerId]);

    $total = (int) $result->total_competencies;

    if ($total === 0) {
        return [
            'total_competencies' => 0,
            'market_rate' => 0,
            'trend_rate' => 0,
            'final_index' => 0,
        ];
    }

    $marketRate = $result->market_score ?? 0;
    $trendRate  = $result->trend_score ?? 0;

    $finalIndex =
        ($laborWeight * $marketRate) +
        ($trendWeight * $trendRate);

    return [
        'total_competencies' => $total,
        'market_rate' => round($marketRate * 100, 1),
        'trend_rate'  => round($trendRate * 100, 1),
        'final_index' => round($finalIndex * 100, 1),
    ];
}

    /* =====================================================
       AGRUPAR CURSOS → COMPETENCIAS
    ===================================================== */

   private function groupCoursesByCompetency($courses, int $careerId, int $year)
{
    $minMarketSignals = 3;
    $minTrendSignals  = 2;

    $courseStates = $courses->keyBy('id');

    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get();

    $competencyCourses = DB::table('competency_course')
        ->whereIn('competency_id', $competencies->pluck('id'))
        ->get()
        ->groupBy('competency_id');

    return $competencies->map(function ($comp) use (
        $competencyCourses,
        $courseStates,
        $minMarketSignals,
        $minTrendSignals,
        $year
    ) {

        $related = $competencyCourses[$comp->id] ?? collect();

        $marketCount = 0;
        $trendCount  = 0;

        foreach ($related as $rel) {

            if (!isset($courseStates[$rel->course_id])) {
                continue;
            }

            $courseId = $rel->course_id;

            /* =========================
               MERCADO (conteo real)
            ========================== */

            $marketCount += DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('language_job as lj', 'lj.language_id', '=', 'l.id')
                ->join('job_offers as jo', 'jo.id', '=', 'lj.job_offer_id')
                ->where('cl.course_id', $courseId)
                ->whereYear('jo.published_at', $year)
                ->distinct('jo.id')
                ->count('jo.id');

            $marketCount += DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('technology_job as tj', 'tj.technology_id', '=', 't.id')
                ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
                ->where('ct.course_id', $courseId)
                ->whereYear('jo.published_at', $year)
                ->distinct('jo.id')
                ->count('jo.id');

            $marketCount += DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('methodology_job as mj', 'mj.methodology_id', '=', 'm.id')
                ->join('job_offers as jo', 'jo.id', '=', 'mj.job_offer_id')
                ->where('cm.course_id', $courseId)
                ->whereYear('jo.published_at', $year)
                ->distinct('jo.id')
                ->count('jo.id');

            /* =========================
               TENDENCIAS (conteo real)
            ========================== */

            $trendCount += DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'l.market_entity_id')
                ->where('cl.course_id', $courseId)
                ->where('et.year', $year)
                ->count();

            $trendCount += DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 't.market_entity_id')
                ->where('ct.course_id', $courseId)
                ->where('et.year', $year)
                ->count();

            $trendCount += DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'm.market_entity_id')
                ->where('cm.course_id', $courseId)
                ->where('et.year', $year)
                ->count();
        }

        $hasMarket = $marketCount >= $minMarketSignals;
        $hasTrend  = $trendCount  >= $minTrendSignals;

        $status = match (true) {
            $hasMarket && $hasTrend => 'aligned',
            $hasMarket || $hasTrend => 'partial',
            default => 'gap',
        };

        return [
            'id' => $comp->id,
            'name' => $comp->name,
            'market_match' => $hasMarket,
            'trend_match'  => $hasTrend,
            'status'       => $status,
        ];
    });
}

    /* =====================================================
       RESUMEN
    ===================================================== */

   private function calculateCareerAlignmentSummary(
    int $careerId,
    int $year,
    float $laborWeight,
    float $trendWeight
): array {

    $base = DB::selectOne("
        SELECT
            COUNT(DISTINCT comp.id) AS total_competencies,

            COUNT(DISTINCT CASE WHEN market.job_count >= 3 THEN comp.id END) AS market_aligned,

            COUNT(DISTINCT CASE WHEN trend.trend_count >= 2 THEN comp.id END) AS trend_aligned,

            COUNT(DISTINCT CASE
                WHEN (market.job_count IS NULL OR market.job_count < 3)
                 AND (trend.trend_count IS NULL OR trend.trend_count < 2)
                THEN comp.id
            END) AS gap_critico

        FROM competencies comp

        LEFT JOIN (
            SELECT
                cc.competency_id,
                COUNT(DISTINCT jo.id) AS job_count
            FROM competency_course cc
            JOIN course_technology ct ON ct.course_id = cc.course_id
            JOIN technology_job tj ON tj.technology_id = ct.technology_id
            JOIN job_offers jo ON jo.id = tj.job_offer_id
            WHERE YEAR(jo.published_at) = ?
            GROUP BY cc.competency_id
        ) market ON market.competency_id = comp.id

        LEFT JOIN (
            SELECT
                cc.competency_id,
                COUNT(DISTINCT et.id) AS trend_count
            FROM competency_course cc
            JOIN course_technology ct ON ct.course_id = cc.course_id
            JOIN technologies t ON t.id = ct.technology_id
            JOIN entity_trends et
                ON et.market_entity_id = t.market_entity_id
               AND et.year = ?
            GROUP BY cc.competency_id
        ) trend ON trend.competency_id = comp.id

        WHERE comp.career_id = ?
    ", [$year, $year, $careerId]);

    $total = (int) $base->total_competencies;

    if ($total === 0) {
        return [
            'total_competencies' => 0,
            'market_rate' => 0,
            'trend_rate' => 0,
            'final_index' => 0,
            'gap_critico' => 0,
            'gap_mercado' => 0,
            'gap_tendencia' => 0,
        ];
    }

    $marketRate = $base->market_aligned / $total;
    $trendRate  = $base->trend_aligned  / $total;

    $finalScore =
        ($laborWeight * $marketRate) +
        ($trendWeight * $trendRate);

    return [
        'total_competencies' => $total,
        'market_rate' => round($marketRate * 100, 1),
        'trend_rate'  => round($trendRate * 100, 1),
        'final_index' => round($finalScore * 100, 1),
        'gap_critico' => (int) $base->gap_critico,
        'gap_mercado' => 0,
        'gap_tendencia' => 0,
    ];
}

    /* =====================================================
       VER CURSOS DE UNA COMPETENCIA
    ===================================================== */
public function getCourses(
    int $careerId,
    int $year
): Collection {

    $minMarketSignals = 3;
    $minTrendSignals  = 2;

    $courses = DB::table('career_course as cc')
        ->join('courses as c', 'c.id', '=', 'cc.course_id')
        ->where('cc.career_id', $careerId)
        ->select('c.id', 'c.name')
        ->orderBy('c.name')
        ->get();

    if ($courses->isEmpty()) {
        return collect();
    }

    $courseIds = $courses->pluck('id');

    /* =====================================================
       MERCADO — CONTEO REAL (≥ 3 señales)
    ===================================================== */

    $marketCounts = collect()

        // Lenguajes
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('language_job as lj', 'lj.language_id', '=', 'l.id')
                ->join('job_offers as jo', 'jo.id', '=', 'lj.job_offer_id')
                ->whereIn('cl.course_id', $courseIds)
                ->whereYear('jo.published_at', $year)
                ->select('cl.course_id', DB::raw('COUNT(DISTINCT jo.id) as total'))
                ->groupBy('cl.course_id')
                ->get()
        )

        // Tecnologías
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('technology_job as tj', 'tj.technology_id', '=', 't.id')
                ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
                ->whereIn('ct.course_id', $courseIds)
                ->whereYear('jo.published_at', $year)
                ->select('ct.course_id', DB::raw('COUNT(DISTINCT jo.id) as total'))
                ->groupBy('ct.course_id')
                ->get()
        )

        // Metodologías
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('methodology_job as mj', 'mj.methodology_id', '=', 'm.id')
                ->join('job_offers as jo', 'jo.id', '=', 'mj.job_offer_id')
                ->whereIn('cm.course_id', $courseIds)
                ->whereYear('jo.published_at', $year)
                ->select('cm.course_id', DB::raw('COUNT(DISTINCT jo.id) as total'))
                ->groupBy('cm.course_id')
                ->get()
        )

        ->groupBy('course_id')
        ->map(fn($rows) => $rows->sum('total'));

    /* =====================================================
       TENDENCIAS — CONTEO REAL (≥ 2 señales)
    ===================================================== */

    $trendCounts = collect()

        // Lenguajes
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'l.market_entity_id')
                ->whereIn('cl.course_id', $courseIds)
                ->where('et.year', $year)
                ->select('cl.course_id', DB::raw('COUNT(et.id) as total'))
                ->groupBy('cl.course_id')
                ->get()
        )

        // Tecnologías
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 't.market_entity_id')
                ->whereIn('ct.course_id', $courseIds)
                ->where('et.year', $year)
                ->select('ct.course_id', DB::raw('COUNT(et.id) as total'))
                ->groupBy('ct.course_id')
                ->get()
        )

        // Metodologías
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'm.market_entity_id')
                ->whereIn('cm.course_id', $courseIds)
                ->where('et.year', $year)
                ->select('cm.course_id', DB::raw('COUNT(et.id) as total'))
                ->groupBy('cm.course_id')
                ->get()
        )

        ->groupBy('course_id')
        ->map(fn($rows) => $rows->sum('total'));

    /* =====================================================
       MAPEO FINAL
    ===================================================== */

    return $courses->map(function ($course) use (
        $marketCounts,
        $trendCounts,
        $minMarketSignals,
        $minTrendSignals
    ) {

        $marketTotal = $marketCounts[$course->id] ?? 0;
        $trendTotal  = $trendCounts[$course->id] ?? 0;

        $hasMarket = $marketTotal >= $minMarketSignals;
        $hasTrend  = $trendTotal  >= $minTrendSignals;

        $connections =
            ($hasMarket ? 1 : 0) +
            ($hasTrend ? 1 : 0);

        $status = match (true) {
            $hasMarket && $hasTrend => 'Estrategicamente alineado',
            $hasMarket || $hasTrend => 'Alineado',
            default => 'No alineado',
        };

        return [
            'id' => $course->id,
            'name' => $course->name,
            'estado' => $status,
            'connections' => $connections,

            'market_match' => $hasMarket,
            'trend_match'  => $hasTrend,

            'empleo'       => $hasMarket ? 'Demanda activa' : 'Sin demanda',
            'tendencias'   => $hasTrend ? 'Detectado' : 'No detectado',

            'gap_label' => null,
            'gap_count' => null,
        ];
    });
}

    public function getCompetencyCourses(Request $request, int $competencyId)
    {
        $careerId = (int) $request->get('career_id');
        $year     = (int) $request->get('year');

        if (!$careerId || !$year) {
            return response()->json([]);
        }

        $courseStates = $this->cctc
            ->getCourses($careerId, $year)
            ->keyBy('id');

        $related = DB::table('competency_course as cc')
            ->join('courses as c', 'c.id', '=', 'cc.course_id')
            ->where('cc.competency_id', $competencyId)
            ->select('c.id', 'c.name')
            ->orderBy('c.name')
            ->get();

        return response()->json(
            $related->map(function ($course) use ($courseStates) {

                $estado = $courseStates[$course->id]['estado'] ?? 'No alineado';

                return [
                    'id'     => $course->id,
                    'name'   => $course->name,
                    'status' => $estado,
                ];
            })
        );
    }

    /* =====================================================
       HELPERS
    ===================================================== */

    private function getWeights(): array
    {
        $weights = Prueba::getActive('pe_alignment');

        return [
            (float) ($weights?->labor_weight ?? 0.7),
            (float) ($weights?->trend_weight ?? 0.3),
        ];
    }

    private function getAvailableCareers()
    {
        return DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

   private function getGlobalMeta(
    int $careerId,
    int $year
): array {

    /* =========================
       1️⃣ ENTIDADES DE LA CARRERA
    ========================== */

    $entityIds = collect()

        ->merge(
            DB::table('career_course as cc')
                ->join('course_language as cl', 'cl.course_id', '=', 'cc.course_id')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->where('cc.career_id', $careerId)
                ->pluck('l.market_entity_id')
        )

        ->merge(
            DB::table('career_course as cc')
                ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->where('cc.career_id', $careerId)
                ->pluck('t.market_entity_id')
        )

        ->merge(
            DB::table('career_course as cc')
                ->join('course_methodology as cm', 'cm.course_id', '=', 'cc.course_id')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->where('cc.career_id', $careerId)
                ->pluck('m.market_entity_id')
        )

        ->filter()
        ->unique()
        ->values();

    if ($entityIds->isEmpty()) {
        return [
            'year' => $year,
            'vacantes_analizadas' => 0,
            'reportes_analizados' => 0,
            'actualizado' => now()->toDateTimeString(),
        ];
    }

    /* =========================
       2️⃣ VACANTES REALES CRUZADAS
    ========================== */

    $jobIds = collect()

        ->merge(
            DB::table('technology_job as tj')
                ->join('technologies as t', 't.id', '=', 'tj.technology_id')
                ->whereIn('t.market_entity_id', $entityIds)
                ->pluck('tj.job_offer_id')
        )

        ->merge(
            DB::table('language_job as lj')
                ->join('languages as l', 'l.id', '=', 'lj.language_id')
                ->whereIn('l.market_entity_id', $entityIds)
                ->pluck('lj.job_offer_id')
        )

        ->merge(
            DB::table('methodology_job as mj')
                ->join('methodologies as m', 'm.id', '=', 'mj.methodology_id')
                ->whereIn('m.market_entity_id', $entityIds)
                ->pluck('mj.job_offer_id')
        )

        ->unique()
        ->values();

    $vacantes = 0;

    if ($jobIds->isNotEmpty()) {
        $vacantes = DB::table('job_offers')
            ->whereIn('id', $jobIds)
            ->whereYear('published_at', $year)
            ->count();
    }

    /* =========================
       3️⃣ REPORTES REALES CRUZADOS
    ========================== */

    $reportes = DB::table('entity_trends')
        ->whereIn('market_entity_id', $entityIds)
        ->where('year', $year)
        ->count();

    return [
        'year' => $year,
        'vacantes_analizadas' => $vacantes,
        'reportes_analizados' => $reportes,
        'actualizado' => now()->toDateTimeString(),
    ];
}


    private function renderEmpty($careers, $meta, $lw, $tw, $year, $period)
    {
        return Inertia::render(
            'DashboardAlignCompetence/PeAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => null,
                    'year'      => $year,
                    'period'    => $period,
                ],
                'availableCareers' => $careers,
                'weights' => [
                    'laborWeight'  => round($lw * 100, 1),
                    'trendsWeight' => round($tw * 100, 1),
                ],
                'summary'      => null,
                'competencies' => [],
                'meta'         => $meta,
            ]
        );
    }
}
