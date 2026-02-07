<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use Illuminate\Support\Facades\Log;
use App\Services\Ranking\CertificationLaborScoreService;
 
use Illuminate\Pagination\LengthAwarePaginator;

use App\Services\ScrapingStatusService;
use App\Http\Controllers\Dashboard\JobMarketStatusController;


class RankingCertificacionesController extends Controller
{
    protected CertificationLaborScoreService $laborService;

    public function __construct(CertificationLaborScoreService $laborService)
    {
        $this->laborService = $laborService;
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
    $year = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');

    try {
        $weights = Prueba::getActive('certifications');
    } catch (\Throwable $e) {
        $weights = null;
    }

    return [
        'year' => $year,
        'period' => $period,
        'quarter' => $period === 's1' ? 1 : 4,
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
    $reportsSub = $this->getDirectCertificationTrendsSubquery(
        $ctx['range']
    );

    $laborSub = DB::table('certification_job as cj')
        ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
        ->whereBetween('j.published_at', [
            $ctx['range']['start'],
            $ctx['range']['end'],
        ])
        ->select(
            'cj.certification_id',
            DB::raw('COUNT(DISTINCT cj.job_offer_id) as offers')
        )
        ->groupBy('cj.certification_id');

    $maxLabor = max(
        DB::table('certification_job')->count(),
        1
    );

    $totalReports = max(
        $this->getTrendReportsCountByRange($ctx['range']),
        1
    );

    $query = DB::table('market_entities as me')
        ->leftJoinSub($laborSub, 'labor', 'labor.certification_id', '=', 'me.id')
        ->leftJoinSub($reportsSub, 'reports', 'reports.certification_id', '=', 'me.id')
        ->where('me.entity_type', 'certification');

    if (!empty($ctx['areas'])) {
        $query->whereIn('me.category', $ctx['areas']);
    }

    if (!empty($ctx['careers'])) {
        $query->whereExists(function ($q) use ($ctx) {
            $q->select(DB::raw(1))
              ->from('certification_course as cc')
              ->join('career_course as crc', 'crc.course_id', '=', 'cc.course_id')
              ->join('careers as ca', 'ca.id', '=', 'crc.career_id')
              ->whereColumn('cc.certification_id', 'me.id')
              ->whereIn('ca.slug', $ctx['careers']);
        });
    }

    return $query->select(
        DB::raw("'certification' as entity_type"),
        'me.id',
        'me.name',
        'me.vendor',
        'me.level',
        'me.category',

        DB::raw('COALESCE(labor.offers,0) as total_jobs'),
        DB::raw('COALESCE(reports.report_mentions,0) as trend_reports'),

        DB::raw("
            ROUND(
                (LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100,
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
    )->get();
}
 private function mergeRankingForFrontend($certs, $trends, array $ctx)
{
    return collect($certs)
        ->filter(fn ($row) => $row->entity_type === 'certification')
        ->sortByDesc('final_score')
        ->values();
}

private function paginate($items, int $perPage)
{
    $page = request()->get('page', 1);

    return new LengthAwarePaginator(
        $items->forPage($page, $perPage),
        $items->count(),
        $perPage,
        $page,
        [
            'path' => request()->url(),
            'query' => request()->query(),
        ]
    );
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

}
