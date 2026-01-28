<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;

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
public function index(Request $request)
{
    /* ================= PARÁMETROS BASE ================= */
    $year    = (int) $request->get('year', 2025);
    $period  = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    $rankingType = $request->get('ranking_type');
    if (!in_array($rankingType, ['all', 'language', 'trend'])) {
        $rankingType = 'all';
    }

    $careers = array_filter((array) $request->get('career', []));

    // 🔥 Dominio de tendencias (formal)
    $trendDomain = $request->get('trend_domain', 'language');

    // 🔥 Filtro centralizado de tendencias de lenguajes
    $applyLanguageTrendFilter = function ($q) use ($trendDomain) {
        if ($trendDomain === 'language') {
            $q->whereIn('tt.topic_category', [
                'Lenguaje',
                'Lenguajes',
                'Language',
                'Programming Language',
            ]);
        }
    };

    $range = $this->getPeriodRange($period, $year);

    /* ================= PONDERACIONES ================= */
    try {
        $activeWeights = Prueba::getActive('languages');
    } catch (\Throwable $e) {
        $activeWeights = null;
    }

    $laborWeight = (float) ($activeWeights?->labor_weight ?? 0.7);
    $trendWeight = (float) ($activeWeights?->trend_weight ?? 0.3);

    /* ================= CATÁLOGOS ================= */
    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    /* ================= VACANTES ANALIZADAS ================= */
    $totalVacantesAnalizadas = DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('lj.job_offer_id')
        ->count('lj.job_offer_id');

    /* ==================================================
       1. SUBQUERY DEMANDA LABORAL (LENGUAJES)
    ================================================== */
    $laborSub = DB::table('language_job as lj')
        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->select(
            'lj.language_id',
            DB::raw('COUNT(DISTINCT lj.job_offer_id) as offers')
        )
        ->groupBy('lj.language_id');

    $maxLabor = DB::query()
        ->fromSub($laborSub, 'x')
        ->selectRaw('MAX(offers)')
        ->value('MAX(offers)') ?: 1;

    /* ==================================================
       2. TOTAL DE REPORTES DE TENDENCIA
    ================================================== */
    $totalReports = DB::table('technology_trends as tt')
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(tt.raw_data, '$.intent')) = 'technology_trend'")
        ->where('tt.year', $year)
        ->where('tt.quarter', $quarter)
        ->where(function ($q) use ($applyLanguageTrendFilter) {
            $applyLanguageTrendFilter($q);
        })
        ->distinct('tt.id')
        ->count('tt.id');

    $totalReports = max($totalReports, 1);

    /* ==================================================
       3. SUBQUERY REPORTES POR LENGUAJE
    ================================================== */
    $reportsSub = DB::table('technology_trends as tt')
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(tt.raw_data, '$.intent')) = 'technology_trend'")
        ->where('tt.year', $year)
        ->where('tt.quarter', $quarter)
        ->where(function ($q) use ($applyLanguageTrendFilter) {
            $applyLanguageTrendFilter($q);
        })
        ->select(
            DB::raw('LOWER(tt.topic_name) as language_name'),
            DB::raw('COUNT(DISTINCT tt.id) as report_mentions')
        )
        ->groupBy(DB::raw('LOWER(tt.topic_name)'));

    /* ==================================================
       4. LENGUAJES ISIL (LABOR + TREND)
    ================================================== */
    $languagesQuery = DB::table('languages as l')
        ->leftJoinSub($laborSub, 'labor', 'labor.language_id', '=', 'l.id')
        ->leftJoinSub(
            $reportsSub,
            'reports',
            DB::raw('LOWER(l.name)'),
            '=',
            'reports.language_name'
        )
        ->where('l.enabled', 1)
        ->whereExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('course_language as cl')
              ->join('career_course as cc', 'cc.course_id', '=', 'cl.course_id')
              ->whereColumn('cl.language_id', 'l.id');
        });

    if (!empty($careers)) {
        $languagesQuery->whereExists(function ($q) use ($careers) {
            $q->select(DB::raw(1))
              ->from('course_language as cl')
              ->join('career_course as cc', 'cc.course_id', '=', 'cl.course_id')
              ->join('careers as ca', 'ca.id', '=', 'cc.career_id')
              ->whereColumn('cl.language_id', 'l.id')
              ->whereIn('ca.slug', $careers);
        });
    }

    $languagesQuery = $languagesQuery->select(
        DB::raw("'language' as entity_type"),
        DB::raw("
            CASE
              WHEN EXISTS (
                SELECT 1
                FROM course_language cl
                JOIN career_course cc ON cc.course_id = cl.course_id
                WHERE cl.language_id = l.id
              )
              THEN 1 ELSE 0
            END as is_isil
        "),
        DB::raw('0 as is_real_trend'),

        'l.id',
        'l.name',
        DB::raw('NULL as category'),

        DB::raw('COALESCE(labor.offers,0) as total_jobs'),
        DB::raw('COALESCE(reports.report_mentions,0) as trend_reports'),

        DB::raw("
            ROUND(
                (LOG(COALESCE(labor.offers,0) + 1) / LOG({$maxLabor} + 1)) * 100,
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
                    ((LOG(COALESCE(labor.offers,0) + 1) / LOG({$maxLabor} + 1)) * 100 * {$laborWeight})
                  + ((COALESCE(reports.report_mentions,0) / {$totalReports}) * 100 * {$trendWeight})
                ),
            1) as final_score
        "),

        DB::raw('NULL as year'),
        DB::raw('NULL as quarter'),
        DB::raw('NULL as source_title'),
        DB::raw('NULL as source_url'),
        DB::raw('NULL as source_type')
    );

    /* ==================================================
       5. TENDENCIAS PURAS DE LENGUAJES
    ================================================== */
    $trendsQuery = DB::table('technology_trends as tt')
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(tt.raw_data, '$.intent')) = 'technology_trend'")
        ->where('tt.year', $year)
        ->where('tt.quarter', $quarter)
        ->where(function ($q) use ($applyLanguageTrendFilter) {
            $applyLanguageTrendFilter($q);
        })
        ->select(
            DB::raw("'trend' as entity_type"),
            DB::raw('0 as is_isil'),
            DB::raw('1 as is_real_trend'),

            'tt.id',
            'tt.topic_name as name',
            'tt.topic_category as category',

            DB::raw('0 as total_jobs'),
            DB::raw('1 as trend_reports'),

            DB::raw('0 as labor_score'),
            'tt.trend_score as trend_score',

            DB::raw("ROUND(tt.trend_score * {$trendWeight},1) as final_score"),

            'tt.year',
            'tt.quarter',
            'tt.source_title',
            'tt.source_url',
            'tt.source_type'
        );

    /* ==================================================
       6. UNION + FILTRO
    ================================================== */
    if ($rankingType === 'language') {
        $rankingBase = DB::query()->fromSub($languagesQuery, 'ranking');
    } elseif ($rankingType === 'trend') {
        $rankingBase = DB::query()->fromSub($trendsQuery, 'ranking');
    } else {
        $rankingBase = DB::query()->fromSub($languagesQuery, 'ranking');
    }

    $ranking = $rankingBase
        ->orderByDesc('final_score')
        ->paginate(10)
        ->withQueryString();

    /* ================= RENDER ================= */
    return Inertia::render(
        'DashboardRankingLanguages/RankingLenguajesPage',
        [
            'ranking' => $ranking,
            'filters' => [
                'year'         => $year,
                'period'       => $period,
                'career'       => $careers,
                'ranking_type' => $rankingType,
                'trend_domain' => $trendDomain,
            ],
            'availableCareers' => $availableCareers,
            'weights' => [
                'laborWeight' => round($laborWeight * 100, 1),
                'trendWeight' => round($trendWeight * 100, 1),
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


    /* ==================================================
       JOBS POR LENGUAJE (MODAL LABORAL)
    ================================================== */
    public function jobsByLanguage(Request $request, int $languageId)
    {
        $perPage = min((int) $request->get('per_page', 10), 50);
        $page = (int) $request->get('page', 1);

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
       REPORTES / TENDENCIAS POR LENGUAJE
    ================================================== */
    public function reportsByLanguage(Request $request, int $languageId)
    {
        $year = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');
        $quarter = $period === 's1' ? 1 : 4;

        $language = DB::table('languages')
            ->where('id', $languageId)
            ->select('name')
            ->first();

        if (!$language) {
            return response()->json(['total' => 0, 'data' => []]);
        }

        $reports = DB::table('technology_trends as tt')
            ->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(tt.raw_data, '$.intent')) = 'technology_trend'"
            )
            ->where('tt.year', $year)
            ->where('tt.quarter', $quarter)
            ->where(function ($q) use ($language) {
                $q->where('tt.topic_category', 'LIKE', '%Lenguaje%')
                    ->orWhere('tt.topic_category', 'LIKE', '%Lenguajes%')
                    ->orWhere('tt.topic_category', 'LIKE', '%Language%')
                    ->orWhere('tt.topic_category', 'LIKE', '%' . $language->name . '%');
            })
            ->select(
                'tt.id',
                'tt.topic_name',
                'tt.topic_category',
                'tt.trend_score',
                'tt.year',
                'tt.quarter',
                'tt.source_title',
                'tt.source_url',
                'tt.source_type'
            )
            ->orderByDesc('tt.trend_score')
            ->distinct()
            ->get();

        return response()->json([
            'total' => $reports->count(),
            'data' => $reports,
        ]);
    }

    /* ==================================================
       UTILIDADES
    ================================================== */
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
