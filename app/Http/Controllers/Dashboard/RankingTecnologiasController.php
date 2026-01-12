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
       PONDERACIONES
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

        return redirect()
            ->route('dashboard.ranking.technologies')
            ->with('success', 'Ponderaciones aplicadas correctamente');
    }

    /* ==================================================
       TENDENCIAS (DIRECTAS POR TECNOLOGÍA)
    ================================================== */
    private function getTrendSubquery(int $year, string $period)
    {
        $quarter = $period === 's1' ? 1 : 4;

        return DB::table('technology_trend_technology as ttt')
            ->join('technology_trends as tt', function ($join) use ($year, $quarter) {
                $join->on('tt.id', '=', 'ttt.technology_trend_id')
                     ->where('tt.year', $year)
                     ->where('tt.quarter', $quarter);
            })
            ->select(
                'ttt.technology_id',
                DB::raw('SUM(tt.trend_score * ttt.confidence_score) as trend_raw')
            )
            ->groupBy('ttt.technology_id');
    }

    /* ==================================================
       INDEX
    ================================================== */
    public function index(Request $request)
    {
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');

        $categories = array_filter((array) $request->get('category', []));

        $range = $this->getPeriodRange($period, $year);

        /* ===============================
           PONDERACIONES ACTIVAS
        =============================== */
        $activeWeights = Prueba::getActive('technologies');

        $laborWeight = (float) ($activeWeights?->labor_weight ?? 0.60);
        $trendWeight = (float) ($activeWeights?->trend_weight ?? 0.40);

        /* ===============================
           CATEGORÍAS DISPONIBLES
        =============================== */
        $availableCategories = DB::table('technology_categories')
            ->orderBy('name')
            ->pluck('name');

        /* ===============================
           VACANTES ANALIZADAS
        =============================== */
        $totalVacantesAnalizadas = DB::table('technology_job as tj')
            ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->distinct('tj.job_offer_id')
            ->count('tj.job_offer_id');

        /* ===============================
           DEMANDA LABORAL
        =============================== */
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

        /* ===============================
           TENDENCIAS
        =============================== */
        $trendSub = $this->getTrendSubquery($year, $period);

        $maxTrend = DB::query()
            ->fromSub($trendSub, 't')
            ->selectRaw('MAX(trend_raw)')
            ->value('MAX(trend_raw)') ?: 1;

        /* ===============================
           QUERY BASE
        =============================== */
        $query = DB::table('technologies as t')
            ->leftJoin('technology_categories as tc', 'tc.id', '=', 't.category_id')
            ->leftJoinSub($laborSub, 'labor', 'labor.technology_id', '=', 't.id')
            ->leftJoinSub($trendSub, 'trend', 'trend.technology_id', '=', 't.id')
            ->where('t.enabled', 1);

        if (!empty($categories)) {
            $query->whereIn('tc.name', $categories);
        }

        /* ===============================
           SELECT FINAL
        =============================== */
        $ranking = $query
            ->select(
                't.id',
                't.name',
                'tc.name as category',
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
            ->paginate(8)
            ->withQueryString();

        return Inertia::render(
            'DashboardRankingTechnologies/RankingTechnologiesPage',
            [
                'ranking' => $ranking,

                'filters' => [
                    'year'     => $year,
                    'period'   => $period,
                    'category' => $categories,
                ],

                'availableCategories' => $availableCategories,

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

    private function getPeriodRange(string $period, int $year): array
    {
        if ($period === 's1') {
            return ['start' => "$year-01-01", 'end' => "$year-06-30"];
        }

        return ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
}
