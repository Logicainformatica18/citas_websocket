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

    // 🔹 filtros multiselect
    $areas   = array_filter((array) $request->get('area', []));
    $careers = array_filter((array) $request->get('career', []));

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
       ÁREAS TECNOLÓGICAS DESDE BD
    ================================================== */
    $availableAreas = DB::table('certifications')
        ->whereNotNull('category')
        ->distinct()
        ->orderBy('category')
        ->pluck('category');

    /* ==================================================
       Vacantes analizadas reales
    ================================================== */
    $totalVacantesAnalizadas = DB::table('certification_job as cj')
        ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('cj.job_offer_id')
        ->count('cj.job_offer_id');

    /* ==================================================
       1. DEMANDA LABORAL
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
       2. TENDENCIAS TECNOLÓGICAS
    ================================================== */
    $trendSub = $this->getTrendSubquery($year, $period);

    $maxTrend = DB::query()
        ->fromSub($trendSub, 't')
        ->selectRaw('MAX(trend_raw)')
        ->value('MAX(trend_raw)') ?: 1;

    /* ==================================================
       3. QUERY BASE
    ================================================== */
    $query = DB::table('certifications as c')
        ->leftJoinSub($laborSub, 'labor', 'labor.certification_id', '=', 'c.id')
        ->leftJoinSub($trendSub, 'trend', 'trend.certification_id', '=', 'c.id');

    /* ==================================================
       FILTRO: ÁREAS
    ================================================== */
    if (!empty($areas)) {
        $query->whereIn('c.category', $areas);
    }

    /* ==================================================
       FILTRO: CARRERAS (MAPEADAS)
    ================================================== */
    if (!empty($careers)) {
        $careerMap = [
            'cloud'    => ['cloud'],
            'data_ai'  => ['data', 'ai'],
            'cyber'    => ['security', 'cloud'],
            'software' => ['cloud', 'ai', 'data'],
            'networks' => ['networks', 'cloud'],
        ];

        $mappedCategories = collect($careers)
            ->flatMap(fn ($c) => $careerMap[$c] ?? [])
            ->unique()
            ->toArray();

        if (!empty($mappedCategories)) {
            $query->whereIn('c.category', $mappedCategories);
        }
    }

    /* ==================================================
       4. SELECT FINAL
    ================================================== */
    $ranking = $query
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





        )
        ->orderByDesc('final_score')
        ->paginate(4)
        ->withQueryString();

    /* ==================================================
       5. Render
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
