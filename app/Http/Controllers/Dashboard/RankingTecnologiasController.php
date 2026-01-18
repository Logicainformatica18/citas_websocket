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
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');
        $quarter = $period === 's1' ? 1 : 4;

        $range = $this->getPeriodRange($period, $year);

        /* ==================================================
           0.1 PONDERACIÓN GLOBAL
        ================================================== */
        try {
            $activeWeights = Prueba::getActive('technologies');
        } catch (\Throwable $e) {
            $activeWeights = null;
        }

        $laborWeight = (float) ($activeWeights?->labor_weight ?? 0.70);
        $trendWeight = (float) ($activeWeights?->trend_weight ?? 0.30);

        /* ==================================================
           CATEGORÍAS DISPONIBLES
        ================================================== */
        $availableCategories = DB::table('technology_categories')
            ->orderBy('name')
            ->pluck('name');

      $categories = array_filter((array) $request->get('category', []));
$careers    = array_filter((array) $request->get('career', []));


        /* ==================================================
           VACANTES ANALIZADAS
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
            ->where('tt.year', $year)
            ->where('tt.quarter', $quarter)
            ->select(
                'ttt.technology_id',
                DB::raw('COUNT(DISTINCT tt.id) as report_mentions')
            )
            ->groupBy('ttt.technology_id');

        $totalReports = DB::table('technology_trends')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->distinct('id')
            ->count('id');

        $totalReports = max($totalReports, 1);
        /* ==================================================
           3. QUERY BASE TECNOLOGÍAS
        ================================================== */
        $technologiesQuery = DB::table('technologies as t')
            ->leftJoinSub($laborSub, 'labor', 'labor.technology_id', '=', 't.id')
            ->leftJoinSub($reportsSub, 'reports', 'reports.technology_id', '=', 't.id')
            ->leftJoin('technology_categories as tc', 'tc.id', '=', 't.category_id')
            ->where('t.enabled', 1);

        if (!empty($categories)) {
            $technologiesQuery->whereIn('tc.name', $categories);
        }
if (!empty($careers)) {
    $technologiesQuery->whereExists(function ($q) use ($careers) {
        $q->select(DB::raw(1))
          ->from('course_technology as ct')
          ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
          ->join('careers as ca', 'ca.id', '=', 'cc.career_id')
          ->whereColumn('ct.technology_id', 't.id')
          ->whereIn('ca.slug', $careers);
    });
}

        $technologiesQuery = $technologiesQuery->select(
            't.id',
            't.name',
            'tc.name as category',

            DB::raw('COALESCE(labor.offers,0) as total_jobs'),

            DB::raw("
                ROUND(
                    (COALESCE(labor.offers,0) / {$maxLabor}) * 100,
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
                        ((COALESCE(labor.offers,0) / {$maxLabor}) * 100 * {$laborWeight})
                      + ((COALESCE(reports.report_mentions,0) / {$totalReports}) * 100 * {$trendWeight})
                    ),
                1) as final_score
            ")
        );

        $ranking = $technologiesQuery
            ->orderByDesc('final_score')
            ->paginate(10)
            ->withQueryString();


        
$availableCareers = DB::table('careers')
    ->where('active', 1)
    ->orderBy('name')
    ->get(['id', 'name', 'slug']);

        /* ==================================================
           RENDER
        ================================================== */
        return Inertia::render(
            'DashboardRankingTechnologies/RankingTecnologiasPage',
            [
                'ranking' => $ranking,
                'filters' => [
                    'year'     => $year,
                    'period'   => $period,
                    'category' => $categories,
                    'career' => $careers,

                ],
                'availableCategories' => $availableCategories,
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
                    'reportes_analizados' => $totalReports,
                    'actualizado' => now()->toDateTimeString(),
                ],
            ]
        );
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
