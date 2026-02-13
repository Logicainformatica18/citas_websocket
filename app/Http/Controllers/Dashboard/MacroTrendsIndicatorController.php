<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
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

    /* =====================================================
       GUARDAR PONDERACIONES
    ===================================================== */
    public function storeWeights(Request $request)
    {
        $data = $request->validate([
            'labor_weight' => 'required|numeric|min:0|max:1',
            'trend_weight' => 'required|numeric|min:0|max:1',
        ]);

        if (round($data['labor_weight'] + $data['trend_weight'], 2) !== 1.00) {
            return back()->withErrors([
                'message' => 'Las ponderaciones deben sumar 1.00',
            ]);
        }

        DB::transaction(function () use ($data) {

            DB::table('ranking_weights')
                ->where('context', 'macro_trends')
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

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

        return back();
    }

    /* =====================================================
       EJECUTAR DESCUBRIMIENTO GLOBAL
    ===================================================== */
    public function runDiscover(Request $request)
    {
        $data = $request->validate([
            'year'   => 'required|integer',
            'period' => 'required|in:s1,s2',
        ]);

        $quarter = $data['period'] === 's1' ? 1 : 3;

        Artisan::call('trends:discover-global', [
            '--year'    => $data['year'],
            '--quarter' => $quarter,
        ]);

        return back()->with('success', 'Descubrimiento ejecutado correctamente.');
    }

    /* =====================================================
       LISTADO GENERAL (RANKING)
    ===================================================== */
  public function index(Request $request)
{
    $year   = (int) $request->get('year', now()->year);
    $period = $request->get('period', 's1');

    $range = $this->getPeriodRange($period, $year);

    /* =====================================================
       QUERY BASE
    ===================================================== */
    $baseQuery = DB::table('macro_trend_raw')
        ->where('year', $year)
        ->whereBetween('created_at', [
            $range['start'],
            $range['end']
        ]);

    /* =====================================================
       PAGINADO
    ===================================================== */
    $trends = $baseQuery
        ->select(
            'id',
            'trend_name',
            'description',
            'source_name',
            'source_title',
            'source_url',
            'source_type',
            'year',
            'quarter',
            'created_at'
        )
        ->orderByDesc('created_at')
        ->paginate(4);

    /* =====================================================
       MÉTRICAS HEADER
    ===================================================== */
    $totalSemestre = DB::table('macro_trend_raw')
        ->whereBetween('created_at', [
            $range['start'],
            $range['end']
        ])
        ->count();

    $totalYear = DB::table('macro_trend_raw')
        ->where('year', $year)
        ->count();

    $totalHistorico = DB::table('macro_trend_raw')->count();

    return Inertia::render(
        'DashboardMacroTrends/MacroTrendsIndicatorPage',
        [
            'ranking' => $trends,

            'meta' => [
                'year' => $year,
                'period' => $period,
                'periodo_label' =>
                    $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",

                'total_semestre'   => $totalSemestre,
                'total_year'       => $totalYear,
                'total_historico'  => $totalHistorico,

                'actualizado' => now()->toDateTimeString(),
            ],
        ]
    );
}



    /* =====================================================
       RANGO SEMESTRAL
    ===================================================== */
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
