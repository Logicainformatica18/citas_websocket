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

    if (!$careerId) {
        return response()->json([
            'error' => 'career_id es requerido'
        ], 422);
    }

    /* ==================================================
       1️⃣ CONTEXTO BASE
    ================================================== */
    $competency = DB::table('competencies')
        ->where('id', $competencyId)
        ->first(['id', 'name', 'career_id']);

    if (!$competency || $competency->career_id !== $careerId) {
        return response()->json([
            'error' => 'Competencia no válida para la carrera'
        ], 404);
    }

    /* ==================================================
       2️⃣ BUSCAR ANÁLISIS YA GENERADO (CACHE)
    ================================================== */
    $cached = DB::table('competency_ai_analysis')
        ->where('competency_id', $competencyId)
        ->where('career_id', $careerId)
        ->where('year', $year)
        ->where('period', $period)
        ->first();

    if ($cached) {
        return response()->json([
            'source' => 'cache',
            'analysis' => [
                'diagnosis' => $cached->diagnosis,
                'recommendation' => $cached->recommendation,
                'generated_at' => $cached->generated_at,
            ],
        ]);
    }

    /* ==================================================
       3️⃣ LENGUAJES Y TECNOLOGÍAS
    ================================================== */
    $entities = DB::table('competency_market_entity as cme')
        ->join('market_entities as me', 'me.id', '=', 'cme.market_entity_id')
        ->where('cme.competency_id', $competencyId)
        ->select('me.entity_type', 'me.name')
        ->get();

    $languages = $entities
        ->where('entity_type', 'language')
        ->pluck('name')
        ->values()
        ->toArray();

    $technologies = $entities
        ->where('entity_type', 'technology')
        ->pluck('name')
        ->values()
        ->toArray();

    /* ==================================================
       4️⃣ FLAGS (YA CALCULADOS POR TU SISTEMA)
    ================================================== */
    $marketMatch = DB::table('competency_market_entity as cme')
        ->join('market_entities as me', 'me.id', '=', 'cme.market_entity_id')
        ->leftJoin('language_job as lj', 'lj.market_entity_id', '=', 'me.id')
        ->leftJoin('technology_job as tj', 'tj.market_entity_id', '=', 'me.id')
        ->where('cme.competency_id', $competencyId)
        ->exists();

    $trendMatch = DB::table('competency_market_entity as cme')
        ->join('entity_trends as et', 'et.market_entity_id', '=', 'cme.market_entity_id')
        ->where('cme.competency_id', $competencyId)
        ->exists();

    /* ==================================================
       5️⃣ IA – SOLO RECOMENDACIÓN
    ================================================== */
    $result = $aiService->analyze([
        'competency'   => $competency->name,
        'languages'    => $languages,
        'technologies' => $technologies,
        'market_match' => $marketMatch,
        'trend_match'  => $trendMatch,
    ]);

    /* ==================================================
       6️⃣ GUARDAR RESULTADO
    ================================================== */
    DB::table('competency_ai_analysis')->insert([
        'competency_id' => $competencyId,
        'career_id'     => $careerId,
        'year'          => $year,
        'period'        => $period,

        'market_match'  => $marketMatch,
        'trend_match'   => $trendMatch,

        'languages'     => json_encode($languages),
        'technologies'  => json_encode($technologies),

        'diagnosis'     => $result['diagnosis'] ?? '',
        'recommendation'=> $result['recommendation'] ?? '',

        'model'         => config('ai.model', 'gpt-4'),
        'generated_at'  => now(),
    ]);

    return response()->json([
        'source' => 'ai',
        'analysis' => [
            'diagnosis' => $result['diagnosis'] ?? '',
            'recommendation' => $result['recommendation'] ?? '',
            'generated_at' => now(),
        ],
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
                'meta' => $meta,
            ]
        );
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

        // 🔹 SOLO competencias evaluables
        $totalCompetencies = DB::table('competencies as c')
            ->where('c.career_id', $careerId)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('competency_market_entity as cme')
                  ->whereColumn('cme.competency_id', 'c.id');
            })
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
    ->where('c.career_id', $careerId)
    ->whereExists(function ($q) use ($range) {
        $q->select(DB::raw(1))
          ->from('competency_market_entity as cme')
          ->join('market_entities as me', 'me.id', '=', 'cme.market_entity_id')
          ->whereColumn('cme.competency_id', 'c.id')
          ->where(function ($qq) use ($range) {
              $qq->whereExists(function ($lq) use ($range) {
                  $lq->select(DB::raw(1))
                     ->from('language_job as lj')
                     ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
                     ->whereColumn('lj.market_entity_id', 'me.id')
                     ->whereBetween('j.published_at', [$range['start'], $range['end']]);
              })
              ->orWhereExists(function ($tq) use ($range) {
                  $tq->select(DB::raw(1))
                     ->from('technology_job as tj')
                     ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
                     ->whereColumn('tj.market_entity_id', 'me.id')
                     ->whereBetween('j.published_at', [$range['start'], $range['end']]);
              });
          });
    })
    ->count();


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
            ->join('competency_market_entity as cme', 'cme.competency_id', '=', 'c.id')
            ->join('entity_trends as et', 'et.market_entity_id', '=', 'cme.market_entity_id')
            ->where('c.career_id', $careerId)
            ->where('et.year', $year)
            ->where('et.quarter', $quarter)
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
            'reportes_analizados' => DB::table('entity_trends')
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

    $range = $this->getPeriodRange($period, $year);

    /* ==================================================
       1️⃣ COMPETENCIAS BASE
    ================================================== */
    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get(['id', 'name']);

    if ($competencies->isEmpty()) {
        return response()->json(['data' => []]);
    }

    $ids = $competencies->pluck('id')->all();

    /* ==================================================
       2️⃣ STACK ASOCIADO (LENGUAJES + TECNOLOGÍAS)
    ================================================== */
    $stack = DB::table('competency_market_entity as cme')
        ->join('market_entities as me', 'me.id', '=', 'cme.market_entity_id')
        ->whereIn('cme.competency_id', $ids)
        ->select(
            'cme.competency_id',
            'me.entity_type',
            'me.name'
        )
        ->get()
        ->groupBy('competency_id');

    /* ==================================================
       3️⃣ MARKET MATCH
    ================================================== */
    $marketLookup = DB::table('competency_market_entity as cme')
        ->join('market_entities as me', 'me.id', '=', 'cme.market_entity_id')
        ->leftJoin('language_job as lj', 'lj.market_entity_id', '=', 'me.id')
        ->leftJoin('technology_job as tj', 'tj.market_entity_id', '=', 'me.id')
        ->join('job_offers as j', function ($q) {
            $q->on('j.id', '=', DB::raw('COALESCE(lj.job_offer_id, tj.job_offer_id)'));
        })
        ->whereIn('cme.competency_id', $ids)
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct()
        ->pluck('cme.competency_id')
        ->flip();

    /* ==================================================
       4️⃣ TREND MATCH
    ================================================== */
    $trendLookup = DB::table('competency_market_entity as cme')
        ->join('entity_trends as et', 'et.market_entity_id', '=', 'cme.market_entity_id')
        ->whereIn('cme.competency_id', $ids)
        ->where('et.year', $year)
        ->where('et.quarter', $quarter)
        ->distinct()
        ->pluck('cme.competency_id')
        ->flip();

    /* ==================================================
       5️⃣ ARMADO FINAL PARA CARDS
    ================================================== */
 /* ==================================================
   5️⃣ ARMADO FINAL + ORDEN SEMÁNTICO
================================================== */
$data = $competencies
    ->map(function ($c) use ($stack, $marketLookup, $trendLookup) {

        $entities = $stack[$c->id] ?? collect();

        $languages = $entities
            ->where('entity_type', 'language')
            ->pluck('name')
            ->values();

        $technologies = $entities
            ->where('entity_type', 'technology')
            ->pluck('name')
            ->values();

        $market = isset($marketLookup[$c->id]);
        $trend  = isset($trendLookup[$c->id]);

        // 🔥 ORDEN SEMÁNTICO
        // 1 = alineada, 2 = parcial, 3 = gap
        if ($market && $trend) {
            $order = 1;
        } elseif ($market || $trend) {
            $order = 2;
        } else {
            $order = 3;
        }

        return [
            'id' => $c->id,
            'name' => $c->name,

            'languages' => $languages,
            'technologies' => $technologies,

            'market_match' => $market,
            'trend_match' => $trend,

            'order' => $order, // 👈 clave
        ];
    })
    // 🔥 ORDEN FINAL: alineadas → parciales → GAP
    ->sortBy([
        ['order', 'asc'],
        ['name', 'asc'],
    ])
    ->values();


    return response()->json([
        'data' => $data,
    ]);
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
