<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\AI\CompetencyRecommendationService;

class PeAlignmentIndicatorController extends Controller
{
/* =====================================================
   IA – RECOMENDACIÓN POR COMPETENCIA
===================================================== */


public function analyzeCompetencyWithAI(
    Request $request,
    int $competencyId,
    CompetencyRecommendationService $aiService
) {
    $careerId = (int) $request->get('career_id');
    $year     = (int) $request->get('year', 2026);
    $period   = $request->get('period', 's1');
    $quarter  = $period === 's1' ? 1 : 4;

    if (!$careerId) {
        return response()->json(['error' => 'career_id es requerido'], 422);
    }

    $competency = DB::table('competencies')
        ->where('id', $competencyId)
        ->where('career_id', $careerId)
        ->first(['id', 'name', 'description_en']);

    if (!$competency) {
        return response()->json(['error' => 'Competencia no válida'], 404);
    }

    $range = $this->getPeriodRange($period, $year);

    /* ===============================
       MARKET MATCH
    =============================== */

    $jobCount = DB::table('competency_job_offer as cjo')
        ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
        ->where('cjo.competency_id', $competencyId)
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('j.id')
        ->count('j.id');

    $marketMatch = $jobCount > 0;

    /* ===============================
       TREND MATCH
    =============================== */

    $trendCount = DB::table('competency_trends')
        ->where('competency_id', $competencyId)
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->count();

    $trendMatch = $trendCount > 0;

    $result = $aiService->analyze([
        'competency'   => $competency->description_en,
        'market_match' => $marketMatch,
        'trend_match'  => $trendMatch,
    ]);

    return response()->json([
        'source' => 'ai',
        'analysis' => $result
    ]);
}


    /* =====================================================
       INDEX
    ===================================================== */
 public function index(Request $request)
{
    [
        $careerId,
        $year,
        $period,
        $quarter,
        $range
    ] = $this->resolveParams($request);

    [$laborWeight, $trendWeight] = $this->getWeights();

    $availableCareers = $this->getAvailableCareers();
    $meta = $this->getGlobalMeta($range, $year, $period);

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

    $summary = $this->getCareerSummary(
        $careerId,
        $year,
        $quarter,
        $range,
        $laborWeight,
        $trendWeight
    );

    $competencies = $this->getDetailedCompetencies(
        $careerId,
        $year,
        $quarter,
        $range
    );

    return Inertia::render(
        'DashboardAlignCompetence/PeAlignmentIndicatorPage',
        [
            'filters' => [
                'career_id' => $careerId,
                'year' => $year,
                'period' => $period,
            ],
            'availableCareers' => $availableCareers,
            'weights' => [
                'laborWeight'  => round($laborWeight * 100, 1),
                'trendsWeight' => round($trendWeight * 100, 1),
            ],
            'summary' => $summary,
            'competencies' => $competencies,
            'meta' => $meta,
        ]
    );
}
private function getDetailedCompetencies(
    int $careerId,
    int $year,
    int $quarter,
    array $range
) {

    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get();

    return $competencies->map(function ($c) use ($year, $quarter, $range) {

        /* ====================================================
           JOB COUNT (SOLO DIRECTO A COMPETENCIA)
        ==================================================== */

        $jobCount = DB::table('competency_job_offer as cjo')
            ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
            ->where('cjo.competency_id', $c->id)
            ->whereNotNull('j.published_at')
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->distinct()
            ->count('j.id');


        /* ====================================================
           TREND COUNT (DIRECTO)
        ==================================================== */

        $trendCount = DB::table('competency_trends')
            ->where('competency_id', $c->id)
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->count();


        $marketMatch = $jobCount > 0;
        $trendMatch  = $trendCount > 0;

        $status = match (true) {
            $marketMatch && $trendMatch => 'aligned',
            $marketMatch || $trendMatch => 'partial',
            default => 'gap',
        };

        return [
            'id' => $c->id,
            'name' => $c->name,

            'job_count' => $jobCount,
            'trend_count' => $trendCount,

            'market_match' => $marketMatch,
            'trend_match' => $trendMatch,
            'status' => $status,
        ];
    })
    ->sortByDesc('job_count')
    ->values();
}




    /* =====================================================
       SUMMARY POR CARRERA
    ===================================================== */
  private function getCareerSummary(
    int $careerId,
    int $year,
    int $quarter,
    array $range,
    float $laborWeight,
    float $trendWeight
): array {

    $totalCompetencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->count();

    [$marketMatched, $marketPct] = $this->getMarketStats(
        $careerId,
        $range,
        $totalCompetencies
    );

    [$trendMatched, $trendPct] = $this->getTrendStats(
        $careerId,
        $year,
        $quarter,
        $totalCompetencies
    );

    $finalIndex = round(
        ($laborWeight * $marketPct) +
        ($trendWeight * $trendPct),
        2
    );

    return [
        'total_competencies' => $totalCompetencies,
        'market' => [
            'matched' => $marketMatched,
            'percentage' => $marketPct,
        ],
        'prospective' => [
            'matched' => $trendMatched,
            'percentage' => $trendPct,
        ],
        'final_index' => $finalIndex,
    ];
}


    /* =====================================================
       MERCADO (via language_job / technology_job)
    ===================================================== */
  private function getMarketStats(
    int $careerId,
    array $range,
    int $total
): array {

    $matched = DB::table('competencies as c')
        ->join('competency_job_offer as cjo', 'cjo.competency_id', '=', 'c.id')
        ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
        ->where('c.career_id', $careerId)
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('c.id')
        ->count('c.id');

    $pct = $total > 0
        ? round(($matched / $total) * 100, 2)
        : 0;

    return [$matched, $pct];
}



    /* =====================================================
       TENDENCIAS (entity_trends)
    ===================================================== */
 private function getTrendStats(
    int $careerId,
    int $year,
    int $quarter,
    int $total
): array {

    $matched = DB::table('competencies as c')
        ->join('competency_trends as ct', function ($join) use ($year, $quarter) {
            $join->on('ct.competency_id', '=', 'c.id')
                 ->where('ct.year', $year)
                 ->where('ct.quarter', $quarter);
        })
        ->where('c.career_id', $careerId)
        ->distinct('c.id')
        ->count('c.id');

    $pct = $total > 0
        ? round(($matched / $total) * 100, 2)
        : 0;

    return [$matched, $pct];
}



    /* =====================================================
       HELPERS COMUNES
    ===================================================== */
    private function resolveParams(Request $request): array
    {
        $careerId = (int) $request->get('career_id');
        $year     = (int) $request->get('year', 2026);
        $period   = $request->get('period', 's1');
        $quarter  = $period === 's1' ? 1 : 4;

        return [
            $careerId,
            $year,
            $period,
            $quarter,
            $this->getPeriodRange($period, $year)
        ];
    }

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

    private function getGlobalMeta(array $range, int $year, string $period): array
    {
        return [
            'year' => $year,
            'period' => $period,
            'periodo_label' => $period === 's1'
                ? "Semestre 1 – Enero a Junio {$year}"
                : "Semestre 2 – Julio a Diciembre {$year}",
            'vacantes_analizadas' => DB::table('job_offers')
                ->whereBetween('published_at', [$range['start'], $range['end']])
                ->count(),
            'reportes_analizados' => DB::table('competency_trends')
    ->where('year', $year)
    ->where('quarter', $period === 's1' ? 1 : 4)
    ->count(),
            'actualizado' => now()->toDateTimeString(),
        ];
    }

    private function getPeriodRange(string $period, int $year): array
    {
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
public function competenciesByCareer(Request $request, int $careerId)
{
    $year    = (int) $request->get('year', 2026);
    $period  = $request->get('period', 's1');
    $quarter = $period === 's1' ? 1 : 4;
    $range   = $this->getPeriodRange($period, $year);

    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get(['id', 'name']);

    if ($competencies->isEmpty()) {
        return response()->json(['data' => []]);
    }

    $data = $competencies->map(function ($c) use ($year, $quarter, $range) {

        $jobCount = DB::table('competency_job_offer as cjo')
            ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
            ->where('cjo.competency_id', $c->id)
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->distinct('j.id')
            ->count('j.id');

        $trendCount = DB::table('competency_trends')
            ->where('competency_id', $c->id)
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->count();

        $market = $jobCount > 0;
        $trend  = $trendCount > 0;

        $order = $market && $trend ? 1 : ($market || $trend ? 2 : 3);

        return [
            'id' => $c->id,
            'name' => $c->name,
            'job_count' => $jobCount,
            'trend_count' => $trendCount,
            'market_match' => $market,
            'trend_match' => $trend,
            'order' => $order,
        ];
    })
    ->sortBy([['order','asc'], ['name','asc']])
    ->values();

    return response()->json(['data' => $data]);
}




    private function renderEmpty($careers, $meta, $lw, $tw, $year, $period)
    {
        return Inertia::render(
            'DashboardAlignCompetence/PeAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => null,
                    'year' => $year,
                    'period' => $period,
                ],
                'availableCareers' => $careers,
                'weights' => [
                    'laborWeight'  => round($lw * 100, 1),
                    'trendsWeight' => round($tw * 100, 1),
                ],
                'summary' => null,
                'meta' => $meta,
            ]
        );
    }
}
