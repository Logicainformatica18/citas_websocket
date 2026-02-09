<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\ScrapingStatusService;
use App\Http\Controllers\Dashboard\JobMarketStatusController;


class RankingLenguajesController extends Controller
{
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
    $reportsSub = DB::table('entity_trends as et')
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
            ->whereBetween('et.created_at', [
                $range['start'],
                $range['end'],
            ])
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
            ((COALESCE(labor.offers,0) / {$maxLabor}) * 100 * {$laborWeight})
          + ((COALESCE(reports.report_mentions,0) / {$totalReports}) * 100 * {$trendWeight})
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
        (COALESCE(labor.offers,0) / {$maxLabor}) * 100,
    1) as labor_score
"),


      DB::raw("
    ROUND(
        (COALESCE(reports.report_mentions,0) / {$totalReports}) * 100,
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
public function trendsByLanguage(Request $request, int $marketEntityId)
{
    $perPage = min((int) $request->get('per_page', 10), 50);

    $trends = DB::table('entity_trends')
        ->where('market_entity_id', $marketEntityId)
        ->orderByDesc('trend_score')
        ->paginate($perPage, [
            'id',
            'trend_score',
            'source_title',
            'source_url',
            'source_type',
            'created_at',
        ]);

    return response()->json([
        // 👇 ARRAY PLANO (esto evita el error)
        'data' => $trends->items(),

        // 👇 PAGINACIÓN SEPARADA
        'pagination' => [
            'current_page' => $trends->currentPage(),
            'last_page'    => $trends->lastPage(),
            'per_page'     => $trends->perPage(),
            'total'        => $trends->total(),
            'prev_page_url'=> $trends->previousPageUrl(),
            'next_page_url'=> $trends->nextPageUrl(),
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
        'quarter' => $period === 's1' ? 1 : 4,
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



}
