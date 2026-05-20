<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CertificationsWeeklyExport;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use Illuminate\Pagination\LengthAwarePaginator;

use App\Services\ScrapingStatusService;
use App\Http\Controllers\Dashboard\JobMarketStatusController;


class RankingCertificacionesController extends Controller
{

public function run(Request $request)
{
    try {

        $limit = (int) $request->get('limit', 10);
        $sleep = (int) $request->get('sleep', 5);

        Artisan::call('certifications:discover-gaps', [
            '--limit' => $limit,
            '--sleep' => $sleep,
        ]);

        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Descubrimiento de certificaciones ejecutado correctamente',
            'output'  => $output,
        ]);

    } catch (\Throwable $e) {

        Log::error('RUN_CERT_GAP_ERROR', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error ejecutando el comando de certificaciones',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
    public function storeWeights(Request $request)
    {
        /* ==================================================
           1. Validación
        ================================================== */
        $data = $request->validate([
            'labor_weight' => 'required|numeric|min:0|max:1',
            'trend_weight' => 'required|numeric|min:0|max:1',
        ]);

        if (round($data['labor_weight'] + $data['trend_weight'], 2) !== 1.00) {
            return response()->json([
                'message' => 'Las ponderaciones deben sumar 1.00',
            ], 422);
        }

        /* ==================================================
           2. Transacción segura
        ================================================== */
        DB::transaction(function () use ($data) {

            // 🔹 Desactivar ponderación activa actual (contexto certifications)
            Prueba::where('context', 'certifications')
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            // 🔹 Crear nueva ponderación activa
            Prueba::create([
                'labor_weight' => $data['labor_weight'],
                'trend_weight' => $data['trend_weight'],
                'context' => 'certifications',
                'is_active' => 1,
                'applied_at' => now(),
                'updated_by' => auth()->id(),
            ]);
        });

        /* ==================================================
           3. Respuesta
        ================================================== */
        return redirect()->back();


    }

public function index(Request $request)
{
    /* ==================================================
       0. CONTEXTO BASE (ÚNICA FUENTE DE VERDAD)
    ================================================== */
    $context = $this->getBaseContext($request);

    /* ==================================================
       1. RANKINGS BASE
    ================================================== */
$certifications = $this->getCertificationsRanking($context);

/*
 | ⚠️ IMPORTANTE
 | entity_trends NO se mezclan como cards
 | solo se usan dentro del cálculo
 */
$merged = $certifications;


    $ranking = $this->paginate($merged, 4);

    /* ==================================================
       2. ESTADO DE SCRAPING (NORMALIZADO)
    ================================================== */
    $scrapingStatus = array_merge([
        'status'            => null,
        'started_at'        => null,
        'finished_at'       => null,
        'last_finished_at'  => null,
        'last_run_human'    => null,
        'source'            => null,
    ], ScrapingStatusService::getByEntity('certifications') ?? []);

    $totalVacantesAnalizadas = DB::table('certification_job as cj')
    ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
    ->whereBetween('j.published_at', [
        $context['range']['start'],
        $context['range']['end'],
    ])
    ->distinct('cj.job_offer_id')
    ->count('cj.job_offer_id');
$totalReports = $this->getTrendReportsCountByRange(
    $context['range']
);


    /* ==================================================
       3. RENDER
    ================================================== */
    return Inertia::render(
        'DashboardRankingCertificaciones/RankingCertificacionesPage',
        [
            'ranking' => $ranking,

            /* ================= FILTROS ================= */
            'filters' => [
                'year'           => $context['year'],
                'search' => $request->get('search'),
                'period'         => $context['period'],
                'area'           => $context['areas'],
                'career'         => $context['careers'],
                'ranking_type'   => $context['ranking_type'],
                'trend_category' => $context['trend_category'],
            ],

            /* ================= CATÁLOGOS ================= */
            'availableAreas' => DB::table('market_entities')
                ->where('entity_type', 'certification')
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),

            'availableCareers' => DB::table('careers')
                ->where('active', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),

            'availableTrendCategories' => DB::table('market_entities')
                ->where('entity_type', 'certification')
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),

            /* ================= PONDERACIONES ================= */
            'weights' => [
                'laborWeight'  => round($context['laborWeight'] * 100, 1),
                'trendsWeight' => round($context['trendWeight'] * 100, 1),
            ],

            /* ================= ESTADOS ================= */
            'jobMarketStatus' => JobMarketStatusController::get(
                $context['year'],
                $context['period']
            ),

            'scrapingStatus' => $scrapingStatus,

            /* ================= META ================= */
           'meta' => [
    'year' => $context['year'],
    'period' => $context['period'],
    'periodo_label' =>
        $context['period'] === 's1'
            ? "Semestre 1 – Enero a Junio {$context['year']}"
            : "Semestre 2 – Julio a Diciembre {$context['year']}",

    // 🔥 ESTOS DOS SON LOS QUE FALTABAN
    'vacantes_analizadas' => $totalVacantesAnalizadas,
    'reportes_analizados' => $totalReports,

    'actualizado' => now()->toDateTimeString(),
],

        ]
    );
}

private function getBaseContext(Request $request): array
{
    $year = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');

    try {
        $weights = Prueba::getActive('certifications');
    } catch (\Throwable $e) {
        $weights = null;
    }

    return [
        'year' => $year,
         'search' => $request->get('search'),
        'period' => $period,
     'semester' => $period === 's1' ? 1 : 2,
        'range' => $this->getPeriodRange($period, $year),

        'areas' => array_filter((array) $request->get('area', [])),
        'careers' => $request->filled('career')
            ? array_filter((array) $request->career)
            : [],

        'ranking_type' => $request->get('ranking_type', 'all'),
        'trend_category' => $request->get('trend_category'),

        'laborWeight' => (float) ($weights?->labor_weight ?? 0.7),
        'trendWeight' => (float) ($weights?->trend_weight ?? 0.3),
    ];
}
private function getCertificationsRanking(array $ctx)
{
    /* ==================================================
       1. SUBQUERY: TENDENCIAS
    ================================================== */
    $reportsSub = $this->getDirectCertificationTrendsSubquery(
        $ctx['range']
    );

    /* ==================================================
       2. SUBQUERY: LABORAL
    ================================================== */
    $laborSub = DB::table('certification_job as cj')
        ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
        ->whereBetween('j.published_at', [
            $ctx['range']['start'],
            $ctx['range']['end'],
        ])
        ->select(
            'cj.market_entity_id',
            DB::raw('COUNT(DISTINCT cj.job_offer_id) as offers')
        )
        ->groupBy('cj.market_entity_id');

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
            $j->on('reports.certification_id', '=', 'me.id');
        })
        ->where('me.entity_type', 'certification');
/* ==================================================
   BUSCADOR
================================================== */
if (!empty($ctx['search'])) {

    $search = strtolower($ctx['search']);

    $query->where(function ($q) use ($search) {
        $q->whereRaw('LOWER(me.name) LIKE ?', ["%{$search}%"])
          ->orWhereRaw('LOWER(me.vendor) LIKE ?', ["%{$search}%"])
          ->orWhereRaw('LOWER(me.level) LIKE ?', ["%{$search}%"])
          ->orWhereRaw('LOWER(me.category) LIKE ?', ["%{$search}%"]);
    });
}
    /* ==================================================
       5. FILTRO ÁREAS
    ================================================== */
    if (!empty($ctx['areas'])) {
        $query->whereIn('me.category', $ctx['areas']);
    }

    /* ==================================================
       6. FILTRO CARRERAS (LEGACY, OK)
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
       7. SELECT FINAL
    ================================================== */
    return $query->select(
        DB::raw("'certification' as entity_type"),
        'me.id',
        'me.name',
        'me.vendor',
        'me.level',
        'me.category',

        // 🔥 CLASIFICACIÓN REAL
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
                    (COALESCE(reports.report_mentions,0) / {$totalReports}) * 100 * {$ctx['trendWeight']}
                ),
            1) as final_score
        ")
    )
    ->get()
    ->sortByDesc('final_score')
    ->values();
}


private function paginate($items, int $perPage)
{
    $page = LengthAwarePaginator::resolveCurrentPage();

    $items = $items->values(); // 🔥 reindexar bien

    return new LengthAwarePaginator(
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
/* ==================================================
   JOBS POR CERTIFICACIÓN (LABORAL)
================================================== */
public function jobsByCertification(Request $request, int $marketEntityId)
{
    $perPage = min((int) $request->get('per_page', 10), 50);
    $page    = (int) $request->get('page', 1);


       $jobs = DB::table('certification_job as cj')
    ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
    ->where('cj.market_entity_id', $marketEntityId)
    ->select(
        'j.id',
        'j.title',
        'j.company',
        'j.city',
        'j.country',
        'j.url'
    )
    ->orderByDesc('j.published_at')
    ->paginate($perPage, ['*'], 'page', $page);



    // 🔥 ESTO ES LO QUE TU FRONTEND ESPERA
    return response()->json([
        'data' => $jobs,
    ]);
}


/* ==================================================
   REPORTES / TENDENCIAS POR CERTIFICACIÓN
================================================== */
public function trendDetail(
    Request $request,
    int $marketEntityId
) {

    $year = (int) $request->get(
        'year',
        now()->year
    );

    $period = $request->get(
        'period',
        's1'
    );

    $semester =
        $period === 's1'
            ? 1
            : 2;

    $perPage = min(
        (int) $request->get('per_page', 10),
        50
    );

    $page = (int) $request->get(
        'page',
        1
    );

    /*
    ==================================================
    📦 REPORTES PAGINADOS
    ==================================================
    */

    $trends = DB::table('entity_trends')

        ->where(
            'market_entity_id',
            $marketEntityId
        )

        ->where(
            'year',
            $year
        )

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

                // 👇 NUEVO
                'discovered_by',

                'created_at',
            ],
            'page',
            $page
        );

    /*
    ==================================================
    📊 STATS GLOBALES
    ==================================================
    */

    $tavilyTotal = DB::table('entity_trends')

        ->where(
            'market_entity_id',
            $marketEntityId
        )

        ->where(
            'year',
            $year
        )

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

        ->where(
            'market_entity_id',
            $marketEntityId
        )

        ->where(
            'year',
            $year
        )

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

        'data' =>
            $trends->items(),

        'stats' => [

            'tavily_total' =>
                $tavilyTotal,

            'gpt_total' =>
                $gptTotal,
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


private function getDirectCertificationTrendsSubquery(array $range)
{
    return DB::table('entity_trends as et')
        ->join('market_entities as me', function ($j) {
            $j->on('me.id', '=', 'et.market_entity_id')
              ->where('me.entity_type', 'certification');
        })
        ->whereBetween('et.created_at', [
            $range['start'],
            $range['end'],
        ])
        ->select(
            'me.id as certification_id',
            DB::raw('COUNT(DISTINCT et.id) as report_mentions')
        )
        ->groupBy('me.id');
}

private function getPeriodRange(string $period, int $year): array
{
    if ($period === 's1') {
        return [
            'start' => "$year-01-01",
            'end' => "$year-06-30",
        ];
    }

    return [
        'start' => "$year-07-01",
        'end' => "$year-12-31",
    ];
}



private function getTrendReportsCountByRange(array $range): int
{
    return DB::table('entity_trends as et')
        ->join('market_entities as me', function ($j) {
            $j->on('me.id', '=', 'et.market_entity_id')
              ->where('me.entity_type', 'certification');
        })
        ->whereBetween('et.created_at', [
            $range['start'],
            $range['end'],
        ])
        ->count('et.id');
}
public function weeklyScores(Request $request, $certificationId = null)
{
    try {

        /*
        ==================================================
        📅 PARAMS
        ==================================================
        */

        $year = (int) $request->get('year', now()->year);
        $filter = $request->get('filter', 'weekly');
        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 6), 20);

        /*
        ==================================================
        🛡️ VALID FILTER
        ==================================================
        */

        if (!in_array($filter, ['weekly', 'biweekly', 'monthly'])) {
            $filter = 'weekly';
        }

        /*
        ==================================================
        🔍 QUERY CACHE (CERTIFICACIONES)
        ==================================================
        */

        // Cambiamos a la tabla de caché de certificaciones
        $query = DB::table('certification_evolution_cache as cec')
            ->join('market_entities as me', 'me.id', '=', 'cec.market_entity_id')
            ->where('cec.year', $year)
            ->where('cec.period_type', $filter);

        /* 🎯 FILTRO CERTIFICACIÓN OPCIONAL */
        if ($certificationId) {
            $query->where('cec.market_entity_id', $certificationId);
        }

        $rows = $query->select('cec.*', 'me.name')
            ->orderByDesc('cec.start_date')
            ->orderBy('cec.ranking_position')
            ->get();

        /*
        ==================================================
        📦 GROUP PERIODOS
        ==================================================
        */

        $periods = $rows
            ->groupBy(function ($item) {
                return $item->period_type . '_' . $item->start_date;
            })
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'period'           => $first->period_label,
                    'label'            => $first->period_label,
                    'start_date'       => $first->start_date,
                    'end_date'         => $first->end_date,
                    'period_score'     => round($items->avg('final_score'), 1),
                    'total_jobs'       => $items->sum('jobs'),
                    
                    /* 🔵 TOP CERTIFICACIONES */
                    'top' => $items
                        ->sortBy('ranking_position')
                        ->take(5)
                        ->values()
                        ->map(function ($item) {
                            return [
                                'id'               => $item->market_entity_id,
                                'name'             => $item->name,
                                'jobs'             => $item->jobs,
                                'trend_reports'    => $item->trend_reports,
                                'labor_score'      => (float) $item->labor_score,
                                'trend_score'      => (float) $item->trend_score,
                                'final_score'      => (float) $item->final_score,
                                'ranking_position' => $item->ranking_position,
                            ];
                        }),
                ];
            })
            ->sortByDesc('start_date')
            ->values();

        /*
        ==================================================
        📦 PAGINATION
        ==================================================
        */

        $total = $periods->count();
        $paged = $periods->forPage($page, $perPage)->values();

        /*
        ==================================================
        🚀 RESPONSE
        ==================================================
        */

        return response()->json([
            'success'    => true,
            'filter'     => $filter,
            'year'       => $year,
            'data'       => $paged,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, ceil($total / $perPage)),
            ],
        ]);

    } catch (\Throwable $e) {

        Log::error('[CERTIFICATION_EVOLUTION_CACHE_ERROR]', [
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Helper para extraer el identificador corto ('Semana 1', 'Quincena 2', 'Enero')
     * que tu JS usa en los ejes de las gráficas.
     */
    private function extractPeriodNumberLabel(string $fullLabel, string $filter): string
    {
        if ($filter === 'monthly') {
            return explode(' ', $fullLabel)[0]; // Retorna solo el nombre del mes: "Enero"
        }
        
        // Para weekly/biweekly: extrae "Semana X" o "Quincena X" antes del " de Mes"
        $parts = explode(' de ', $fullLabel);
        return $parts[0] ?? $fullLabel;
    }
public function exportWeekly(Request $request)
{
    $year   = (int) $request->get('year', 2026);
    $filter = $request->get('filter', 'weekly');

    return Excel::download(
        new CertificationsWeeklyExport($year, $filter),
        "certifications_weekly.xlsx"
    );
}
}
