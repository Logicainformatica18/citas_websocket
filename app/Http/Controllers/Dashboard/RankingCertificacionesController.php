<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\Ranking\CertificationLaborScoreService;
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
            'context'      => 'certifications',
            'is_active'    => 1,
            'applied_at'   => now(),
            'updated_by'   => auth()->id(),
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
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;
    $trendReportsCount = $this->getTrendReportsCount($year, $quarter);
$rankingType = $request->get('ranking_type', 'all');
    $areas   = array_filter((array) $request->get('area', []));
    $careers = $request->filled('career')
        ? array_filter((array) $request->career)
        : [];

    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    $range = $this->getPeriodRange($period, $year);
    $trendCategory = $request->get('trend_category');
$reportsSub   = $this->getCertificationReportsSubquery($year, $quarter);
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

    $maxLabor = DB::query()
        ->fromSub($laborSub, 'x')
        ->selectRaw('MAX(offers)')
        ->value('MAX(offers)') ?: 1;

    /* ==================================================
       2. SUBQUERY TENDENCIAS (CERTIFICACIONES)
    ================================================== */


    /* ==================================================
       3. QUERY CERTIFICACIONES (BASE)
    ================================================== */
 $certificationsQuery = DB::table('certifications as c')
    ->leftJoinSub($laborSub, 'labor', 'labor.certification_id', '=', 'c.id')
    ->leftJoinSub($reportsSub, 'reports', 'reports.certification_id', '=', 'c.id');



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
            (COALESCE(labor.offers,0) / {$maxLabor}) * 100,
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

    // ===============================
    // Score final ponderado
    // ===============================
    DB::raw("
        ROUND(
            (
                ((COALESCE(labor.offers,0) / {$maxLabor}) * 100 * {$laborWeight})
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



    /* ==================================================
       4. QUERY TENDENCIAS (COMO ITEMS DE RANKING)
    ================================================== */
$trendsQuery = DB::table('technology_trends as tt')
    ->where('tt.topic_category', 'like', 'Certificaciones%')
    ->where('tt.year', $year)
    ->where('tt.quarter', $quarter);

/* Filtro por categoría si aplica */
if ($rankingType === 'trend' && !empty($trendCategory)) {
    $trendsQuery->where('tt.topic_category', $trendCategory);
}

$trendsQuery = $trendsQuery->select(
    DB::raw("'trend' as entity_type"),
    'tt.id as id',
    'tt.topic_name as name',
    DB::raw('NULL as vendor'),
    DB::raw('NULL as level'),
    'tt.topic_category as category',

    // métricas
    DB::raw('0 as total_jobs'),
    DB::raw('1 as trend_reports'),
    DB::raw('0 as labor_score'),
    'tt.trend_score as trend_score',
    DB::raw("ROUND(tt.trend_score * {$trendWeight},1) as final_score"),

    // 🔥 DATOS DE CONTEXTO (CLAVE)
    'tt.year',
    'tt.quarter',
    'tt.source_title',
    'tt.source_url',
    'tt.source_type'
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
    'year'          => $year,
    'period'        => $period,
    'area'          => $areas,
    'career'        => $careers,
    'ranking_type'  => $rankingType, // 🔥 CLAVE
      'trend_category' => $trendCategory, // 🔥 ESTO FALTABA
],

            'availableAreas' => $availableAreas,
            'availableCareers' => $availableCareers,
                'availableTrendCategories' => $availableTrendCategories, // 🔥
            'weights' => [
                'laborWeight'  => round($laborWeight * 100, 1),
                'trendsWeight' => round($trendWeight * 100, 1),
            ],
            'meta' => [
                'year'   => $year,
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
    $year   = (int) $request->get('year', 2025);
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
    $year   = (int) $request->get('year', 2025);
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
        'year'           => $year,
        'period'         => $period,
        'data'           => $jobs,
    ]);
}
private function getCertificationReportsSubquery(int $year, int $quarter)
{
    return DB::table('technology_trends as tt')
        ->join('technology_trend_technology as ttt', 'ttt.technology_trend_id', '=', 'tt.id')
        ->join('course_technology as ct', 'ct.technology_id', '=', 'ttt.technology_id')
        ->join('certification_course as cc', 'cc.course_id', '=', 'ct.course_id')
        ->where('tt.topic_category', 'like', 'Certificaciones%')
        ->where('tt.year', $year)
        ->where('tt.quarter', $quarter)
        ->select(
            'cc.certification_id',
            DB::raw('COUNT(DISTINCT tt.id) as report_mentions')
        )
        ->groupBy('cc.certification_id');
}

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
            'end'   => "$year-06-30",
        ];
    }

    return [
        'start' => "$year-07-01",
        'end'   => "$year-12-31",
    ];
}

}
