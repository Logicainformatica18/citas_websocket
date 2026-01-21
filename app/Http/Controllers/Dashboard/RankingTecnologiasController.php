<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;

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
       0. Parámetros base
    ================================================== */
    $year        = (int) $request->get('year', 2025);
    $period      = $request->get('period', 's2');
    $quarter     = $period === 's1' ? 1 : 4;
 
$rankingType = $request->get('ranking_type');

if (!in_array($rankingType, ['all', 'technology', 'trend'])) {
    $rankingType = 'all';
}

    $categories = array_filter((array) $request->get('category', []));
    $careers    = array_filter((array) $request->get('career', []));

    $range = $this->getPeriodRange($period, $year);

    /* ==================================================
       0.1 Ponderaciones activas
    ================================================== */
    try {
        $activeWeights = Prueba::getActive('technologies');
    } catch (\Throwable $e) {
        $activeWeights = null;
    }

    $laborWeight = (float) ($activeWeights?->labor_weight ?? 0.70);
    $trendWeight = (float) ($activeWeights?->trend_weight ?? 0.30);

    /* ==================================================
       Catálogos
    ================================================== */
    $availableCategories = DB::table('technology_categories')
        ->orderBy('name')
        ->pluck('name');

    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    /* ==================================================
       Vacantes analizadas
    ================================================== */
    $totalVacantesAnalizadas = DB::table('technology_job as tj')
        ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('tj.job_offer_id')
        ->count('tj.job_offer_id');

    /* ==================================================
       1. SUBQUERY DEMANDA LABORAL
    ================================================== */
    $laborSub = DB::table('technology_job as tj')
        ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->select(
            'tj.technology_id',
            DB::raw('COUNT(DISTINCT tj.job_offer_id) as offers')
        )
        ->groupBy('tj.technology_id');

    $maxLabor = DB::query()
        ->fromSub($laborSub, 'x')
        ->selectRaw('MAX(offers)')
        ->value('MAX(offers)') ?: 1;

    /* ==================================================
       2. SUBQUERY TENDENCIAS (TECNOLOGÍAS)
    ================================================== */
    $reportsSub = DB::table('technology_trends as tt')
        ->join('technology_trend_technology as ttt', 'ttt.technology_trend_id', '=', 'tt.id')
       ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(tt.raw_data, '$.intent')) = 'technology_trend'")

        ->where('tt.year', $year)
        ->where('tt.quarter', $quarter)
        ->select(
            'ttt.technology_id',
            DB::raw('COUNT(DISTINCT tt.id) as report_mentions')
        )
        ->groupBy('ttt.technology_id');

  $totalReports = DB::table('technology_trends')
    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.intent')) = 'technology_trend'")
    ->where('year', $year)
    ->where('quarter', $quarter)
    ->distinct('id')
    ->count('id');

$totalReports = max($totalReports, 1);


    /* ==================================================
       3. TECNOLOGÍAS ISIL (asociadas a carrera)
    ================================================== */
    $isilQuery = DB::table('technologies as t')
        ->leftJoinSub($laborSub, 'labor', 'labor.technology_id', '=', 't.id')
        ->leftJoinSub($reportsSub, 'reports', 'reports.technology_id', '=', 't.id')
        ->leftJoin('technology_categories as tc', 'tc.id', '=', 't.category_id')
        ->where('t.enabled', 1)
        ->whereExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('course_technology as ct')
              ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
              ->whereColumn('ct.technology_id', 't.id');
        });

    if (!empty($categories)) {
        $isilQuery->whereIn('tc.name', $categories);
    }

    if (!empty($careers)) {
        $isilQuery->whereExists(function ($q) use ($careers) {
            $q->select(DB::raw(1))
              ->from('course_technology as ct')
              ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
              ->join('careers as ca', 'ca.id', '=', 'cc.career_id')
              ->whereColumn('ct.technology_id', 't.id')
              ->whereIn('ca.slug', $careers);
        });
    }

  $isilQuery = $isilQuery->select(
    DB::raw("'technology' as entity_type"),
    DB::raw('1 as is_isil'),
    DB::raw('0 as is_real_trend'), 
    't.id',
    't.name',
    'tc.name as category',

    // métricas
DB::raw('COALESCE(labor.offers,0) as total_jobs'),
DB::raw('COALESCE(reports.report_mentions,0) as trend_reports'), // 🔥 FALTABA

DB::raw("
    ROUND((COALESCE(labor.offers,0) / {$maxLabor}) * 100, 1)
    as labor_score
"),


    DB::raw("
        ROUND((COALESCE(reports.report_mentions,0) / {$totalReports}) * 100, 1)
        as trend_score
    "),

    DB::raw("
        ROUND(
            (
                ((COALESCE(labor.offers,0) / {$maxLabor}) * 100 * {$laborWeight})
              + ((COALESCE(reports.report_mentions,0) / {$totalReports}) * 100 * {$trendWeight})
            ),
        1) as final_score
    "),

    // 🔥 CAMPOS DE CONTEXTO (dummy)
    DB::raw('NULL as year'),
    DB::raw('NULL as quarter'),
    DB::raw('NULL as source_title'),
    DB::raw('NULL as source_url'),
    DB::raw('NULL as source_type')
);


    /* ==================================================
       4. TECNOLOGÍAS EN TENDENCIA (NO ISIL)
    ================================================== */
$trendsQuery = DB::table('technology_trends as tt')
    ->leftJoin('technology_trend_job as ttj', 'ttj.technology_trend_id', '=', 'tt.id')
    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(tt.raw_data, '$.intent')) = 'technology_trend'")
    ->where('tt.year', $year)
    ->where('tt.quarter', $quarter)
    ->groupBy(
        'tt.id',
        'tt.topic_name',
        'tt.trend_score',
        'tt.year',
        'tt.quarter',
        'tt.source_title',
        'tt.source_url',
        'tt.source_type'
    )
    ->select(
        DB::raw("'trend' as entity_type"),
        DB::raw('0 as is_isil'),
        DB::raw('1 as is_real_trend'), // 🔥 CLAVE
        'tt.id',
        'tt.topic_name as name',
        DB::raw('NULL as category'),

        // métricas
        DB::raw('COUNT(DISTINCT ttj.job_offer_id) as total_jobs'),
        DB::raw('COUNT(DISTINCT tt.id) as trend_reports'),

        DB::raw("
            ROUND(
              LEAST(
                (COUNT(DISTINCT ttj.job_offer_id) / {$maxLabor}) * 100,
                100
              ),
              1
            ) as labor_score
        "),

        'tt.trend_score as trend_score',

        DB::raw("
            ROUND(
              (
                LEAST(
                  (COUNT(DISTINCT ttj.job_offer_id) / {$maxLabor}) * 100,
                  100
                ) * {$laborWeight}
              ) + (tt.trend_score * {$trendWeight}),
              1
            ) as final_score
        "),

        // contexto
        'tt.year',
        'tt.quarter',
        'tt.source_title',
        'tt.source_url',
        'tt.source_type'
    );


if ($rankingType === 'trend' && $request->filled('trend_category')) {
    $trendsQuery->where('tt.topic_name', $request->trend_category);
}

    /* ==================================================
       5. UNION + FILTRO
    ================================================== */
    if ($rankingType === 'technology') {

    // 👉 Solo tecnologías ISIL
    $rankingBase = DB::query()
        ->fromSub($isilQuery, 'ranking');

} elseif ($rankingType === 'trend') {

    // 👉 Solo tendencias tecnológicas
    $rankingBase = DB::query()
        ->fromSub($trendsQuery, 'ranking');

} else {

    // 👉 Ranking general (ISIL + Tendencias)
    $rankingBase = DB::query()
        ->fromSub(
            $isilQuery->unionAll($trendsQuery),
            'ranking'
        );
}


    $ranking = $rankingBase
        ->orderByDesc('final_score')
        ->paginate(10)
        ->withQueryString();

    /* ==================================================
       Render
    ================================================== */

    $availableTrendCategories = DB::table('technology_trends')
    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.intent')) = 'technology_trend'")
    ->where('year', $year)
    ->where('quarter', $quarter)
    ->distinct()
    ->orderBy('topic_name')
    ->pluck('topic_name');


    return Inertia::render(
        'DashboardRankingTechnologies/RankingTecnologiasPage',
        [
            'ranking' => $ranking,
            'filters' => [
                'year'         => $year,
                'period'       => $period,
                'category'     => $categories,
                'career'       => $careers,
              'ranking_type' => $rankingType,


            ],
            'availableCategories' => $availableCategories,
            'availableCareers'    => $availableCareers,
            'availableTrendCategories' => $availableTrendCategories,

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
                'reportes_analizados' => $totalReports,
                'actualizado' => now()->toDateTimeString(),
            ],
        ]
    );
}

public function reportsByTechnology(Request $request, int $technologyId)
{
    $year    = (int) $request->get('year', 2025);
    $period  = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    $reports = DB::table('technology_trends as tt')
        ->join(
            'technology_trend_technology as ttt',
            'ttt.technology_trend_id',
            '=',
            'tt.id'
        )
        ->where('ttt.technology_id', $technologyId)
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(tt.raw_data, '$.intent')) = 'technology_trend'")
        ->where('tt.year', $year)
        ->where('tt.quarter', $quarter)
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
        ->distinct()
        ->orderByDesc('tt.trend_score')
        ->get();

    return response()->json([
        'total' => $reports->count(),
        'data'  => $reports,
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
        $page    = (int) $request->get('page', 1);

        $jobs = DB::table('job_offers as j')
            ->join('technology_job as tj', 'tj.job_offer_id', '=', 'j.id')
            ->where('tj.technology_id', $technologyId)
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
