<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Prueba;
use App\Services\Ranking\MacroTrendScoreService;

class MacroTrendsIndicatorController extends Controller
{
protected MacroTrendScoreService $service;
public function __construct(MacroTrendScoreService $service)
{
    $this->service = $service;
}
public function storeWeights(Request $request)
{
    /* =====================================================
       1. VALIDACIÓN
    ===================================================== */
    $data = $request->validate([
        'labor_weight' => 'required|numeric|min:0|max:1',
        'trend_weight' => 'required|numeric|min:0|max:1',
    ]);

    if (round($data['labor_weight'] + $data['trend_weight'], 2) !== 1.00) {
        return back()->withErrors([
            'message' => 'Las ponderaciones deben sumar 1.00',
        ]);
    }

    /* =====================================================
       2. TRANSACCIÓN
    ===================================================== */
    DB::transaction(function () use ($data) {

        // 🔹 Desactivar ponderación activa anterior
        DB::table('ranking_weights')
            ->where('context', 'macro_trends')
            ->where('is_active', 1)
            ->update(['is_active' => 0]);

        // 🔹 Insertar nueva ponderación
        DB::table('ranking_weights')->insert([
            'labor_weight' => $data['labor_weight'],
            'trend_weight' => $data['trend_weight'],
            'context' => 'macro_trends',
            'is_active' => 1,
            'applied_at' => now(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    /* =====================================================
       3. RESPUESTA
    ===================================================== */
    return back();
}
    /* =====================================================
       LISTADO GENERAL (CARDS)
    ===================================================== */
  public function index(Request $request)
    {
        /* =====================================================
           0. CONTEXTO
        ===================================================== */
        $year   = (int) $request->get('year', now()->year);
        $period = $request->get('period', 's1');

        $range = $this->getPeriodRange($period, $year);

        $weights = Prueba::getActive('macro_trends');

        $laborWeight = (float) ($weights?->labor_weight ?? 0.6);
        $trendWeight = (float) ($weights?->trend_weight ?? 0.4);

        /* =====================================================
           SUBQUERY REPORTES
        ===================================================== */
        $reportSub = DB::table('macro_trend_entity_trend as mtet')
            ->join('entity_trends as et', 'et.id', '=', 'mtet.entity_trend_id')
            ->whereBetween('et.created_at', [
                $range['start'],
                $range['end']
            ])
            ->select(
                'mtet.macro_trend_id',
                DB::raw('COUNT(DISTINCT et.id) as trend_reports')
            )
            ->groupBy('mtet.macro_trend_id');

        /* =====================================================
           SUBQUERY LABOR (SERVICIO)
        ===================================================== */
        $laborSub = $this->service->getLaborSubquery($range, $year);

        $maxLabor = max(
            DB::query()->fromSub($laborSub, 'x')->max('total_jobs') ?? 0,
            1
        );

        $maxReports = max(
            DB::query()->fromSub($reportSub, 'r')->max('trend_reports') ?? 0,
            1
        );

        /* =====================================================
           RANKING
        ===================================================== */
        $rankingQuery = DB::table('macro_trends as m')
            ->leftJoinSub($laborSub, 'labor', 'labor.macro_id', '=', 'm.id')
            ->leftJoinSub($reportSub, 'reports', 'reports.macro_trend_id', '=', 'm.id')
            ->where('m.year', $year)
            ->select(
                'm.id',
                'm.name',
                'm.description',

                DB::raw('COALESCE(labor.total_jobs,0) as total_jobs'),
                DB::raw('COALESCE(reports.trend_reports,0) as trend_reports'),

                DB::raw("
                    ROUND(
                        (COALESCE(labor.total_jobs,0) / {$maxLabor}) * 100,
                    1) as labor_score
                "),

                DB::raw("
                    ROUND(
                        (COALESCE(reports.trend_reports,0) / {$maxReports}) * 100,
                    1) as trend_score
                "),

                DB::raw("
                    ROUND(
                        (
                            ((COALESCE(labor.total_jobs,0) / {$maxLabor}) * 100 * {$laborWeight})
                            +
                            ((COALESCE(reports.trend_reports,0) / {$maxReports}) * 100 * {$trendWeight})
                        ),
                    1) as final_score
                ")
            )
            ->orderByDesc('final_score');

        $ranking = $rankingQuery->paginate(6);

        /* =====================================================
           TOTALES GLOBALES (SERVICIO)
        ===================================================== */
        $totals = $this->service->getGlobalTotals($range, $year);

        /* =====================================================
           RENDER
        ===================================================== */
   return Inertia::render(
    'DashboardMacroTrends/MacroTrendsIndicatorPage',
    [
        'ranking' => $ranking,

        'weights' => [
            'laborWeight'  => round($laborWeight * 100, 1),
            'trendsWeight' => round($trendWeight * 100, 1),
        ],

        'meta' => [
            'year' => $year,
            'period' => $period,
            'periodo_label' =>
                $period === 's1'
                    ? "Semestre 1 – Enero a Junio {$year}"
                    : "Semestre 2 – Julio a Diciembre {$year}",
            'vacantes_analizadas' => $totals['jobs'],
            'reportes_analizados' => $totals['reports'],
            'actualizado' => now()->toDateTimeString(),
        ],
    ]
);

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


    /* =====================================================
       DETALLE DE UNA MACRO
    ===================================================== */
    public function detail($id)
    {
        $macro = DB::table('macro_trends')
            ->where('id', $id)
            ->first();

        if (!$macro) {
            abort(404);
        }

        $reportes = DB::table('macro_trend_entity_trend as mtet')
            ->join('entity_trends as et', 'et.id', '=', 'mtet.entity_trend_id')
            ->where('mtet.macro_trend_id', $id)
            ->select(
                'et.id',
                'et.trend_name',
                'et.source_url',
                'et.created_at'
            )
            ->orderByDesc('et.created_at')
            ->paginate(10);

        $jobs = DB::table('macro_trend_job as mtj')
            ->join('job_offers as j', 'j.id', '=', 'mtj.job_offer_id')
            ->where('mtj.macro_trend_id', $id)
            ->select(
                'j.id',
                'j.title',
                'j.company',
                'j.region',
                'j.published_at'
            )
            ->orderByDesc('j.published_at')
            ->paginate(10);

        return Inertia::render(
            'DashboardMacroTrends/MacroTrendDetailPage',
            [
                'macro' => $macro,
                'reportes' => $reportes,
                'jobs' => $jobs,
            ]
        );
    }

    /* =====================================================
       API SOLO REPORTES
    ===================================================== */
    public function getReports($id)
    {
        return DB::table('macro_trend_entity_trend as mtet')
            ->join('entity_trends as et', 'et.id', '=', 'mtet.entity_trend_id')
            ->where('mtet.macro_trend_id', $id)
            ->select(
                'et.trend_name',
                'et.source_url',
                'et.created_at'
            )
            ->orderByDesc('et.created_at')
            ->get();
    }

    /* =====================================================
       API SOLO JOBS
    ===================================================== */
    public function getJobs($id)
    {
        return DB::table('macro_trend_job as mtj')
            ->join('job_offers as j', 'j.id', '=', 'mtj.job_offer_id')
            ->where('mtj.macro_trend_id', $id)
            ->select(
                'j.title',
                'j.company',
                'j.region',
                'j.published_at'
            )
            ->orderByDesc('j.published_at')
            ->get();
    }
}
