<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\ScrapingStatusService;
use App\Http\Controllers\Dashboard\JobMarketStatusController;
use Illuminate\Support\Facades\Artisan;
use App\Exports\EntityEvolutionExport;
use App\Http\Controllers\Dashboard\Concerns\RefreshesEntityRanking;
use App\Services\Ranking\EntityRankingService;
use App\Services\TechnologyRankingService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class RankingTecnologiasController extends Controller
{
    use RefreshesEntityRanking;

    /* ==================================================
       GUARDAR PONDERACIONES (TECNOLOGÍAS)
    ================================================== */
   public function runGapDiscovery(Request $request)
{
    try {

        $limit = (int) $request->get('limit', 10);
        $sleep = (int) $request->get('sleep', 2);

        Artisan::call('technologies:discover-gaps', [
            '--limit' => $limit,
            '--sleep' => $sleep,
        ]);

        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Brechas tecnológicas detectadas correctamente.',
            'output'  => $output,
        ]);

    } catch (\Throwable $e) {

        Log::error('[TECH_GAP_RUN_ERROR]', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error ejecutando el motor IA de tecnologías.',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
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
/* ==================================================
   RANKING PRINCIPAL – TECNOLOGÍAS
   Lee SOLO de las tablas de resumen (technology_rankings / technology_ranking_meta),
   que llena `ranking:refresh-technologies`. No calcula nada en vivo (antes tardaba
   15-32 s y superaba el max_execution_time).

   Cambios de cálculo respecto de la versión en vivo:
   - el máximo de tendencias para normalizar sale solo de tecnologías (antes podía
     venir de otra entidad: IDC, un "lenguaje", con 163 reportes);
   - reportes_analizados cuenta solo reportes de tecnologías;
   - el filtro ranking_type se quitó: se leía pero nunca se aplicaba y la interfaz
     no ofrece cómo elegirlo.
================================================== */
public function index(Request $request)
{
    $rankingService = $this->rankingService();

    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1') === 's2' ? 's2' : 's1';
    $search = $request->get('search');

    $careers = $request->filled('career')
        ? array_values(array_filter((array) $request->career))
        : [];

    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    [$laborWeight, $trendWeight] = $rankingService->activeWeights();

    $meta = $rankingService->meta($year, $period);

    // Sin cálculo previo: paginador vacío y mensaje amigable en el frontend.
    $ranking = $meta
        ? $rankingService->paginate($year, $period, $laborWeight, $trendWeight, $careers, $search)
        : new LengthAwarePaginator([], 0, 10);

    $calculatedAt = $meta
        ? Carbon::parse($meta->calculated_at)->timezone('America/Lima')
        : null;

    return Inertia::render(
        'DashboardRankingTechnologies/RankingTecnologiasPage',
        [
            'ranking' => $ranking->withQueryString(),

            'filters' => [
                'year'   => $year,
                'period' => $period,
                'career' => $careers,
                'search' => $search,
            ],

            'availableCareers' => $availableCareers,

            'scrapingStatus' => ScrapingStatusService::getByEntity('technologies'),
            'jobMarketStatus'=> JobMarketStatusController::get($year, $period),

            'canRefreshRanking' => (bool) $request->user()?->can('administrar'),

            'weights' => [
                'laborWeight' => round($laborWeight * 100, 1),
                'trendWeight' => round($trendWeight * 100, 1),
            ],

            'meta' => [
                'year'   => $year,
                'period' => $period,
                'periodo_label' =>
                    $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",
                'vacantes_analizadas' => (int) ($meta->vacantes_analizadas ?? 0),
                'reportes_analizados' => (int) ($meta->reportes_analizados ?? 0),
                'has_data' => $meta !== null,
                // Fecha del último cálculo, en hora de Perú
                'calculated_at' => $calculatedAt?->toIso8601String(),
                'calculated_at_label' => $calculatedAt?->locale('es')->translatedFormat('d/m/Y h:i a'),
                'actualizado' => $calculatedAt?->toDateTimeString(),
            ],
        ]
    );
}

/* ==================================================
   BOTÓN "RECALCULAR RANKING": refresh() y refreshStatus() vienen del trait
   RefreshesEntityRanking (compartido con Lenguajes).
================================================== */
protected function rankingService(): EntityRankingService
{
    return app(TechnologyRankingService::class);
}



public function trendsByTechnology(Request $request, int $marketEntityId)
{
    $year    = (int) $request->get('year', 2026);
    $period  = $request->get('period', 's1');
$semester = $period === 's1' ? 1 : 2;
    $perPage = min((int) $request->get('per_page', 10), 50);

$trends = DB::table('entity_trends as et')
    ->where('et.market_entity_id', $marketEntityId)
    ->where('et.year', $year)
    ->whereIn(
        'et.quarter',
        $semester === 1
            ? [1, 2]
            : [3, 4]
    )
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
             'et.discovered_by',
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
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');
   $semester = $period === 's1' ? 1 : 2;

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
        'quarter'     => $semester,
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
   $semester = $period === 's1' ? 1 : 2;

    $perPage = min((int) $request->get('per_page', 10), 50);

   $paginator = DB::table('entity_trends')
    ->where('market_entity_id', $marketEntityId)
    ->where('year', $year)
    ->whereIn(
        'quarter',
        $semester === 1
            ? [1, 2]
            : [3, 4]
    )
    ->orderByDesc('trend_score')
        ->paginate(
            $perPage,
            [
                'id',
                'trend_score',
                'source_title',
                'source_url',
                'source_type',
                  'discovered_by',
                'created_at',
            ]
        );
$tavilyTotal = DB::table('entity_trends')
    ->where('market_entity_id', $marketEntityId)
    ->where('year', $year)
    ->whereIn(
        'quarter',
        $semester === 1
            ? [1, 2]
            : [3, 4]
    )
    ->where('discovered_by', 'LIKE', '%tavily%')
    ->count();

$gptTotal = DB::table('entity_trends')
    ->where('market_entity_id', $marketEntityId)
    ->where('year', $year)
    ->whereIn(
        'quarter',
        $semester === 1
            ? [1, 2]
            : [3, 4]
    )
    ->where('discovered_by', 'LIKE', '%gpt%')
    ->count();
  return response()->json([
    'data' => $paginator->items(),

    'stats' => [
        'tavily_total' => $tavilyTotal,
        'gpt_total' => $gptTotal,
    ],

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
/* ==================================================
   EVOLUCIÓN (MODAL): fotos del ranking semestral en cada corte
   Lee SOLO de technology_ranking_weekly(_meta), que llena ranking:refresh-technologies.
================================================== */
public function weeklyScores(Request $request)
{
    try {
        $year = (int) $request->get('year', now()->year);
        $filter = $request->get('filter', 'weekly');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, min((int) $request->get('per_page', 6), 20));

        if (! in_array($filter, EntityRankingService::WINDOW_TYPES, true)) {
            $filter = 'weekly';
        }

        $result = $this->rankingService()->windowsPage($year, $filter, $page, $perPage);

        return response()->json([
            'success'    => true,
            'filter'     => $filter,
            'year'       => $year,
            'data'       => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    } catch (\Throwable $e) {
        Log::error('[TECHNOLOGY_EVOLUTION_ERROR]', [
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'No se pudo cargar la evolución.',
        ], 500);
    }
}

/* Export en streaming: ~450 tecnologías × 45 semanas sin agotar memoria */
public function exportTechnologyEvolution(Request $request)
{
    $year = (int) $request->get('year', 2026);
    $filter = $request->get('filter', 'weekly');
    $filter = in_array($filter, EntityRankingService::WINDOW_TYPES, true) ? $filter : 'weekly';

    return (new EntityEvolutionExport($this->rankingService(), $year, $filter, 'Tecnología'))
        ->download("technology_evolution_{$filter}.xlsx");
}

}
