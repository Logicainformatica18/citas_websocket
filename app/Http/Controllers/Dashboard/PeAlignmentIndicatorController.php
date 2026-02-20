<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\CCTCService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Models\Career;


class PeAlignmentIndicatorController extends Controller
{


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

      //  $courses = $this->cctc->getCourses($careerId, $year);

        /* =====================================================
           🔥 2️⃣ AGRUPAR POR COMPETENCIA
        ===================================================== */

//       $competencies = $this->getCompetenciesWithScore(
//     $careerId,
//     $year,
//     $laborWeight,
//     $trendWeight
// );
$competencies = $this->getCompetenciesFromMaterialized(
    $careerId,
    $year,
    $laborWeight,
    $trendWeight
);


        /* =====================================================
           🔥 3️⃣ RESUMEN
        ===================================================== */
// $summary = $this->calculateCareerAlignmentSummaryQuartile(
//     $careerId,
//     $year,
//     $laborWeight,
//     $trendWeight
// );

$summary = $this->calculateCareerAlignmentSummaryFromMaterialized(
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
                'career' => $careerId ? Career::find($careerId) : null,
            ]
        );
    }
// private function calculateCareerAlignmentSummaryQuartile(
//     int $careerId,
//     int $year,
//     float $laborWeight,
//     float $trendWeight
// ): array {

// $result = DB::selectOne("
//     WITH market_base AS (

//         /* =========================
//            TECNOLOGÍAS
//         ========================== */
//         SELECT
//             cc.competency_id,
//             jo.id AS job_id
//         FROM competency_course cc
//         JOIN course_technology ct ON ct.course_id = cc.course_id
//         JOIN technology_job tj ON tj.technology_id = ct.technology_id
//         JOIN job_offers jo ON jo.id = tj.job_offer_id
//         WHERE YEAR(jo.published_at) = ?

//         UNION ALL

//         /* =========================
//            LENGUAJES
//         ========================== */
//         SELECT
//             cc.competency_id,
//             jo.id AS job_id
//         FROM competency_course cc
//         JOIN course_language cl ON cl.course_id = cc.course_id
//         JOIN language_job lj ON lj.language_id = cl.language_id
//         JOIN job_offers jo ON jo.id = lj.job_offer_id
//         WHERE YEAR(jo.published_at) = ?

//         UNION ALL

//         /* =========================
//            METODOLOGÍAS
//         ========================== */
//         SELECT
//             cc.competency_id,
//             jo.id AS job_id
//         FROM competency_course cc
//         JOIN course_methodology cm ON cm.course_id = cc.course_id
//         JOIN methodology_job mj ON mj.methodology_id = cm.methodology_id
//         JOIN job_offers jo ON jo.id = mj.job_offer_id
//         WHERE YEAR(jo.published_at) = ?
//     ),

//     market_agg AS (
//         SELECT
//             competency_id,
//             COUNT(DISTINCT job_id) AS job_count
//         FROM market_base
//         GROUP BY competency_id
//     ),

//     market_ranked AS (
//         SELECT
//             competency_id,
//             job_count,
//             NTILE(4) OVER (ORDER BY job_count DESC) AS quartile
//         FROM market_agg
//     ),

//     /* ===================================================== */

//     trend_base AS (

//         /* TECNOLOGÍAS */
//         SELECT
//             cc.competency_id,
//             et.id AS trend_id
//         FROM competency_course cc
//         JOIN course_technology ct ON ct.course_id = cc.course_id
//         JOIN technologies t ON t.id = ct.technology_id
//         JOIN entity_trends et
//             ON et.market_entity_id = t.market_entity_id
//            AND et.year = ?

//         UNION ALL

//         /* LENGUAJES */
//         SELECT
//             cc.competency_id,
//             et.id AS trend_id
//         FROM competency_course cc
//         JOIN course_language cl ON cl.course_id = cc.course_id
//         JOIN languages l ON l.id = cl.language_id
//         JOIN entity_trends et
//             ON et.market_entity_id = l.market_entity_id
//            AND et.year = ?

//         UNION ALL

//         /* METODOLOGÍAS */
//         SELECT
//             cc.competency_id,
//             et.id AS trend_id
//         FROM competency_course cc
//         JOIN course_methodology cm ON cm.course_id = cc.course_id
//         JOIN methodologies m ON m.id = cm.methodology_id
//         JOIN entity_trends et
//             ON et.market_entity_id = m.market_entity_id
//            AND et.year = ?
//     ),

//     trend_agg AS (
//         SELECT
//             competency_id,
//             COUNT(DISTINCT trend_id) AS trend_count
//         FROM trend_base
//         GROUP BY competency_id
//     ),

//     trend_ranked AS (
//         SELECT
//             competency_id,
//             trend_count,
//             NTILE(4) OVER (ORDER BY trend_count DESC) AS quartile
//         FROM trend_agg
//     )

//     /* ===================================================== */

//     SELECT
//         COUNT(DISTINCT comp.id) AS total_competencies,

//         AVG(
//             CASE
//                 WHEN mr.job_count IS NULL THEN 0
//                 WHEN mr.quartile = 1 THEN 1
//                 WHEN mr.quartile = 2 THEN 0.75
//                 WHEN mr.quartile = 3 THEN 0.5
//                 ELSE 0.25
//             END
//         ) AS market_score,

//         AVG(
//             CASE
//                 WHEN tr.trend_count IS NULL THEN 0
//                 WHEN tr.quartile = 1 THEN 1
//                 WHEN tr.quartile = 2 THEN 0.75
//                 WHEN tr.quartile = 3 THEN 0.5
//                 ELSE 0.25
//             END
//         ) AS trend_score

//     FROM competencies comp
//     LEFT JOIN market_ranked mr ON mr.competency_id = comp.id
//     LEFT JOIN trend_ranked tr ON tr.competency_id = comp.id
//     WHERE comp.career_id = ?
// ", [
//     $year, $year, $year,   // market
//     $year, $year, $year,   // trend
//     $careerId
// ]);

//     $total = (int) $result->total_competencies;

//     if ($total === 0) {
//         return [
//             'total_competencies' => 0,
//             'market_rate' => 0,
//             'trend_rate' => 0,
//             'final_index' => 0,
//         ];
//     }

//     $marketRate = $result->market_score ?? 0;
//     $trendRate  = $result->trend_score ?? 0;

//     $finalIndex =
//         ($laborWeight * $marketRate) +
//         ($trendWeight * $trendRate);

//     return [
//         'total_competencies' => $total,
//         'market_rate' => round($marketRate * 100, 1),
//         'trend_rate'  => round($trendRate * 100, 1),
//         'final_index' => round($finalIndex * 100, 1),
//     ];
// }
public function refreshData(Request $request)
{
    $careerId = (int) $request->career_id;
    $year = now()->year;

    Artisan::call('pe:recalculate', [
        'career_id' => $careerId,
        '--year' => $year,
    ]);

    return response()->json(['status' => 'ok']);
}
private function calculateCareerAlignmentSummaryFromMaterialized(
    int $careerId,
    int $year,
    float $laborWeight,
    float $trendWeight
): array {

    $rows = DB::table('pe_alignment_competency_results')
        ->where('career_id', $careerId)
        ->where('year', $year)
        ->get(['market_score', 'trend_score']);

    $total = $rows->count();

    if ($total === 0) {
        return [
            'total_competencies' => 0,
            'market_rate' => 0,
            'trend_rate'  => 0,
            'final_index' => 0,
        ];
    }

    $marketAvg = $rows->avg('market_score');
    $trendAvg  = $rows->avg('trend_score');

    // 🔥 Recalcular final con pesos actuales
    $final =
        ($laborWeight * $marketAvg) +
        ($trendWeight * $trendAvg);

    return [
        'total_competencies' => $total,
        'market_rate' => round($marketAvg * 100, 1),
        'trend_rate'  => round($trendAvg * 100, 1),
        'final_index' => round($final * 100, 1),
    ];
}


public function updateWeights(Request $request)
{
    $request->validate([
        'labor_weight' => 'required|numeric|min:0|max:1',
        'trend_weight' => 'required|numeric|min:0|max:1',
    ]);

    // 🔥 Crear nueva ponderación
    $weight = Prueba::create([
        'labor_weight' => $request->labor_weight,
        'trend_weight' => $request->trend_weight,
        'context'      => 'pe_alignment',
        'is_active'    => 0, // se activará después
        'updated_by'   => auth()->id(),
    ]);

    // 🔥 Validar suma 100%
    if (!$weight->isValid()) {
        $weight->delete();

        return back()->withErrors([
            'weights' => 'Las ponderaciones deben sumar 100%',
        ]);
    }

    // 🔥 Activar (desactiva anteriores del mismo contexto)
    $weight->activate();

    return back()->with('success', true);
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
    int $year,
    float $laborWeight = 0.7,
    float $trendWeight = 0.3
): Collection {

  $rows = DB::select("
    WITH market_base AS (

        /* =========================
           TECNOLOGÍAS
        ========================== */
        SELECT
            cc.course_id,
            jo.id AS job_id
        FROM career_course cc
        JOIN course_technology ct ON ct.course_id = cc.course_id
        JOIN technology_job tj ON tj.technology_id = ct.technology_id
        JOIN job_offers jo ON jo.id = tj.job_offer_id
        WHERE cc.career_id = ?
          AND YEAR(jo.published_at) = ?

        UNION ALL

        /* =========================
           LENGUAJES
        ========================== */
        SELECT
            cc.course_id,
            jo.id AS job_id
        FROM career_course cc
        JOIN course_language cl ON cl.course_id = cc.course_id
        JOIN language_job lj ON lj.language_id = cl.language_id
        JOIN job_offers jo ON jo.id = lj.job_offer_id
        WHERE cc.career_id = ?
          AND YEAR(jo.published_at) = ?

        UNION ALL

        /* =========================
           METODOLOGÍAS
        ========================== */
        SELECT
            cc.course_id,
            jo.id AS job_id
        FROM career_course cc
        JOIN course_methodology cm ON cm.course_id = cc.course_id
        JOIN methodology_job mj ON mj.methodology_id = cm.methodology_id
        JOIN job_offers jo ON jo.id = mj.job_offer_id
        WHERE cc.career_id = ?
          AND YEAR(jo.published_at) = ?
    ),

    market_agg AS (
        SELECT
            course_id,
            COUNT(DISTINCT job_id) AS job_count
        FROM market_base
        GROUP BY course_id
    ),

   market_ranked AS (
    SELECT
        course_id,
        job_count
    FROM market_agg
),


    /* ===================================================== */

    trend_base AS (

        /* TECNOLOGÍAS */
        SELECT
            cc.course_id,
            et.id AS trend_id
        FROM career_course cc
        JOIN course_technology ct ON ct.course_id = cc.course_id
        JOIN technologies t ON t.id = ct.technology_id
        JOIN entity_trends et
            ON et.market_entity_id = t.market_entity_id
           AND et.year = ?
        WHERE cc.career_id = ?

        UNION ALL

        /* LENGUAJES */
        SELECT
            cc.course_id,
            et.id AS trend_id
        FROM career_course cc
        JOIN course_language cl ON cl.course_id = cc.course_id
        JOIN languages l ON l.id = cl.language_id
        JOIN entity_trends et
            ON et.market_entity_id = l.market_entity_id
           AND et.year = ?
        WHERE cc.career_id = ?

        UNION ALL

        /* METODOLOGÍAS */
        SELECT
            cc.course_id,
            et.id AS trend_id
        FROM career_course cc
        JOIN course_methodology cm ON cm.course_id = cc.course_id
        JOIN methodologies m ON m.id = cm.methodology_id
        JOIN entity_trends et
            ON et.market_entity_id = m.market_entity_id
           AND et.year = ?
        WHERE cc.career_id = ?
    ),

    trend_agg AS (
        SELECT
            course_id,
            COUNT(DISTINCT trend_id) AS trend_count
        FROM trend_base
        GROUP BY course_id
    ),

    trend_ranked AS (
    SELECT
        course_id,
        trend_count
    FROM trend_agg
)


    /* ===================================================== */

    SELECT
        c.id,
        c.name,

        CASE
    WHEN mr.job_count IS NULL THEN 0
    WHEN mr.job_count >= 300 THEN 1
    WHEN mr.job_count >= 150 THEN 0.75
    WHEN mr.job_count >= 50 THEN 0.5
    ELSE 0.25
END AS market_score,


       CASE
    WHEN tr.trend_count IS NULL THEN 0
    WHEN tr.trend_count >= 10 THEN 1
    WHEN tr.trend_count >= 5 THEN 0.75
    WHEN tr.trend_count >= 2 THEN 0.5
    ELSE 0.25
END AS trend_score


    FROM career_course cc
    JOIN courses c ON c.id = cc.course_id
    LEFT JOIN market_ranked mr ON mr.course_id = c.id
    LEFT JOIN trend_ranked tr ON tr.course_id = c.id
    WHERE cc.career_id = ?
", [
    $careerId, $year,
    $careerId, $year,
    $careerId, $year,

    $year, $careerId,
    $year, $careerId,
    $year, $careerId,

    $careerId
]);


    return collect($rows)
        ->map(function ($row) use ($laborWeight, $trendWeight) {



            $marketValue = $row->market_score ?? 0;
            $trendValue  = $row->trend_score ?? 0;

            $finalScore =
                ($laborWeight * $marketValue) +
                ($trendWeight * $trendValue);

            $percentage = round($finalScore * 100, 1);

           if ($marketValue == 0 && $trendValue == 0) {
    $level = 'Crítica'; // 🔴 No tiene nada
} else {
    $level = match (true) {
        $percentage >= 80 => 'Fuerte',
        $percentage >= 60 => 'Media',
        $percentage >= 40 => 'Débil',
        default           => 'Baja',
    };
}


            return [
                'id' => $row->id,
                'name' => $row->name,
                'final_score' => $percentage,
                'level' => $level,
            ];
        })
        ->sortByDesc('final_score')
        ->values();
}
public function analyzeCareerWithAI(Request $request)
{
    $careerId = (int) $request->career_id;
    $year     = (int) $request->year ?? now()->year;

    if (!$careerId) {
        return response()->json([
            'error' => 'Carrera requerida'
        ], 422);
    }

    try {

        Artisan::call('career:recommend', [
            'career_id' => $careerId,
            '--year'    => $year,
        ]);

        return response()->json([
            'status' => 'ok'
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}

// private function getCompetenciesWithScore(
//     int $careerId,
//     int $year,
//     float $laborWeight,
//     float $trendWeight
// ) {

//   $rows = DB::select("
// WITH market_base AS (

//     -- Tecnologías
//     SELECT
//         cc.competency_id,
//         COUNT(DISTINCT jo.id) AS job_count
//     FROM competency_course cc
//     JOIN course_technology ct ON ct.course_id = cc.course_id
//     JOIN technology_job tj ON tj.technology_id = ct.technology_id
//     JOIN job_offers jo ON jo.id = tj.job_offer_id
//     WHERE YEAR(jo.published_at) = ?
//     GROUP BY cc.competency_id

//     UNION ALL

//     -- Lenguajes
//     SELECT
//         cc.competency_id,
//         COUNT(DISTINCT jo.id) AS job_count
//     FROM competency_course cc
//     JOIN course_language cl ON cl.course_id = cc.course_id
//     JOIN language_job lj ON lj.language_id = cl.language_id
//     JOIN job_offers jo ON jo.id = lj.job_offer_id
//     WHERE YEAR(jo.published_at) = ?
//     GROUP BY cc.competency_id

//     UNION ALL

//     -- Metodologías
//     SELECT
//         cc.competency_id,
//         COUNT(DISTINCT jo.id) AS job_count
//     FROM competency_course cc
//     JOIN course_methodology cm ON cm.course_id = cc.course_id
//     JOIN methodology_job mj ON mj.methodology_id = cm.methodology_id
//     JOIN job_offers jo ON jo.id = mj.job_offer_id
//     WHERE YEAR(jo.published_at) = ?
//     GROUP BY cc.competency_id
// ),

// market_aggregated AS (
//     SELECT
//         competency_id,
//         SUM(job_count) AS job_count
//     FROM market_base
//     GROUP BY competency_id
// ),

// market_ranked AS (
//     SELECT
//         competency_id,
//         job_count,
//         NTILE(4) OVER (ORDER BY job_count DESC) AS quartile
//     FROM market_aggregated
// ),

// trend_base AS (

//     -- Tecnologías
//     SELECT
//         cc.competency_id,
//         COUNT(DISTINCT et.id) AS trend_count
//     FROM competency_course cc
//     JOIN course_technology ct ON ct.course_id = cc.course_id
//     JOIN technologies t ON t.id = ct.technology_id
//     JOIN entity_trends et
//         ON et.market_entity_id = t.market_entity_id
//        AND et.year = ?
//     GROUP BY cc.competency_id

//     UNION ALL

//     -- Lenguajes
//     SELECT
//         cc.competency_id,
//         COUNT(DISTINCT et.id) AS trend_count
//     FROM competency_course cc
//     JOIN course_language cl ON cl.course_id = cc.course_id
//     JOIN languages l ON l.id = cl.language_id
//     JOIN entity_trends et
//         ON et.market_entity_id = l.market_entity_id
//        AND et.year = ?
//     GROUP BY cc.competency_id

//     UNION ALL

//     -- Metodologías
//     SELECT
//         cc.competency_id,
//         COUNT(DISTINCT et.id) AS trend_count
//     FROM competency_course cc
//     JOIN course_methodology cm ON cm.course_id = cc.course_id
//     JOIN methodologies m ON m.id = cm.methodology_id
//     JOIN entity_trends et
//         ON et.market_entity_id = m.market_entity_id
//        AND et.year = ?
//     GROUP BY cc.competency_id
// ),

// trend_aggregated AS (
//     SELECT
//         competency_id,
//         SUM(trend_count) AS trend_count
//     FROM trend_base
//     GROUP BY competency_id
// ),

// trend_ranked AS (
//     SELECT
//         competency_id,
//         trend_count,
//         NTILE(4) OVER (ORDER BY trend_count DESC) AS quartile
//     FROM trend_aggregated
// )

// SELECT
//     comp.id,
//     comp.name,

//     CASE
//         WHEN mr.job_count IS NULL THEN 0
//         WHEN mr.quartile = 1 THEN 1
//         WHEN mr.quartile = 2 THEN 0.75
//         WHEN mr.quartile = 3 THEN 0.5
//         ELSE 0.25
//     END AS market_score,

//     CASE
//         WHEN tr.trend_count IS NULL THEN 0
//         WHEN tr.quartile = 1 THEN 1
//         WHEN tr.quartile = 2 THEN 0.75
//         WHEN tr.quartile = 3 THEN 0.5
//         ELSE 0.25
//     END AS trend_score

// FROM competencies comp
// LEFT JOIN market_ranked mr ON mr.competency_id = comp.id
// LEFT JOIN trend_ranked tr ON tr.competency_id = comp.id
// WHERE comp.career_id = ?
// ", [
//     $year, $year, $year,  // market (3 bloques)
//     $year, $year, $year,  // trend (3 bloques)
//     $careerId
// ]);


//     return collect($rows)
//         ->map(function ($row) use ($laborWeight, $trendWeight) {

//             $marketValue = $row->market_score ?? 0;
//             $trendValue  = $row->trend_score ?? 0;

//             $finalScore =
//                 ($laborWeight * $marketValue) +
//                 ($trendWeight * $trendValue);

//             $percentage = round($finalScore * 100, 1);

//           if ($marketValue == 0 && $trendValue == 0) {
//     $level = 'Crítica'; // 🔴 No tiene absolutamente nada
// } else {
//     $level = match (true) {
//         $percentage >= 80 => 'Fuerte',
//         $percentage >= 60 => 'Media',
//         $percentage >= 40 => 'Débil',
//         default           => 'Baja',
//     };
// }


//             return [
//                 'id' => $row->id,
//                 'name' => $row->name,
//                 'market_score' => round($marketValue * 100, 1),
//                 'trend_score'  => round($trendValue * 100, 1),
//                 'final_score'  => $percentage,
//                 'level'        => $level,
//             ];
//         })
//         ->sortByDesc('final_score')
//         ->values();
// }
private function getCompetenciesFromMaterialized(
    int $careerId,
    int $year,
    float $laborWeight,
    float $trendWeight
) {

    $rows = DB::table('pe_alignment_competency_results as r')
        ->join('competencies as c', 'c.id', '=', 'r.competency_id')
        ->where('r.career_id', $careerId)
        ->where('r.year', $year)
        ->select(
            'c.id',
            'c.name',
            'r.market_score',
            'r.trend_score',
            'r.job_count',
            'r.trend_count'
        )
        ->get();

    return $rows->map(function ($row) use ($laborWeight, $trendWeight) {

        $marketValue = (float) $row->market_score;
        $trendValue  = (float) $row->trend_score;

        // 🔥 Recalcular con pesos actuales
        $finalScore =
            ($laborWeight * $marketValue) +
            ($trendWeight * $trendValue);

        $percentage = round($finalScore * 100, 1);

        if ($marketValue == 0 && $trendValue == 0) {
            $level = 'Crítica';
        } else {
            $level = match (true) {
                $percentage >= 80 => 'Fuerte',
                $percentage >= 60 => 'Media',
                $percentage >= 40 => 'Débil',
                default           => 'Baja',
            };
        }

        return [
            'id'           => $row->id,
            'name'         => $row->name,
            'job_count'    => $row->job_count,
            'trend_count'  => $row->trend_count,
            'market_score' => round($marketValue * 100, 1),
            'trend_score'  => round($trendValue * 100, 1),
            'final_score'  => $percentage,
            'level'        => $level,
        ];
    })
    ->sortByDesc('final_score')
    ->values();
}
public function getCompetencyCourses(Request $request, int $competencyId)
{
    $careerId = (int) $request->get('career_id');
    $year     = (int) $request->get('year');

    if (!$careerId || !$year) {
        return response()->json([]);
    }

    // 🔥 Obtener cursos ya calculados con modelo porcentual
    [$laborWeight, $trendWeight] = $this->getWeights();

    $courseStates = $this->getCourses(
        $careerId,
        $year,
        $laborWeight,
        $trendWeight
    )->keyBy('id');

    // 🔥 Cursos asociados a la competencia
    $related = DB::table('competency_course as cc')
        ->join('courses as c', 'c.id', '=', 'cc.course_id')
        ->where('cc.competency_id', $competencyId)
        ->select('c.id', 'c.name')
        ->orderBy('c.name')
        ->get();

    return response()->json(
        $related->map(function ($course) use ($courseStates) {

            $courseData = $courseStates[$course->id] ?? null;

            return [
                'id' => $course->id,
                'name' => $course->name,
                'final_score' => $courseData['final_score'] ?? 0,
                'level' => $courseData['level'] ?? 'Crítica',
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
