<?php

namespace App\Http\Controllers\Dashboard;

use AppServicesIndicatorsIndicatorScope;
use App\Services\Indicators\IndicatorScope;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\ScrapingStatusService;
use App\Http\Controllers\Dashboard\JobMarketStatusController;
use App\Http\Controllers\Dashboard\Concerns\RefreshesEntityRanking;
use App\Services\LanguageRankingService;
use App\Services\Ranking\EntityRankingService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EntityEvolutionExport;
use Illuminate\Support\Facades\Log;

class RankingLenguajesController extends Controller
{
    use RefreshesEntityRanking;

public function run(Request $request)
{
    try {

        $limit = (int) $request->get('limit', 10);
        $sleep = (int) $request->get('sleep', 2);

        Artisan::call('languages:discover-gaps', [
            '--limit' => $limit,
            '--sleep' => $sleep,
        ]);

        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Brechas detectadas correctamente.',
            'output'  => $output,
        ]);

    } catch (\Throwable $e) {

        Log::error('[LANGUAGE_GAP_RUN_ERROR]', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error ejecutando el motor IA.',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
    /* ==================================================
       GUARDAR PONDERACIONES (LANGUAGES)
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
            Prueba::where('context', 'languages')
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            Prueba::create([
                'labor_weight' => $data['labor_weight'],
                'trend_weight' => $data['trend_weight'],
                'context' => 'languages',
                'is_active' => 1,
                'applied_at' => now(),
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back();
    }

    /* ==================================================
       RANKING PRINCIPAL – LANGUAGES
    ================================================== */
//     public function reportsByLanguage(Request $request, int $marketEntityId)
// {
//     $year   = (int) $request->get('year', 2026);
//     $period = $request->get('period', 's1');
//     $range  = $this->getPeriodRange($period, $year);

//     $reports = DB::table('entity_trends')
//         ->where('market_entity_id', $marketEntityId)
//         ->whereBetween('created_at', [
//             $range['start'],
//             $range['end'],
//         ])
//         ->select(
//             'id',
//             'trend_name',
//             'trend_score',
//             'source_title',
//             'source_url',
//             'source_type',
//             'created_at'
//         )
//         ->orderByDesc('trend_score')
//         ->paginate(
//             min((int) $request->get('per_page', 10), 50)
//         );

//     return response()->json([
//         'data' => $reports,
//     ]);
// }

/* ==================================================
   RANKING PRINCIPAL – LANGUAGES
   Lee SOLO de las tablas de resumen (language_rankings / language_ranking_meta),
   que llena `ranking:refresh-languages`. No calcula nada en vivo.
================================================== */
public function index(Request $request, LanguageRankingService $rankingService)
{
    $career = $request->input('career');
    $search = $request->get('search');
    $career = $career
        ? (is_array($career) ? $career : [$career])
        : [];

    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1') === 's2' ? 's2' : 's1';

    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    [$laborWeight, $trendWeight] = $rankingService->activeWeights();

    $meta = $rankingService->meta($year, $period);

    // Sin cálculo previo: paginador vacío y mensaje amigable en el frontend.
    $ranking = $meta
        ? $rankingService->paginate($year, $period, $laborWeight, $trendWeight, $career, $search)
        : new LengthAwarePaginator([], 0, 10);

    $calculatedAt = $meta
        ? Carbon::parse($meta->calculated_at)->timezone('America/Lima')
        : null;

    return Inertia::render(
        'DashboardRankingLanguages/RankingLenguajesPage',
        [
            'ranking' => $ranking->withQueryString(),

            'filters' => [
                'year'   => $year,
                'period' => $period,
                'career' => $career, // 🔥 CLAVE
                'search' => $search,
            ],

            'weights' => [
                'laborWeight'  => round($laborWeight * 100, 1),
                'trendWeight'  => round($trendWeight * 100, 1),
            ],

            'scrapingStatus' => ScrapingStatusService::getByEntity('languages'),

            'jobMarketStatus' => JobMarketStatusController::get($year, $period),

            'availableCareers' => $availableCareers,

            'canRefreshRanking' => (bool) $request->user()?->can('administrar'),

            'meta' => [
                'year' => $year,
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
   RefreshesEntityRanking (compartido con Tecnologías).
================================================== */
protected function rankingService(): EntityRankingService
{
    return app(LanguageRankingService::class);
}

/* ==================================================
   JOBS POR LENGUAJE EN TENDENCIA (MODAL LABORAL)
================================================== */



    /* ==================================================
       JOBS POR LENGUAJE (MODAL LABORAL)
    ================================================== */

    /* ==================================================
       REPORTES / TENDENCIAS POR LENGUAJE
    ================================================== */

public function runLanguageGapDiscovery(Request $request)
{
    try {

        $limit = (int) $request->get('limit', 10);
        $sleep = (int) $request->get('sleep', 5);

        Artisan::call('languages:discover-gaps', [
            '--limit' => $limit,
            '--sleep' => $sleep,
        ]);

        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Proceso ejecutado correctamente',
            'output'  => $output,
        ]);

    } catch (\Throwable $e) {

        Log::error('RUN_LANGUAGE_GAP_ERROR', [
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error ejecutando el comando',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
    /* ==================================================
       UTILIDADES
    ================================================== */
   public function jobsByLanguage(Request $request, int $marketEntityId)
{
    $perPage = min((int) $request->get('per_page', 10), 50);
    $page    = (int) $request->get('page', 1);

    // Mismo criterio que el ranking: sin ofertas excluidas por título ni asociaciones excluidas
    $paginator = IndicatorScope::associations(IndicatorScope::offers(DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id'), 'j'), 'language_job', 'lj')
        ->where('lj.market_entity_id', $marketEntityId)
        ->orderByDesc('j.published_at')
        ->paginate(
            $perPage,
            [
                'j.id',
                'j.title',
                'j.company',
                'j.city as location',
                'j.country',
                'j.modality',
                'j.source',
                'j.published_at',
                'j.url',
            ],
            'page',
            $page
        );

    return response()->json([
        'data'         => $paginator->items(),
        'current_page'=> $paginator->currentPage(),
        'last_page'   => $paginator->lastPage(),
        'total'       => $paginator->total(),
    ]);
}


    private function getPeriodRange(string $period, int $year): array
    {
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }

    /* ==================================================
   DETALLE DE TENDENCIA (MODAL – LENGUAJES)
================================================== */



private function getLanguagesRanking(array $ctx)
{
    /* ==================================================
       1. SUBQUERY: TENDENCIAS
    ================================================== */
    $reportsSub = $this->getDirectLanguageTrendsSubquery(
        $ctx['range']
    );

    /* ==================================================
       2. SUBQUERY: LABORAL
    ================================================== */
    $laborSub = DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
        ->whereBetween('j.published_at', [
            $ctx['range']['start'],
            $ctx['range']['end'],
        ])
        ->select(
            'lj.market_entity_id',
            DB::raw('COUNT(DISTINCT lj.job_offer_id) as offers')
        )
        ->groupBy('lj.market_entity_id');

    /* ==================================================
       3. NORMALIZADORES
    ================================================== */
    $maxLabor = max(
        DB::query()->fromSub($laborSub, 'x')->max('offers'),
        1
    );

    $maxTrend = max(
        DB::query()->fromSub($reportsSub, 'r')->max('report_mentions'),
        1
    );

    $totalReports = max(
        $this->getTrendReportsCountByRange($ctx['range']),
        1
    );

    /* ==================================================
       4. QUERY PRINCIPAL
    ================================================== */
    $query = DB::table('market_entities as me')
        ->leftJoinSub($laborSub, 'labor', function ($j) {
            $j->on('labor.market_entity_id', '=', 'me.id');
        })
        ->leftJoinSub($reportsSub, 'reports', function ($j) {
            $j->on('reports.language_id', '=', 'me.id');
        })
        ->where('me.entity_type', 'language');

    /* ==================================================
       5. FILTRO CARRERAS
    ================================================== */
    if (!empty($ctx['careers'])) {
        $query->whereExists(function ($q) use ($ctx) {
            $q->select(DB::raw(1))
              ->from('market_entity_career as mec')
              ->join('careers as ca', 'ca.id', '=', 'mec.career_id')
              ->whereColumn('mec.market_entity_id', 'me.id')
              ->whereIn('ca.slug', $ctx['careers']);
        });
    }

    /* ==================================================
       6. SELECT FINAL
    ================================================== */
    return $query->select(
        DB::raw("'language' as entity_type"),
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
        DB::raw('COALESCE(reports.report_mentions,0) as trend_reports'),

    DB::raw("
ROUND(
(LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100,
1) as labor_score
"),


      DB::raw("
    ROUND(
      (LOG(COALESCE(reports.report_mentions,0)+1) / LOG({$maxTrend}+1)) * 100,
    1) as trend_score
"),


        DB::raw("
            ROUND(
                (
                  (LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100 * {$ctx['laborWeight']}
+
(LOG(COALESCE(reports.report_mentions,0)+1) / LOG({$maxTrend}+1)) * 100 * {$ctx['trendWeight']}
                ),
            1) as final_score
        ")
    )
    ->get()
    ->sortByDesc('final_score')
    ->values();
}
private function getDirectLanguageTrendsSubquery(array $range)
{
    return IndicatorScope::trends(DB::table('entity_trends as et'), 'et')
        ->join('market_entities as me', function ($j) {
            $j->on('me.id', '=', 'et.market_entity_id')
              ->where('me.entity_type', 'language');
        })
        ->whereBetween('et.created_at', [
            $range['start'],
            $range['end'],
        ])
        ->select(
            'me.id as language_id',
            DB::raw('COUNT(DISTINCT et.id) as report_mentions')
        )
        ->groupBy('me.id');
}

private function getTrendReportsCountByRange(array $range): int
{
    return IndicatorScope::trends(DB::table('entity_trends as et'), 'et')
        ->join('market_entities as me', function ($j) {
            $j->on('me.id', '=', 'et.market_entity_id')
              ->where('me.entity_type', 'language');
        })
        ->whereBetween('et.created_at', [
            $range['start'],
            $range['end'],
        ])
        ->count('et.id');
}

private function paginate($items, int $perPage)
{
    $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();

    $items = $items->values();

    return new \Illuminate\Pagination\LengthAwarePaginator(
        $items->slice(($page - 1) * $perPage, $perPage)->values(),
        $items->count(),
        $perPage,
        $page,
        [
            'path' => request()->url(),
            'query' => request()->query(),
        ]
    );
}
public function trendsByLanguage(
    Request $request,
    int $marketEntityId
) {
    $perPage = min(
        (int) $request->get('per_page', 10),
        50
    );

    $year = (int) $request->get('year', 2026);

    $period = $request->get('period', 's1');

    $semester =
        $period === 's1'
            ? 1
            : 2;

    /*
    ==================================================
    📦 REPORTES PAGINADOS
    ==================================================
    */

    $trends = IndicatorScope::trends(DB::table('entity_trends'))

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

                // 👇 IMPORTANTE
                'discovered_by',

                'created_at',
            ]
        );

    /*
    ==================================================
    📊 STATS GLOBALES
    ==================================================
    */

    $tavilyTotal = IndicatorScope::trends(DB::table('entity_trends'))

        ->where('market_entity_id', $marketEntityId)

        ->where('year', $year)

        ->whereIn(
            'quarter',
            $semester === 1
                ? [1, 2]
                : [3, 4]
        )

        ->where(
            'discovered_by',
            'LIKE',
            '%tavily%'
        )

        ->count();

    $gptTotal = IndicatorScope::trends(DB::table('entity_trends'))

        ->where('market_entity_id', $marketEntityId)

        ->where('year', $year)

        ->whereIn(
            'quarter',
            $semester === 1
                ? [1, 2]
                : [3, 4]
        )

        ->where(
            'discovered_by',
            'LIKE',
            '%gpt%'
        )

        ->count();

    /*
    ==================================================
    🚀 RESPONSE
    ==================================================
    */

    return response()->json([

        'data' => $trends->items(),

        'stats' => [

            'tavily_total' => $tavilyTotal,

            'gpt_total' => $gptTotal,
        ],

        'pagination' => [

            'current_page' =>
                $trends->currentPage(),

            'last_page' =>
                $trends->lastPage(),

            'per_page' =>
                $trends->perPage(),

            'total' =>
                $trends->total(),

            'prev_page_url' =>
                $trends->previousPageUrl(),

            'next_page_url' =>
                $trends->nextPageUrl(),
        ],
    ]);
}

private function getBaseContext(Request $request): array
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');

    try {
        $weights = Prueba::getActive('languages');
    } catch (\Throwable $e) {
        $weights = null;
    }

    return [
        'year' => $year,
        'period' => $period,
        'semester' => $period === 's1' ? 1 : 2,
        'range' => $this->getPeriodRange($period, $year),

        'areas' => [],
        'careers' => $request->filled('career')
            ? array_filter((array) $request->career)
            : [],

        'ranking_type' => $request->get('ranking_type', 'all'),

        'laborWeight' => (float) ($weights?->labor_weight ?? 0.7),
        'trendWeight' => (float) ($weights?->trend_weight ?? 0.3),
    ];
}

/* ==================================================
   EVOLUCIÓN (MODAL): semanas ISO, quincenas y meses
   Lee SOLO de language_ranking_weekly(_meta), que llena
   `ranking:refresh-languages`. Cada ventana cuenta las ofertas publicadas
   dentro de ella (no acumula).
================================================== */
public function weeklyScores(Request $request, LanguageRankingService $rankingService)
{
    try {
        $year = (int) $request->get('year', now()->year);
        $filter = $request->get('filter', 'weekly');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, min((int) $request->get('per_page', 6), 20));

        if (! in_array($filter, LanguageRankingService::WINDOW_TYPES, true)) {
            $filter = 'weekly';
        }

        $result = $rankingService->windowsPage($year, $filter, $page, $perPage);

        return response()->json([
            'success'    => true,
            'filter'     => $filter,
            'year'       => $year,
            'data'       => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    } catch (\Throwable $e) {
        Log::error('[LANGUAGE_EVOLUTION_ERROR]', [
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
 public function exportEvolution(Request $request)
{
    $year = (int) $request->get(
        'year',
        2026
    );

    $filter = $request->get(
        'filter',
        'weekly'
    );

    // Export genérico en streaming (mismas columnas que LanguagesEvolutionExport)
    $filter = in_array($filter, EntityRankingService::WINDOW_TYPES, true) ? $filter : 'weekly';

    return (new EntityEvolutionExport($this->rankingService(), $year, $filter, 'Lenguaje'))
        ->download("languages_evolution_{$filter}.xlsx");
}
}
