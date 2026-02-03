<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use Illuminate\Support\Facades\Log;
use App\Services\Ranking\CertificationLaborScoreService;
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
           0. Parámetros base
        ================================================== */
        $year = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');
        $quarter = $period === 's1' ? 1 : 4;
        $trendReportsCount = $this->getTrendReportsCount($year, $quarter);
        $rankingType = $request->get('ranking_type', 'all');
        $areas = array_filter((array) $request->get('area', []));
        $careers = $request->filled('career')
            ? array_filter((array) $request->career)
            : [];

        $availableCareers = DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $range = $this->getPeriodRange($period, $year);
        $trendCategory = $request->get('trend_category');
        $reportsSub = $this->getDirectCertificationTrendsSubquery($year, $quarter);
        $totalReports = $this->getTrendReportsCount($year, $quarter);
        $totalReports = max($totalReports, 1);
        /* ==================================================
           0.1 PONDERACIÓN GLOBAL
        ================================================== */
        try {
            $activeWeights = Prueba::getActive('certifications');
        } catch (\Throwable $e) {
            $activeWeights = null;
        }

        $laborWeight = (float) ($activeWeights?->labor_weight ?? 0.70);
        $trendWeight = (float) ($activeWeights?->trend_weight ?? 0.30);

        /* ==================================================
           ÁREAS DISPONIBLES
        ================================================== */
        $availableAreas = DB::table('certifications')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        /* ==================================================
           VACANTES ANALIZADAS
        ================================================== */
        $totalVacantesAnalizadas = DB::table('certification_job as cj')
            ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->distinct('cj.job_offer_id')
            ->count('cj.job_offer_id');

        /* ==================================================
           1. SUBQUERY DEMANDA LABORAL
        ================================================== */
        $laborSub = DB::table('certification_job as cj')
            ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->select(
                'cj.certification_id',
                DB::raw('COUNT(DISTINCT cj.job_offer_id) as offers')
            )
            ->groupBy('cj.certification_id');


        $maxLabor = DB::table('certification_job as cj')
    ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
    ->whereBetween('j.published_at', [$range['start'], $range['end']])
    ->when(!empty($areas), function ($q) use ($areas) {
        $q->join('certifications as c', 'c.id', '=', 'cj.certification_id')
          ->whereIn('c.category', $areas);
    })
    ->selectRaw('COUNT(DISTINCT cj.job_offer_id) as offers')
    ->groupBy('cj.certification_id')
    ->orderByDesc('offers')
    ->limit(1)
    ->value('offers') ?: 1;




        /* ==================================================
           2. SUBQUERY TENDENCIAS (CERTIFICACIONES)
        ================================================== */
        // ==================================================
// 🔥 MAX LABOR PARA TENDENCIAS (technology_trend_job)
// ==================================================
        $maxTrendLabor = DB::table('technology_trend_job as ttj')
            ->join('job_offers as j', 'j.id', '=', 'ttj.job_offer_id')
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->selectRaw('COUNT(DISTINCT ttj.job_offer_id) as offers')
            ->groupBy('ttj.technology_trend_id')
            ->orderByDesc('offers')
            ->limit(1)
            ->value('offers') ?: 1;


        /* ==================================================
           3. QUERY CERTIFICACIONES (BASE)
        ================================================== */
        $certificationsQuery = DB::table('certifications as c')
            ->leftJoinSub($laborSub, 'labor', 'labor.certification_id', '=', 'c.id')
            ->leftJoinSub(
                $reportsSub,
                'reports',
                'reports.certification_id',
                '=',
                'c.id'
            );




        if (!empty($areas)) {
            $certificationsQuery->whereIn('c.category', $areas);
        }

        if ($request->filled('career')) {
            $certificationsQuery->whereExists(function ($q) use ($careers) {
                $q->select(DB::raw(1))
                    ->from('certification_course as cc')
                    ->join('career_course as crc', 'crc.course_id', '=', 'cc.course_id')
                    ->join('careers as ca', 'ca.id', '=', 'crc.career_id')
                    ->whereColumn('cc.certification_id', 'c.id')
                    ->whereIn('ca.slug', $careers);
            });
        }

        $certificationsQuery = $certificationsQuery->select(
            DB::raw("'certification' as entity_type"),
            'c.id',
            'c.name',
            'c.vendor',
            'c.level',
            'c.category',

            // ===============================
            // Demanda laboral
            // ===============================
            DB::raw('COALESCE(labor.offers,0) as total_jobs'),
            DB::raw('COALESCE(reports.report_mentions, 0) as trend_reports'),

            DB::raw("
  ROUND(
    (LOG(COALESCE(labor.offers,0) + 1) / LOG({$maxLabor} + 1)) * 100,
  1) as labor_score
"),


            // ===============================
            // Tendencia = % de reportes
            // ===============================
            DB::raw("
        ROUND(
            (COALESCE(reports.report_mentions,0) / {$totalReports}) * 100,
        1) as trend_score
    "),
DB::raw("
  ROUND(
    (
      (LOG(COALESCE(labor.offers,0) + 1) / LOG({$maxLabor} + 1)) * 100 * {$laborWeight}
    ),
  1) as labor_weighted_score
"),

            // ===============================
            // Score final ponderado
            // ===============================
            DB::raw("
        ROUND(
            (
               (
  (LOG(COALESCE(labor.offers,0) + 1) / LOG({$maxLabor} + 1)) * 100 * {$laborWeight}
)

              + ((COALESCE(reports.report_mentions,0) / {$totalReports}) * 100 * {$trendWeight})
            ),
        1) as final_score
    "),
            // 🔥 CAMPOS DE TENDENCIA (OBLIGATORIOS PARA UNION)
            DB::raw('NULL as year'),
            DB::raw('NULL as quarter'),
            DB::raw('NULL as source_title'),
            DB::raw('NULL as source_url'),
            DB::raw('NULL as source_type')
        );


        $trendLaborSub = DB::table('technology_trend_job as ttj')
            ->join('job_offers as j', 'j.id', '=', 'ttj.job_offer_id')
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->select(
                'ttj.technology_trend_id',
                DB::raw('COUNT(DISTINCT ttj.job_offer_id) as offers')
            )
            ->groupBy('ttj.technology_trend_id');

        /* ==================================================
           4. QUERY TENDENCIAS (COMO ITEMS DE RANKING)
        ================================================== */
        $trendsQuery = DB::table('technology_trends as tt')
            ->leftJoinSub(
                $trendLaborSub,
                'trend_labor',
                'trend_labor.technology_trend_id',
                '=',
                'tt.id'
            )
            ->where('tt.topic_category', 'like', 'Certificaciones%')
            ->where('tt.year', $year)
            ->where('tt.quarter', $quarter);


        /* Filtro por categoría si aplica */
        if ($rankingType === 'trend' && !empty($trendCategory)) {
            $trendsQuery->where('tt.topic_category', $trendCategory);
        }

    $trendsQuery = $trendsQuery->select(
    DB::raw("'trend' as entity_type"),          // 1
    'tt.id as id',                              // 2
    'tt.topic_name as name',                    // 3
    DB::raw('NULL as vendor'),                  // 4
    DB::raw('NULL as level'),                   // 5
    'tt.topic_category as category',            // 6

    // ===============================
    // total_jobs
    // ===============================
    DB::raw('COALESCE(trend_labor.offers, 0) as total_jobs'), // 7

    // ===============================
    // trend_reports (VA ANTES)
    // ===============================
    DB::raw('1 as trend_reports'),               // 8

    // ===============================
    // labor_score
    // ===============================
    DB::raw("
        ROUND(
            (COALESCE(trend_labor.offers,0) / {$maxTrendLabor}) * 100,
        1) as labor_score
    "),                                          // 9

    // ===============================
    // trend_score
    // ===============================
    'tt.trend_score as trend_score',              // 10

    // ===============================
    // labor_weighted_score
    // ===============================
    DB::raw('NULL as labor_weighted_score'),      // 11

    // ===============================
    // final_score
    // ===============================
    DB::raw("ROUND(tt.trend_score * {$trendWeight},1) as final_score"), // 12

    // ===============================
    // contexto
    // ===============================
    'tt.year',                                   // 13
    'tt.quarter',                                // 14
    'tt.source_title',                           // 15
    'tt.source_url',                             // 16
    'tt.source_type'                             // 17
);





        $rankingBase = DB::query()
            ->fromSub(
                $certificationsQuery->unionAll($trendsQuery),
                'ranking'
            );

        /* 👇 AQUÍ VA EL FILTRO (ESTE ES EL BLOQUE QUE FALTABA) */
        if ($rankingType !== 'all') {
            $rankingBase->where('entity_type', $rankingType);
        }

        $ranking = $rankingBase
            ->orderByDesc('final_score')
            ->paginate(4)
            ->withQueryString();

        $availableTrendCategories = DB::table('technology_trends')
            ->whereNotNull('topic_category')
            ->where('topic_category', 'like', 'Certificaciones%')
            ->distinct()
            ->orderBy('topic_category')
            ->pluck('topic_category');

        if ($rankingType !== 'trend') {
            $trendCategory = null;
        }

        /* ==================================================
           6. Render
        ================================================== */
        return Inertia::render(
            'DashboardRankingCertificaciones/RankingCertificacionesPage',
            [
                'ranking' => $ranking,
                'filters' => [
                    'year' => $year,
                    'period' => $period,
                    'area' => $areas,
                    'career' => $careers,
                    'ranking_type' => $rankingType, // 🔥 CLAVE
                    'trend_category' => $trendCategory, // 🔥 ESTO FALTABA
                ],

                'availableAreas' => $availableAreas,
                'availableCareers' => $availableCareers,
                'availableTrendCategories' => $availableTrendCategories, // 🔥
                'weights' => [
                    'laborWeight' => round($laborWeight * 100, 1),
                    'trendsWeight' => round($trendWeight * 100, 1),
                ],
                'jobMarketStatus' => JobMarketStatusController::get($year, $period),
                'meta' => [
                    'year' => $year,
                    'period' => $period,
                    'periodo_label' => $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",
                    'vacantes_analizadas' => $totalVacantesAnalizadas,
                    'reportes_analizados' => $trendReportsCount,
                    'actualizado' => now()->toDateTimeString(),
                ],
            ]
        );
    }



    public function trendDetail(Request $request)
    {
        $year = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');
        $quarter = $period === 's1' ? 1 : 4;

        $trend = DB::table('technology_trends as tt')
            ->where('tt.topic_category', 'like', 'Certificaciones%')
            ->where('tt.year', $year)
            ->where('tt.quarter', $quarter)
            ->orderByDesc('tt.trend_score')
            ->select(
                'tt.id',
                'tt.topic_name',
                'tt.trend_score',
                'tt.year',
                'tt.quarter',
                'tt.source_title',
                'tt.source_url',
                'tt.source_type'
            )
            ->first();

        return response()->json([
            'data' => $trend,
        ]);
    }





    public function jobsByCertification(Request $request, int $certificationId)
    {
        $year = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');

        $perPage = min((int) $request->get('per_page', 10), 50);

        $jobsQuery = $this->laborService
            ->getJobsForCertification($certificationId, $year, $period);

        $jobs = $jobsQuery
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
            'used_for_score' => true,
            'year' => $year,
            'period' => $period,
            'data' => $jobs,
        ]);
    }
    private function getDirectCertificationTrendsSubquery(int $year, int $quarter)
    {
        return DB::table('certifications as c')
            ->join('technology_trends as tt', function ($join) use ($year, $quarter) {
                $join->whereRaw(
                    "JSON_UNQUOTE(tt.scanned_keywords) LIKE CONCAT('%', LOWER(c.name), '%')"
                )
                    ->where('tt.topic_category', 'like', 'Certificaciones%')
                    ->where('tt.year', $year)
                    ->where('tt.quarter', $quarter);
            })
            ->select(
                'c.id as certification_id',
                DB::raw('COUNT(DISTINCT tt.id) as report_mentions'),
                DB::raw('ROUND(AVG(tt.trend_score), 2) as avg_trend_score')
            )
            ->groupBy('c.id');
    }

public function jobsByTechnologyTrend(Request $request, int $trendId)
{
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    Log::info('🟣 [TrendJobs] Request recibida', [
        'trend_id' => $trendId,
        'year' => $year,
        'period' => $period,
        'quarter' => $quarter,
    ]);

    /* =========================
       1️⃣ Validar tendencia
    ========================= */
    $trend = DB::table('technology_trends')
        ->where('id', $trendId)
        ->first();

    Log::info('🟣 [TrendJobs] Trend encontrado', [
        'exists' => (bool) $trend,
        'trend' => $trend,
    ]);

    /* =========================
       2️⃣ Conteo directo en pivot
    ========================= */
    $pivotCount = DB::table('technology_trend_job')
        ->where('technology_trend_id', $trendId)
        ->count();

    Log::info('🟣 [TrendJobs] Conteo en technology_trend_job', [
        'count' => $pivotCount,
    ]);

    /* =========================
       3️⃣ IDs de jobs en pivot
    ========================= */
    $jobIds = DB::table('technology_trend_job')
        ->where('technology_trend_id', $trendId)
        ->pluck('job_offer_id');

    Log::info('🟣 [TrendJobs] Job IDs asociados', [
        'job_ids' => $jobIds->take(10), // solo primeros para no saturar
        'total' => $jobIds->count(),
    ]);

    /* =========================
       4️⃣ Query base de jobs (SIN FILTROS)
    ========================= */
    $baseQuery = DB::table('job_offers')
        ->whereIn('id', $jobIds);

    Log::info('🟣 [TrendJobs] Jobs existentes sin filtros', [
        'count' => $baseQuery->count(),
    ]);

    /* =========================
       5️⃣ Aplicar filtros temporales
    ========================= */
    $range = $this->getPeriodRange($period, $year);

    Log::info('🟣 [TrendJobs] Rango de fechas', $range);

    $jobsQuery = DB::table('technology_trend_job as ttj')
        ->join('job_offers as j', 'j.id', '=', 'ttj.job_offer_id')
        ->where('ttj.technology_trend_id', $trendId)
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
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
        );

    Log::info('🟣 [TrendJobs] SQL final', [
        'sql' => $jobsQuery->toSql(),
        'bindings' => $jobsQuery->getBindings(),
    ]);

    $jobs = $jobsQuery
        ->orderByDesc('j.published_at')
        ->paginate(10);

    Log::info('🟣 [TrendJobs] Resultado final', [
        'total' => $jobs->total(),
        'current_page' => $jobs->currentPage(),
    ]);

    return response()->json([
        'used_for_score' => true,
        'trend_id' => $trendId,
        'year' => $year,
        'period' => $period,
        'data' => $jobs,
    ]);
}


    // private function getCertificationReportsSubquery(int $year, int $quarter)
// {
//     return DB::table('technology_trends as tt')
//         ->join('technology_trend_technology as ttt', 'ttt.technology_trend_id', '=', 'tt.id')
//         ->join('course_technology as ct', 'ct.technology_id', '=', 'ttt.technology_id')
//         ->join('certification_course as cc', 'cc.course_id', '=', 'ct.course_id')
//         ->where('tt.topic_category', 'like', 'Certificaciones%')
//         ->where('tt.year', $year)
//         ->where('tt.quarter', $quarter)
//         ->select(
//             'cc.certification_id',
//             DB::raw('COUNT(DISTINCT tt.id) as report_mentions')
//         )
//         ->groupBy('cc.certification_id');
// }

    private function getTrendReportsCount(int $year, int $quarter): int
    {
        return DB::table('technology_trends')
            ->where('topic_category', 'like', 'Certificaciones%')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->distinct('id')
            ->count('id');
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

}
