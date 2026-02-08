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
                    (LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100 * {$laborWeight}
                    +
                    (COALESCE(reports.report_mentions,0) / {$totalReports}) * 100 * {$trendWeight}
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

    $jobs = DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
        ->where('lj.market_entity_id', $marketEntityId)
        ->select(
            'j.id',
            'j.title',
            'j.company',
            'j.city',
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

    return response()->json([
        'data' => $jobs,
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


public function languageTrendDetail(int $trendId)
{
    $trend = DB::table('technology_trends')
        ->where('id', $trendId)
        ->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.intent')) = 'technology_trend'"
        )
        ->select(
            'id',
            'topic_name',
            'topic_category',
            'trend_score',
            'year',
            'quarter',
            'source_title',
            'source_url',
            'source_type'
        )
        ->first();

    if (!$trend) {
        return response()->json([
            'data' => null,
        ], 404);
    }

    return response()->json([
        'data' => [
            'id'           => $trend->id,
            'topic_name'   => $trend->topic_name,
            'category'     => $trend->topic_category,
            'trend_score'  => round($trend->trend_score, 1),
            'year'         => $trend->year,
            'quarter'      => $trend->quarter,
            'source_title' => $trend->source_title,
            'source_url'   => $trend->source_url,
            'source_type'  => $trend->source_type,
        ],
    ]);
}

}
