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

    // 🔥 Definimos quarter y rango correctamente
    $quarter = $period === 's1' ? 1 : 4;

    $range = $period === 's1'
        ? ['start' => "{$year}-01-01", 'end' => "{$year}-06-30"]
        : ['start' => "{$year}-07-01", 'end' => "{$year}-12-31"];

    [$laborWeight, $trendWeight] = $this->getWeights();

    $availableCareers = $this->getAvailableCareers();
    $meta             = $this->getGlobalMeta($year, $period);

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
       🔥 NUEVA LÓGICA DIRECTA POR COMPETENCIA
    ===================================================== */

    $competencies = $this->getCompetencyAlignment(
        $careerId,
        $range,
        $year,
        $quarter
    );
$summary = $this->calculateCareerAlignmentSummary(
    $competencies,
    $laborWeight,
    $trendWeight
);
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
            'summary'      =>  $summary,
            'competencies' => $competencies,
            'meta'         => $meta,
        ]
    );
}


    /* =====================================================
       VER CURSOS DE UNA COMPETENCIA
    ===================================================== */
private function getCompetencyAlignment(
    int $careerId,
    array $range,
    int $year,
    int $quarter
)
{
    $minMarketSignals = 3;  // mínimo señales reales de mercado
    $minTrendSignals  = 2;  // mínimo señales reales de tendencia

    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get();

    return $competencies->map(function ($comp) use (
        $range,
        $year,
        $quarter,
        $minMarketSignals,
        $minTrendSignals
    ) {

        /* ========================
           MERCADO (CONTEO REAL)
        ======================== */

        $marketCount =

            // Lenguajes
            DB::table('competency_course as cc')
                ->join('course_language as cl', 'cl.course_id', '=', 'cc.course_id')
                ->join('language_job as lj', 'lj.language_id', '=', 'cl.language_id')
                ->join('job_offers as jo', function ($join) use ($range) {
                    $join->on('jo.id', '=', 'lj.job_offer_id')
                         ->whereBetween('jo.published_at', [$range['start'], $range['end']]);
                })
                ->where('cc.competency_id', $comp->id)
                ->distinct('jo.id')
    ->count('jo.id');

            +

            // Tecnologías
            DB::table('competency_course as cc')
                ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
                ->join('technology_job as tj', 'tj.technology_id', '=', 'ct.technology_id')
                ->join('job_offers as jo', function ($join) use ($range) {
                    $join->on('jo.id', '=', 'tj.job_offer_id')
                         ->whereBetween('jo.published_at', [$range['start'], $range['end']]);
                })
                ->where('cc.competency_id', $comp->id)
                ->distinct('jo.id')
    ->count('jo.id');

            +

            // Metodologías
            DB::table('competency_course as cc')
                ->join('course_methodology as cm', 'cm.course_id', '=', 'cc.course_id')
                ->join('methodology_job as mj', 'mj.methodology_id', '=', 'cm.methodology_id')
                ->join('job_offers as jo', function ($join) use ($range) {
                    $join->on('jo.id', '=', 'mj.job_offer_id')
                         ->whereBetween('jo.published_at', [$range['start'], $range['end']]);
                })
                ->where('cc.competency_id', $comp->id)
                ->count();

       $marketMatch = $marketCount > 0;


        /* ========================
           TENDENCIAS (CONTEO REAL)
        ======================== */

        $trendCount =

            // Lenguajes
            DB::table('competency_course as cc')
                ->join('course_language as cl', 'cl.course_id', '=', 'cc.course_id')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('entity_trends as et', function ($join) use ($year, $quarter) {
                    $join->on('et.market_entity_id', '=', 'l.market_entity_id')
                         ->where('et.year', $year)
                         ->where('et.quarter', $quarter);
                })
                ->where('cc.competency_id', $comp->id)
                ->count()

            +

            // Tecnologías
            DB::table('competency_course as cc')
                ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('entity_trends as et', function ($join) use ($year, $quarter) {
                    $join->on('et.market_entity_id', '=', 't.market_entity_id')
                         ->where('et.year', $year)
                         ->where('et.quarter', $quarter);
                })
                ->where('cc.competency_id', $comp->id)
                ->count()

            +

            // Metodologías
            DB::table('competency_course as cc')
                ->join('course_methodology as cm', 'cm.course_id', '=', 'cc.course_id')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('entity_trends as et', function ($join) use ($year, $quarter) {
                    $join->on('et.market_entity_id', '=', 'm.market_entity_id')
                         ->where('et.year', $year)
                         ->where('et.quarter', $quarter);
                })
                ->where('cc.competency_id', $comp->id)
                ->count();

     $trendMatch  = $trendCount  > 0;


        /* ========================
           STATUS FINAL
        ======================== */

        $status = match (true) {
            $marketMatch && $trendMatch => 'aligned',
            $marketMatch || $trendMatch => 'partial',
            default => 'gap',
        };

        return [
            'id' => $comp->id,
            'name' => $comp->name,
            'market_match' => $marketMatch,
            'trend_match' => $trendMatch,
            'status' => $status,
        ];
    });
}

private function calculateCareerAlignmentSummary(
    \Illuminate\Support\Collection $competencies,
    float $laborWeight,
    float $trendWeight
): array
{
    $total = $competencies->count();

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

    /* =========================
       Conteos
    ========================== */

    $marketAligned = $competencies
        ->where('market_match', true)
        ->count();

    $trendAligned = $competencies
        ->where('trend_match', true)
        ->count();

    $gapCritico = $competencies
        ->where('market_match', false)
        ->where('trend_match', false)
        ->count();

    $gapMercado = $competencies
        ->where('market_match', false)
        ->where('trend_match', true)
        ->count();

    $gapTendencia = $competencies
        ->where('market_match', true)
        ->where('trend_match', false)
        ->count();

    /* =========================
       Tasas
    ========================== */

    $marketRate = $marketAligned / $total;
    $trendRate  = $trendAligned / $total;

    /* =========================
       Índice ponderado
    ========================== */

    $finalScore =
        ($laborWeight * $marketRate) +
        ($trendWeight * $trendRate);

    return [
        'total_competencies' => $total,

        'market_rate' => round($marketRate * 100, 1),
        'trend_rate'  => round($trendRate * 100, 1),

        'final_index' => round($finalScore * 100, 1),

        // GAPS estratégicos
        'gap_critico'    => $gapCritico,
        'gap_mercado'    => $gapMercado,
        'gap_tendencia'  => $gapTendencia,
    ];
}
 public function getCompetencyCourses(Request $request, int $competencyId)
{
    $careerId = (int) $request->get('career_id');
  $year = (int) $request->get('year');
if (!$careerId || !$year) {
    return response()->json([]);
}


    if (!$careerId) {
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

    $result = $related->map(function ($course) use ($courseStates) {

        $estado = $courseStates[$course->id]['estado'] ?? 'No alineado';

        return [
            'id'     => $course->id,
            'name'   => $course->name,
            'estado' => $estado,
        ];
    });

    return response()->json($result);
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

    private function getGlobalMeta(int $year, string $period): array
    {
        return [
            'year' => $year,
            'period' => $period,
            'periodo_label' => $period === 's1'
                ? "Semestre 1 – Enero a Junio {$year}"
                : "Semestre 2 – Julio a Diciembre {$year}",
            'vacantes_analizadas' => DB::table('job_offers')->count(),
            'reportes_analizados' => DB::table('entity_trends')->count(),
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
