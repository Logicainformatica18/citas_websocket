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
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LanguagesEvolutionExport;
use Illuminate\Support\Facades\Log;

class RankingLenguajesController extends Controller
{
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

public function index(Request $request)
{
    /* ==================================================
       0. CONTEXTO BASE (IGUAL A CERTIFICACIONES)
    ================================================== */
    $career = $request->input('career');
$search = $request->get('search');
$career = $career
    ? (is_array($career) ? $career : [$career])
    : [];

    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');
    $range  = $this->getPeriodRange($period, $year);
$availableCareers = DB::table('careers')
    ->where('active', 1)
    ->orderBy('name')
    ->get(['id', 'name', 'slug']);

    try {
        $weights = Prueba::getActive('languages');
    } catch (\Throwable $e) {
        $weights = null;
    }

    $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
    $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

    /* ==================================================
       1. SUBQUERY LABORAL (language_job → market_entity)
    ================================================== */
    $laborSub = DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
        ->whereBetween('j.published_at', [
            $range['start'],
            $range['end'],
        ])
        ->select(
            'lj.market_entity_id',
            DB::raw('COUNT(DISTINCT lj.job_offer_id) as offers')
        )
        ->groupBy('lj.market_entity_id');

    /* ==================================================
       2. SUBQUERY TENDENCIAS (entity_trends)
    ================================================== */
$semester = $period === 's1' ? 1 : 2;

$reportsSub = DB::table('entity_trends as et')
    ->join('market_entities as me', function ($j) {
        $j->on('me.id', '=', 'et.market_entity_id')
          ->where('me.entity_type', 'language');
    })
    ->where('et.year', $year)
->whereIn(
    'et.quarter',
    $semester === 1
        ? [1, 2]
        : [3, 4]
)->select(
            'me.id as language_id',
            DB::raw('COUNT(DISTINCT et.id) as report_mentions')
        )
        ->groupBy('me.id');

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
        DB::table('entity_trends as et')
            ->join('market_entities as me', function ($j) {
                $j->on('me.id', '=', 'et.market_entity_id')
                  ->where('me.entity_type', 'language');
            })
            ->where('et.year', $year)
->whereIn(
    'et.quarter',
    $semester === 1
        ? [1, 2]
        : [3, 4]
)
->count('et.id'),
        1
    );

    /* ==================================================
       4. QUERY PRINCIPAL (market_entities)
    ================================================== */
    $query = DB::table('market_entities as me')
        ->leftJoinSub($laborSub, 'labor', function ($j) {
            $j->on('labor.market_entity_id', '=', 'me.id');
        })
        ->leftJoinSub($reportsSub, 'reports', function ($j) {
            $j->on('reports.language_id', '=', 'me.id');
        })
        ->where('me.entity_type', 'language');
if ($search) {
    $query->where('me.name', 'like', "%{$search}%");
}

        if (!empty($career)) {
    $query->whereExists(function ($q) use ($career) {
        $q->select(DB::raw(1))
          ->from('market_entity_career as mec')
          ->join('careers as ca', 'ca.id', '=', 'mec.career_id')
          ->whereColumn('mec.market_entity_id', 'me.id')
          ->whereIn('ca.slug', $career);
    });
}


    /* ==================================================
       5. SELECT FINAL
    ================================================== */
    $ranking = $query->select(
        DB::raw("'language' as entity_type"),
        'me.id',
        'me.name',

        // 🔥 CLASIFICACIÓN MARKET-FIRST
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
            ((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100 * {$laborWeight})
          + ((LOG(COALESCE(reports.report_mentions,0)+1) / LOG({$maxTrend}+1)) * 100 * {$trendWeight})
        ),
    1) as final_score
"),

    )
    ->orderByDesc('final_score')
    ->paginate(10)
    ->withQueryString();

    /* ==================================================
       6. METADATA
    ================================================== */
    $totalVacantesAnalizadas = DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
        ->whereBetween('j.published_at', [
            $range['start'],
            $range['end'],
        ])
        ->distinct('lj.job_offer_id')
        ->count('lj.job_offer_id');

    $scrapingStatus = ScrapingStatusService::getByEntity('languages');

    /* ==================================================
       7. RENDER
    ================================================== */
    return Inertia::render(
        'DashboardRankingLanguages/RankingLenguajesPage',
        [
            'ranking' => $ranking,

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

            'scrapingStatus' => $scrapingStatus,

            'jobMarketStatus' => JobMarketStatusController::get(
                $year,
                $period
            ),
            'availableCareers' => $availableCareers,


            'meta' => [
                'year' => $year,
                'period' => $period,
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

    $paginator = DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
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
    return DB::table('entity_trends as et')
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
    return DB::table('entity_trends as et')
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

    $trends = DB::table('entity_trends')

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

    $tavilyTotal = DB::table('entity_trends')

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

    $gptTotal = DB::table('entity_trends')

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

public function weeklyScores(Request $request, $languageId = null)
{
    \Carbon\Carbon::setLocale('es');
    setlocale(LC_TIME, 'es_ES.UTF-8');

    try {

        /*
        ==================================================
        📅 PARAMS
        ==================================================
        */

        $year = (int) $request->get('year');

        if (!$year) {

            return response()->json([
                'success' => false,
                'message' => 'Year es requerido',
            ], 422);
        }

        $perPage = min(
            (int) $request->get('per_page', 6),
            20
        );

        $page = (int) $request->get('page', 1);

        $filter = $request->get(
            'filter',
            'weekly'
        );

        if (!in_array($filter, [
            'weekly',
            'biweekly',
            'monthly',
        ])) {

            $filter = 'weekly';
        }

        /*
        ==================================================
        🔍 QUERY BASE
        ==================================================
        */

        $query = DB::table('language_job as lj')

            ->join(
                'job_offers as j',
                'j.id',
                '=',
                'lj.job_offer_id'
            )

            ->join(
                'market_entities as me',
                'me.id',
                '=',
                'lj.market_entity_id'
            )

            ->whereRaw(
                'YEAR(j.published_at) = ?',
                [$year]
            );

        /*
        ==================================================
        🎯 FILTRO LENGUAJE
        ==================================================
        */

        if ($languageId) {

            $query->where(
                'lj.market_entity_id',
                $languageId
            );
        }

        /*
        ==================================================
        📅 WEEKLY
        ==================================================
        */

        if ($filter === 'weekly') {

            $weekExpression = "
                FLOOR((DAY(j.published_at)-1)/7)+1
            ";

            /*
            ==========================================
            🔵 TOP LENGUAJES
            ==========================================
            */

            $rows = (clone $query)

                ->groupBy(

                    DB::raw("
                        MONTH(j.published_at)
                    "),

                    DB::raw($weekExpression),

                    'me.id',

                    'me.name'
                )

                ->select(

                    DB::raw("
                        MONTH(j.published_at)
                        as month_number
                    "),

                    DB::raw("
                        {$weekExpression}
                        as week_number
                    "),

                    'me.id',

                    'me.name',

                    DB::raw("
                        COUNT(DISTINCT lj.job_offer_id)
                        as total
                    ")
                )

                ->get();

            /*
            ==========================================
            🟢 TOTALES REALES
            ==========================================
            */

            $realTotals = DB::table('language_job as lj')

                ->join(
                    'job_offers as j',
                    'j.id',
                    '=',
                    'lj.job_offer_id'
                )

                ->whereRaw(
                    'YEAR(j.published_at) = ?',
                    [$year]
                )

                ->select(

                    DB::raw("
                        MONTH(j.published_at)
                        as month_number
                    "),

                    DB::raw("
                        {$weekExpression}
                        as week_number
                    "),

                    DB::raw("
                        COUNT(DISTINCT lj.job_offer_id)
                        as total_unique
                    ")
                )

                ->groupBy(
                    DB::raw("MONTH(j.published_at)"),
                    DB::raw($weekExpression)
                )

                ->get()

                ->keyBy(function ($item) {

                    return
                        $item->month_number .
                        '-' .
                        $item->week_number;
                });

            /*
            ==========================================
            📦 PERIODOS
            ==========================================
            */

            $periods = $rows

                ->groupBy(function ($item) {

                    return
                        $item->month_number .
                        '-' .
                        $item->week_number;
                })

                ->map(function ($items, $key) use (
                    $year,
                    $realTotals
                ) {

                    $first = $items->first();

                    $month = $first->month_number;

                    $week = $first->week_number;

                    /*
                    ==========================================
                    📅 START DAY
                    ==========================================
                    */

                    $startDay =
                        (($week - 1) * 7) + 1;

                    /*
                    ==========================================
                    📅 END DAY
                    ==========================================
                    */

                    $daysInMonth = \Carbon\Carbon::create(
                        $year,
                        $month,
                        1
                    )->daysInMonth;

                    $endDay = min(
                        $startDay + 6,
                        $daysInMonth
                    );

                    /*
                    ==========================================
                    📅 RANGE
                    ==========================================
                    */

                    $startDate = \Carbon\Carbon::create(
                        $year,
                        $month,
                        $startDay
                    )->format('Y-m-d');

                    $endDate = \Carbon\Carbon::create(
                        $year,
                        $month,
                        $endDay
                    )->format('Y-m-d');

                    /*
                    ==========================================
                    📅 MONTH NAME
                    ==========================================
                    */

                    $monthName = \Carbon\Carbon::create()
                        ->month($month)
                        ->translatedFormat('F');

                    return [

                        'period' =>
                            'Semana ' . $week,

                        'label' =>
                            'Semana ' .
                            $week .
                            ' de ' .
                            ucfirst($monthName),

                        'month' => $month,

                        'week' => $week,

                        'start_date' =>
                            $startDate,

                        'end_date' =>
                            $endDate,

                        /*
                        ✅ TOTAL REAL
                        */

                        'total_period' =>
                            $realTotals[$key]->total_unique ?? 0,

                        /*
                        🔵 TOP
                        */

                        'top' => $items

                            ->sortByDesc('total')

                            ->take(5)

                            ->values()

                            ->map(function ($item) {

                                return [

                                    'id' =>
                                        $item->id,

                                    'name' =>
                                        $item->name,

                                    'total' =>
                                        $item->total,
                                ];
                            }),
                    ];
                })

                ->sortByDesc('start_date')

                ->values();
        }

        /*
        ==================================================
        📅 BIWEEKLY
        ==================================================
        */

        elseif ($filter === 'biweekly') {

            $expression = "
                CASE
                    WHEN DAY(j.published_at) <= 15
                    THEN 1
                    ELSE 2
                END
            ";

            $rows = (clone $query)

                ->groupBy(

                    DB::raw("
                        MONTH(j.published_at)
                    "),

                    DB::raw($expression),

                    'me.id',

                    'me.name'
                )

                ->select(

                    DB::raw("
                        MONTH(j.published_at)
                        as month_number
                    "),

                    DB::raw("
                        {$expression}
                        as quincena
                    "),

                    'me.id',

                    'me.name',

                    DB::raw("
                        COUNT(DISTINCT lj.job_offer_id)
                        as total
                    ")
                )

                ->get();

            $realTotals = DB::table('language_job as lj')

                ->join(
                    'job_offers as j',
                    'j.id',
                    '=',
                    'lj.job_offer_id'
                )

                ->whereRaw(
                    'YEAR(j.published_at) = ?',
                    [$year]
                )

                ->select(

                    DB::raw("
                        MONTH(j.published_at)
                        as month_number
                    "),

                    DB::raw("
                        {$expression}
                        as quincena
                    "),

                    DB::raw("
                        COUNT(DISTINCT lj.job_offer_id)
                        as total_unique
                    ")
                )

                ->groupBy(
                    DB::raw("MONTH(j.published_at)"),
                    DB::raw($expression)
                )

                ->get()

                ->keyBy(function ($item) {

                    return
                        $item->month_number .
                        '-' .
                        $item->quincena;
                });

            $periods = $rows

                ->groupBy(function ($item) {

                    return
                        $item->month_number .
                        '-' .
                        $item->quincena;
                })

                ->map(function ($items, $key) use (
                    $year,
                    $realTotals
                ) {

                    $first = $items->first();

                    $month = $first->month_number;

                    $quincena = $first->quincena;

                    $daysInMonth = \Carbon\Carbon::create(
                        $year,
                        $month,
                        1
                    )->daysInMonth;

                    if ($quincena == 1) {

                        $startDay = 1;
                        $endDay = 15;

                    } else {

                        $startDay = 16;
                        $endDay = $daysInMonth;
                    }

                    $startDate = \Carbon\Carbon::create(
                        $year,
                        $month,
                        $startDay
                    )->format('Y-m-d');

                    $endDate = \Carbon\Carbon::create(
                        $year,
                        $month,
                        $endDay
                    )->format('Y-m-d');

                    $monthName = \Carbon\Carbon::create()
                        ->month($month)
                        ->translatedFormat('F');

                    return [

                        'period' =>
                            'Quincena ' . $quincena,

                        'label' =>
                            'Quincena ' .
                            $quincena .
                            ' de ' .
                            ucfirst($monthName),

                        'month' => $month,

                        'start_date' =>
                            $startDate,

                        'end_date' =>
                            $endDate,

                        /*
                        ✅ TOTAL REAL
                        */

                        'total_period' =>
                            $realTotals[$key]->total_unique ?? 0,

                        'top' => $items

                            ->sortByDesc('total')

                            ->take(5)

                            ->values()

                            ->map(function ($item) {

                                return [

                                    'id' =>
                                        $item->id,

                                    'name' =>
                                        $item->name,

                                    'total' =>
                                        $item->total,
                                ];
                            }),
                    ];
                })

                ->sortByDesc('start_date')

                ->values();
        }

        /*
        ==================================================
        📅 MONTHLY
        ==================================================
        */

        else {

            $rows = (clone $query)

                ->groupBy(

                    DB::raw("
                        MONTH(j.published_at)
                    "),

                    'me.id',

                    'me.name'
                )

                ->select(

                    DB::raw("
                        MONTH(j.published_at)
                        as month_number
                    "),

                    'me.id',

                    'me.name',

                    DB::raw("
                        COUNT(DISTINCT lj.job_offer_id)
                        as total
                    ")
                )

                ->get();

            $realTotals = DB::table('language_job as lj')

                ->join(
                    'job_offers as j',
                    'j.id',
                    '=',
                    'lj.job_offer_id'
                )

                ->whereRaw(
                    'YEAR(j.published_at) = ?',
                    [$year]
                )

                ->select(

                    DB::raw("
                        MONTH(j.published_at)
                        as month_number
                    "),

                    DB::raw("
                        COUNT(DISTINCT lj.job_offer_id)
                        as total_unique
                    ")
                )

                ->groupBy(
                    DB::raw("MONTH(j.published_at)")
                )

                ->pluck(
                    'total_unique',
                    'month_number'
                );

            $periods = $rows

                ->groupBy('month_number')

                ->map(function (
                    $items,
                    $month
                ) use (
                    $year,
                    $realTotals
                ) {

                    $daysInMonth = \Carbon\Carbon::create(
                        $year,
                        $month,
                        1
                    )->daysInMonth;

                    $startDate = \Carbon\Carbon::create(
                        $year,
                        $month,
                        1
                    )->format('Y-m-d');

                    $endDate = \Carbon\Carbon::create(
                        $year,
                        $month,
                        $daysInMonth
                    )->format('Y-m-d');

                    $monthName = \Carbon\Carbon::create()
                        ->month($month)
                        ->translatedFormat('F');

                    return [

                        'period' =>
                            ucfirst($monthName),

                        'label' =>
                            ucfirst($monthName),

                        'month' => $month,

                        'start_date' =>
                            $startDate,

                        'end_date' =>
                            $endDate,

                        /*
                        ✅ TOTAL REAL
                        */

                        'total_period' =>
                            $realTotals[$month] ?? 0,

                        'top' => $items

                            ->sortByDesc('total')

                            ->take(5)

                            ->values()

                            ->map(function ($item) {

                                return [

                                    'id' =>
                                        $item->id,

                                    'name' =>
                                        $item->name,

                                    'total' =>
                                        $item->total,
                                ];
                            }),
                    ];
                })

                ->sortByDesc('month')

                ->values();
        }

        /*
        ==================================================
        📦 PAGINATION
        ==================================================
        */

        $total = $periods->count();

        $paged = $periods

            ->forPage(
                $page,
                $perPage
            )

            ->values();

        /*
        ==================================================
        🚀 RESPONSE
        ==================================================
        */

        return response()->json([

            'success' => true,

            'filter' => $filter,

            'year' => $year,

            'data' => $paged,

            'pagination' => [

                'current_page' => $page,

                'per_page' => $perPage,

                'total' => $total,

                'last_page' => max(
                    1,
                    ceil($total / $perPage)
                ),
            ],
        ]);

    } catch (\Throwable $e) {

        Log::error('[LANGUAGE_EVOLUTION_ERROR]', [

            'message' => $e->getMessage(),

            'line' => $e->getLine(),

            'file' => $e->getFile(),

            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([

            'success' => false,

            'message' => $e->getMessage(),

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

    return Excel::download(

        new LanguagesEvolutionExport(
            $year,
            $filter
        ),

        "languages_evolution_{$filter}.xlsx"
    );
}
}
