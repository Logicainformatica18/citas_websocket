<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\ScrapingStatusService;
use App\Http\Controllers\Dashboard\JobMarketStatusController;


class RankingTecnologiasController extends Controller
{
    /* ==================================================
       GUARDAR PONDERACIONES (TECNOLOGÍAS)
    ================================================== */
    public function storeWeights(Request $request)
    {
        $data = $request->validate([
            'labor_weight' => 'required|numeric|min:0|max:1',
            'trend_weight' => 'required|numeric|min:0|max:1',
        ]);

        if (round($data['labor_weight'] + $data['trend_weight'], 2) !== 1.00) {
            return response()->json([
                'message' => 'Las ponderaciones deben sumar 1.00',
            ], 422);
        }

        DB::transaction(function () use ($data) {

            Prueba::where('context', 'technologies')
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            Prueba::create([
                'labor_weight' => $data['labor_weight'],
                'trend_weight' => $data['trend_weight'],
                'context'      => 'technologies',
                'is_active'    => 1,
                'applied_at'   => now(),
                'updated_by'   => auth()->id(),
            ]);
        });

        return redirect()->back();
    }

    /* ==================================================
       RANKING PRINCIPAL
    ================================================== */
public function index(Request $request)
{
    /* ==================================================
       0. CONTEXTO BASE
    ================================================== */
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');
    $quarter = $period === 's1' ? 1 : 4;

    $range = $this->getPeriodRange($period, $year);

    /* ==================================================
       0.1 FILTROS (INERTIA SAFE)
    ================================================== */
    $careers = $request->filled('career')
        ? (array) $request->career
        : [];

    $rankingType = $request->get('ranking_type', 'all');
    if (!in_array($rankingType, ['all', 'technology', 'trend'])) {
        $rankingType = 'all';
    }

    /* ==================================================
       0.2 PONDERACIONES
    ================================================== */
    try {
        $weights = Prueba::getActive('technologies');
    } catch (\Throwable $e) {
        $weights = null;
    }

    $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
    $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

    /* ==================================================
       0.3 CATÁLOGOS
    ================================================== */
    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    /* ==================================================
       1. DEMANDA LABORAL (technology_job)
    ================================================== */
    $laborSub = DB::table('technology_job as tj')
        ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->groupBy('tj.market_entity_id')
        ->select(
            'tj.market_entity_id',
            DB::raw('COUNT(DISTINCT tj.job_offer_id) as offers')
        );

    $maxLabor = max(
        DB::query()->fromSub($laborSub, 'x')->max('offers'),
        1
    );

    /* ==================================================
       2. TENDENCIAS REALES (entity_trends)
    ================================================== */
    $trendSub = DB::table('entity_trends as et')
        ->where('et.year', $year)
        ->where('et.quarter', $quarter)
        ->groupBy('et.market_entity_id')
        ->select(
            'et.market_entity_id',
            DB::raw('COUNT(et.id) as trend_reports'),
            DB::raw('AVG(et.trend_score) as avg_trend_score')
        );

    $maxTrendReports = max(
        DB::query()->fromSub($trendSub, 't')->max('trend_reports'),
        1
    );

    /* ==================================================
       3. QUERY BASE (market_entities)
    ================================================== */
    $query = DB::table('market_entities as me')
        ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
        ->leftJoinSub($trendSub, 'trends', 'trends.market_entity_id', '=', 'me.id')
        ->where('me.entity_type', 'technology');

    /* ==================================================
       4. FILTRO POR CARRERA (career_market_entity)
    ================================================== */
    if (!empty($careers)) {
        $query->whereExists(function ($q) use ($careers) {
            $q->select(DB::raw(1))
              ->from('career_market_entity as cme')
              ->join('careers as c', 'c.id', '=', 'cme.career_id')
              ->whereColumn('cme.market_entity_id', 'me.id')
              ->whereIn('c.slug', $careers);
        });
    }

    /* ==================================================
       5. SELECT + SCORES (NORMALIZADOS CORRECTOS)
    ================================================== */
    $ranking = $query->select(
        DB::raw("'technology' as entity_type"),
        'me.id',
        'me.name',
        'me.has_isil',
        'me.has_trend',

        DB::raw("
            CASE
                WHEN me.has_isil = 1 AND me.has_trend = 1 THEN 'isil+trend'
                WHEN me.has_isil = 1 THEN 'isil'
                WHEN me.has_trend = 1 THEN 'trend'
                ELSE 'market'
            END as classification
        "),

        DB::raw('COALESCE(labor.offers,0) as total_jobs'),
        DB::raw('COALESCE(trends.trend_reports,0) as trend_reports'),

        // 🔵 SCORE LABORAL (log)
        DB::raw("
            ROUND(
                (LOG(COALESCE(labor.offers,0)+1)
                 / LOG({$maxLabor}+1)) * 100,
            1) as labor_score
        "),

        // 🟣 SCORE TENDENCIAS (log, NO global)
        DB::raw("
            ROUND(
                (LOG(COALESCE(trends.trend_reports,0)+1)
                 / LOG({$maxTrendReports}+1)) * 100,
            1) as trend_score
        "),

        // ⭐ SCORE FINAL
        DB::raw("
            ROUND(
                (
                    (
                        (LOG(COALESCE(labor.offers,0)+1)
                         / LOG({$maxLabor}+1)) * 100 * {$laborWeight}
                    )
                  +
                    (
                        (LOG(COALESCE(trends.trend_reports,0)+1)
                         / LOG({$maxTrendReports}+1)) * 100 * {$trendWeight}
                    )
                ),
            1) as final_score
        ")
    )
    ->orderByDesc('final_score')
    ->paginate(10)
    ->withQueryString();

    /* ==================================================
       6. METADATA
    ================================================== */
    $totalVacantesAnalizadas = DB::table('technology_job as tj')
        ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('tj.job_offer_id')
        ->count('tj.job_offer_id');

    $totalReports = DB::table('entity_trends')
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->count();

    /* ==================================================
       7. RENDER
    ================================================== */
    return Inertia::render(
        'DashboardRankingTechnologies/RankingTecnologiasPage',
        [
            'ranking' => $ranking,

            'filters' => [
                'year'   => $year,
                'period'=> $period,
                'career'=> $careers,
                'ranking_type' => $rankingType !== 'all' ? $rankingType : null,
            ],

            'availableCareers' => $availableCareers,

            'scrapingStatus' => ScrapingStatusService::getByEntity('technologies'),
            'jobMarketStatus'=> JobMarketStatusController::get($year, $period),

            'weights' => [
                'laborWeight' => round($laborWeight * 100, 1),
                'trendWeight' => round($trendWeight * 100, 1),
            ],

            'meta' => [
                'year'   => $year,
                'period'=> $period,
                'periodo_label' =>
                    $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",
                'vacantes_analizadas' => $totalVacantesAnalizadas,
                'reportes_analizados' => $totalReports,
                'actualizado' => now()->toDateTimeString(),
            ],
        ]
    );
}



public function trendsByTechnology(Request $request, int $marketEntityId)
{
    $year    = (int) $request->get('year', 2026);
    $period  = $request->get('period', 's1');
    $quarter = $period === 's1' ? 1 : 4;

    $perPage = min((int) $request->get('per_page', 10), 50);

    $trends = DB::table('entity_trends as et')
        ->where('et.market_entity_id', $marketEntityId)
        ->where('et.year', $year)
        ->where('et.quarter', $quarter)
        ->orderByDesc('et.trend_score')
        ->select(
            'et.id',
            'et.topic_name',
            'et.trend_score',
            'et.year',
            'et.quarter',
            'et.source_title',
            'et.source_url',
            'et.source_type',
            'et.created_at'
        )
        ->paginate($perPage);

    return response()->json([
        'data' => $trends->items(),
        'pagination' => [
            'current_page' => $trends->currentPage(),
            'last_page'    => $trends->lastPage(),
            'per_page'     => $trends->perPage(),
            'total'        => $trends->total(),
        ],
    ]);
}


private function getBaseContext(Request $request): array
{
    /* ==================================================
       AÑO / PERIODO
    ================================================== */
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    /* ==================================================
       RANGO DE FECHAS
    ================================================== */
    $range = $this->getPeriodRange($period, $year);

    /* ==================================================
       NORMALIZAR FILTROS (🔥 CLAVE INERTIA)
    ================================================== */
    $categories = $request->input('category');
    $categories = $categories
        ? (is_array($categories) ? $categories : [$categories])
        : [];

    $careers = $request->input('career');
    $careers = $careers
        ? (is_array($careers) ? $careers : [$careers])
        : [];

    $rankingType = $request->get('ranking_type', 'all');
    if (!in_array($rankingType, ['all', 'technology', 'trend'])) {
        $rankingType = 'all';
    }

    /* ==================================================
       PONDERACIONES ACTIVAS
    ================================================== */
    try {
        $weights = Prueba::getActive('technologies');
    } catch (\Throwable $e) {
        $weights = null;
    }

    $laborWeight = (float) ($weights?->labor_weight ?? 0.70);
    $trendWeight = (float) ($weights?->trend_weight ?? 0.30);

    /* ==================================================
       CONTEXTO FINAL
    ================================================== */
    return [
        'year'        => $year,
        'period'      => $period,
        'quarter'     => $quarter,
        'range'       => $range,

        // filtros
        'categories'  => $categories,
        'careers'     => $careers,
        'rankingType' => $rankingType,

        // pesos
        'laborWeight' => $laborWeight,
        'trendWeight' => $trendWeight,
    ];
}
public function reportsByTechnology(Request $request, int $marketEntityId)
{
    $year    = (int) $request->get('year', 2026);
    $period  = $request->get('period', 's1');
    $quarter = $period === 's1' ? 1 : 4;

    $perPage = min((int) $request->get('per_page', 10), 50);

    $paginator = DB::table('entity_trends')
        ->where('market_entity_id', $marketEntityId)
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->orderByDesc('trend_score')
        ->paginate(
            $perPage,
            [
                'id',
                'trend_score',
                'source_title',
                'source_url',
                'source_type',
                'created_at',
            ]
        );

    return response()->json([
        // 👇 DATA PLANA (clave para React)
        'data' => $paginator->items(),

        // 👇 PAGINACIÓN SEPARADA
        'pagination' => [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'prev_page_url'=> $paginator->previousPageUrl(),
            'next_page_url'=> $paginator->nextPageUrl(),
        ],
    ]);
}

 

public function technologyTrendDetail(Request $request, int $trendId)
{
    $trend = DB::table('technology_trends')
        ->where('id', $trendId)
        ->select(
            'id',
            'topic_name',
            'trend_score',
            'year',
            'quarter',
            'source_title',
            'source_url',
            'source_type',
            'raw_data'
        )
        ->first();

    return response()->json([
        'data' => $trend,
    ]);
}
public function jobsByTechnologyTrend(Request $request, int $trendId)
{
    $perPage = min((int) $request->get('per_page', 10), 50);

    $jobs = DB::table('job_offers as j')
        ->join('technology_trend_job as ttj', 'ttj.job_offer_id', '=', 'j.id')
        ->where('ttj.technology_trend_id', $trendId)
        ->select(
            'j.id',
            'j.title',
            'j.company',
            'j.location',
            'j.country',
            'j.modality',
            'j.salary_min',
            'j.salary_max',
            'j.source',
            'j.published_at',
            'j.url'
        )
        ->orderByDesc('j.published_at')
        ->paginate($perPage);

    return response()->json([
        'data' => $jobs,
    ]);
}


    /* ==================================================
       JOBS POR TECNOLOGÍA
    ================================================== */
   public function jobsByTechnology(Request $request, int $technologyId)
{
    $perPage = min((int) $request->get('per_page', 10), 50);

    $jobs = DB::table('job_offers as j')
        ->join('technology_job as tj', 'tj.job_offer_id', '=', 'j.id')
        ->where('tj.market_entity_id', $technologyId)
        ->orderByDesc('j.published_at')
        ->select(
            'j.id',
            'j.title',
            'j.company',
            'j.location',
            'j.country',
            'j.modality',
            'j.salary_min',
            'j.salary_max',
            'j.source',
            'j.published_at',
            'j.url'
        )
        ->paginate($perPage);

    return response()->json($jobs);
}

public function jobsByLanguage(Request $request, int $languageId)
{
    $perPage = min((int) $request->get('per_page', 10), 50);
    $page    = (int) $request->get('page', 1);

    $jobs = DB::table('job_offers as j')
        ->join('language_job as lj', 'lj.job_offer_id', '=', 'j.id')
        ->where('lj.language_id', $languageId)
        ->select(
            'j.id',
            'j.title',
            'j.company',
            'j.location',
            'j.country',
            'j.modality',
            'j.salary_min',
            'j.salary_max',
            'j.source',
            'j.published_at',
            'j.url'
        )
        ->orderByDesc('j.published_at')
        ->paginate($perPage, ['*'], 'page', $page);

    return response()->json($jobs);
}

    /* ==================================================
       UTILIDADES
    ================================================== */
    private function getPeriodRange(string $period, int $year): array
    {
        if ($period === 's1') {
            return [
                'start' => "$year-01-01",
                'end'   => "$year-06-30",
            ];
        }

        return [
            'start' => "$year-07-01",
            'end'   => "$year-12-31",
        ];
    }


}
