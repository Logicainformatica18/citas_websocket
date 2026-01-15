<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Prueba;
class RankingCertificacionesController extends Controller
{
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
 return redirect()
    ->route('dashboard.ranking.certificaciones')
    ->with('success', 'Ponderaciones aplicadas correctamente');

}
    private function getTrendSubquery(int $year, string $period)
{
    $quarter = $period === 's1' ? 1 : 4; // tu convención real

    return DB::table('certifications as c')
        ->leftJoin('certification_course as cc', 'cc.certification_id', '=', 'c.id')
        ->leftJoin('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
        ->leftJoin('technology_trend_technology as ttt', 'ttt.technology_id', '=', 'ct.technology_id')
        ->leftJoin('technology_trends as tt', function ($join) use ($year, $quarter) {
            $join->on('tt.id', '=', 'ttt.technology_trend_id')
                 ->where('tt.year', $year)
                 ->where('tt.quarter', $quarter);
        })
        ->select(
            'c.id as certification_id',
            DB::raw('SUM(tt.trend_score * ttt.confidence_score) as trend_raw')
        )
        ->groupBy('c.id');
}

public function index(Request $request)
{
    /* ==================================================
       0. Parámetros base
    ================================================== */
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    $areas   = array_filter((array) $request->get('area', []));
    $careers = $request->filled('career')
        ? array_filter((array) $request->career)
        : [];

    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    $range = $this->getPeriodRange($period, $year);

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
    $trendSub = $this->getTrendSubquery($year, $period);

    $maxTrend = DB::query()
        ->fromSub($trendSub, 't')
        ->selectRaw('MAX(trend_raw)')
        ->value('MAX(trend_raw)') ?: 1;

    /* ==================================================
       3. QUERY CERTIFICACIONES (BASE)
    ================================================== */
    $certificationsQuery = DB::table('certifications as c')
        ->leftJoinSub($laborSub, 'labor', 'labor.certification_id', '=', 'c.id')
        ->leftJoinSub($trendSub, 'trend', 'trend.certification_id', '=', 'c.id');

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
        DB::raw('COALESCE(labor.offers,0) as total_jobs'),
        DB::raw("ROUND((COALESCE(labor.offers,0)/{$maxLabor})*100,1) as labor_score"),
        DB::raw("ROUND((COALESCE(trend.trend_raw,0)/{$maxTrend})*100,1) as trend_score"),
        DB::raw("
            ROUND(
                (
                    ((COALESCE(labor.offers,0)/{$maxLabor})*100*{$laborWeight})
                  + ((COALESCE(trend.trend_raw,0)/{$maxTrend})*100*{$trendWeight})
                ),1
            ) as final_score
        ")
    );

    /* ==================================================
       4. QUERY TENDENCIAS (COMO ITEMS DE RANKING)
    ================================================== */
$trendsQuery = DB::table('technology_trends as tt')
    ->leftJoin('technology_trend_job as ttj', 'ttj.technology_trend_id', '=', 'tt.id')
    ->leftJoin('job_offers as j', function ($join) use ($range) {
        $join->on('j.id', '=', 'ttj.job_offer_id')
             ->whereBetween('j.published_at', [$range['start'], $range['end']]);
    })
    ->where('tt.year', $year)
    ->where('tt.quarter', $quarter)
    ->groupBy('tt.id', 'tt.topic_name', 'tt.topic_category', 'tt.trend_score')
    ->select(
        DB::raw("'trend' as entity_type"),
        'tt.id as id',
        'tt.topic_name as name',
        DB::raw('NULL as vendor'),
        DB::raw('NULL as level'),
        'tt.topic_category as category',

        // 👇 mercado laboral REAL
        DB::raw('COUNT(DISTINCT ttj.job_offer_id) as total_jobs'),

        // 👇 score laboral normalizado (como certificaciones)
        DB::raw("
            ROUND(
                (COUNT(DISTINCT ttj.job_offer_id) / {$maxLabor}) * 100,
                1
            ) as labor_score
        "),

        DB::raw('tt.trend_score as trend_score'),

        // 👇 misma fórmula 70/30
        DB::raw("
            ROUND(
                (
                    ((COUNT(DISTINCT ttj.job_offer_id) / {$maxLabor}) * 100 * {$laborWeight})
                  + (tt.trend_score * {$trendWeight})
                ),
                1
            ) as final_score
        ")
    );



    // $trendsQuery = DB::table('technology_trends as tt')
    //     ->leftJoin('technology_trend_job as ttj', 'ttj.technology_trend_id', '=', 'tt.id')
    //     ->leftJoin('job_offers as j', function ($join) use ($range) {
    //         $join->on('j.id', '=', 'ttj.job_offer_id')
    //              ->whereBetween('j.published_at', [$range['start'], $range['end']]);
    //     })
    //     ->where('tt.topic_category', 'like', 'Certificaciones%')
    //     ->where('tt.year', $year)
    //     ->where('tt.quarter', $quarter)
    //     ->groupBy('tt.id', 'tt.topic_name', 'tt.topic_category', 'tt.trend_score')
    //     ->select(
    //         DB::raw("'trend' as entity_type"),
    //         'tt.id as id',
    //         'tt.topic_name as name',
    //         DB::raw('NULL as vendor'),
    //         DB::raw('NULL as level'),
    //         'tt.topic_category as category',
    //         DB::raw('COUNT(DISTINCT ttj.job_offer_id) as total_jobs'),
    //         DB::raw('0 as labor_score'),
    //         DB::raw('tt.trend_score as trend_score'),
    //         DB::raw('tt.trend_score as final_score')
    //     );

    /* ==================================================
       5. UNION + PAGINACIÓN
    ================================================== */
    $ranking = DB::query()
        ->fromSub(
            $certificationsQuery->unionAll($trendsQuery),
            'ranking'
        )
        ->orderByDesc('final_score')
        ->paginate(4)
        ->withQueryString();

    /* ==================================================
       6. Render
    ================================================== */
    return Inertia::render(
        'DashboardRankingCertificaciones/RankingCertificacionesPage',
        [
            'ranking' => $ranking,
            'filters' => [
                'year'   => $year,
                'period' => $period,
                'area'   => $areas,
                'career' => $careers,
            ],
            'availableAreas' => $availableAreas,
            'availableCareers' => $availableCareers,
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
                'actualizado' => now()->toDateTimeString(),
            ],
        ]
    );
}


private function baseCertificationRankingQuery(
    int $year,
    string $period,
    float $laborWeight,
    float $trendWeight
) {
    $quarter = $period === 's1' ? 1 : 4;

    $laborSub = DB::table('certification_job as cj')
        ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
        ->whereYear('j.published_at', $year)
        ->select(
            'cj.certification_id',
            DB::raw('COUNT(DISTINCT cj.job_offer_id) as offers')
        )
        ->groupBy('cj.certification_id');

    $maxLabor = DB::query()
        ->fromSub($laborSub, 'x')
        ->selectRaw('MAX(offers)')
        ->value('MAX(offers)') ?: 1;

    $trendSub = $this->getTrendSubquery($year, $period);

    $maxTrend = DB::query()
        ->fromSub($trendSub, 't')
        ->selectRaw('MAX(trend_raw)')
        ->value('MAX(trend_raw)') ?: 1;

    return DB::table('certifications as c')
        ->leftJoinSub($laborSub, 'labor', 'labor.certification_id', '=', 'c.id')
        ->leftJoinSub($trendSub, 'trend', 'trend.certification_id', '=', 'c.id')
        ->select(
            'c.id',
            'c.name',
            'c.vendor',
            'c.level',
            'c.category',
            DB::raw('COALESCE(labor.offers,0) as total_jobs'),
            DB::raw("ROUND((COALESCE(labor.offers,0)/{$maxLabor})*100,1) as labor_score"),
            DB::raw("ROUND((COALESCE(trend.trend_raw,0)/{$maxTrend})*100,1) as trend_score"),
            DB::raw("
                ROUND(
                    (
                        ((COALESCE(labor.offers,0)/{$maxLabor})*100*{$laborWeight})
                      + ((COALESCE(trend.trend_raw,0)/{$maxTrend})*100*{$trendWeight})
                    ),1
                ) as final_score
            ")
        );
}


public function trendingCertifications(Request $request)
{
    $year   = (int) $request->get('year', now()->year);
    $period = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    // 🔹 ponderaciones activas (las mismas del index)
    $weights = Prueba::getActive('certifications');
    $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
    $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

    $range = $this->getPeriodRange($period, $year);

    $items = DB::table('technology_trends as tt')
        ->leftJoin('technology_trend_job as ttj', 'ttj.technology_trend_id', '=', 'tt.id')
        ->leftJoin('job_offers as j', function ($join) use ($range) {
            $join->on('j.id', '=', 'ttj.job_offer_id')
                 ->whereBetween('j.published_at', [$range['start'], $range['end']]);
        })
        ->where('tt.topic_category', 'like', 'Certificaciones%')
        ->where('tt.year', $year)
        ->where('tt.quarter', $quarter)
        ->groupBy(
            'tt.id',
            'tt.topic_name',
            'tt.topic_category',
            'tt.trend_score'
        )
        ->select(
            'tt.id as trend_id',
            'tt.topic_name as name',
            'tt.topic_category',
            'tt.trend_score',
            DB::raw('COUNT(DISTINCT ttj.job_offer_id) as job_offers'),
            DB::raw("
                ROUND(
                    (
                        (tt.trend_score * {$trendWeight})
                      + (LOG(COUNT(DISTINCT ttj.job_offer_id) + 1) * 10 * {$laborWeight})
                    ),
                    1
                ) as final_score
            ")
        )
        ->havingRaw('job_offers > 0')
        ->havingRaw('final_score > 0')
        ->orderByDesc('final_score')
        ->limit(8)
        ->get();

    return response()->json([
        'year'   => $year,
        'period'=> $period,
        'items' => $items,
        'empty' => $items->isEmpty(),
    ]);
}






public function jobsByCertification(Request $request, int $certificationId)
{
    // ===============================
    // 1. Parámetros controlados
    // ===============================
    $perPage = min((int) $request->get('per_page', 10), 50); // límite de seguridad
    $page    = (int) $request->get('page', 1);

    // ===============================
    // 2. Query base (LIVIANA)
    // ===============================
    $jobs = DB::table('job_offers as j')
        ->join('certification_job as cj', 'cj.job_offer_id', '=', 'j.id')
        ->where('cj.certification_id', $certificationId)
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
            'j.url' // 👈 URL original
        )
        ->orderByDesc('j.published_at')
        ->paginate(
            $perPage,
            ['*'],
            'page',
            $page
        );

    // ===============================
    // 3. Respuesta JSON paginada
    // ===============================
    return response()->json($jobs);
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
