<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MacroTrendsIndicatorController extends Controller
{
    public function index(Request $request)
    {
        /* =====================================================
           0️⃣ Parámetros base
        ===================================================== */
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's1');
        $quarter = $period === 's1' ? 1 : 4;

        $regions = array_filter((array) $request->get('region', []));
        $careers = array_filter((array) $request->get('career', []));

        $range = $this->getPeriodRange($period, $year);

        $laborWeight = 0.60;
        $trendWeight = 0.40;

        /* =====================================================
           1️⃣ MÉTRICAS PARA HEADER
        ===================================================== */
        $vacantesAnalizadas = DB::table('job_offers')
            ->when($regions, fn ($q) => $q->whereIn('region', $regions))
            ->whereBetween('published_at', [$range['start'], $range['end']])
            ->count('id');

        $reportesAnalizados = DB::table('technology_trends')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->count('id');

        /* =====================================================
           2️⃣ DEMANDA LABORAL (lenguajes + tecnologías)
        ===================================================== */
        $laborBase = DB::query()->fromSub(function ($q) use ($range, $regions, $careers) {

            /* ======================
               Lenguajes
            ====================== */
            $q->select(
                DB::raw('LOWER(l.name) as term'),
                DB::raw('COUNT(DISTINCT lj.job_offer_id) as labor_mentions')
            )
            ->from('languages as l')
            ->join('language_job as lj', 'lj.language_id', '=', 'l.id')
            ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->when($regions, fn ($qq) => $qq->whereIn('j.region', $regions))

            ->when($careers, function ($qq) use ($careers) {
                $qq->whereExists(function ($sq) use ($careers) {
                    $sq->select(DB::raw(1))
                        ->from('course_language as cl')
                        ->join('career_course as cc', 'cc.course_id', '=', 'cl.course_id')
                        ->join('careers as ca', 'ca.id', '=', 'cc.career_id')
                        ->whereColumn('cl.language_id', 'l.id')
                        ->whereIn('ca.slug', $careers);
                });
            })

            ->groupBy('l.id', 'l.name')

            ->unionAll(

                /* ======================
                   Tecnologías
                ====================== */
                DB::table('technologies as t')
                    ->select(
                        DB::raw('LOWER(t.name) as term'),
                        DB::raw('COUNT(DISTINCT tj.job_offer_id) as labor_mentions')
                    )
                    ->join('technology_job as tj', 'tj.technology_id', '=', 't.id')
                    ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
                    ->whereBetween('j.published_at', [$range['start'], $range['end']])
                    ->when($regions, fn ($qq) => $qq->whereIn('j.region', $regions))

                    ->when($careers, function ($qq) use ($careers) {
                        $qq->whereExists(function ($sq) use ($careers) {
                            $sq->select(DB::raw(1))
                                ->from('course_technology as ct')
                                ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
                                ->join('careers as ca', 'ca.id', '=', 'cc.career_id')
                                ->whereColumn('ct.technology_id', 't.id')
                                ->whereIn('ca.slug', $careers);
                        });
                    })

                    ->groupBy('t.id', 't.name')
            );

        }, 'labor_base');

        $laborAgg = DB::query()
            ->fromSub($laborBase, 'x')
            ->select(
                'term',
                DB::raw('SUM(labor_mentions) as labor_mentions')
            )
            ->groupBy('term');

        $maxLabor = max(
            DB::query()->fromSub($laborAgg, 'm')->max('labor_mentions') ?? 0,
            1
        );

        /* =====================================================
           3️⃣ REPORTES DE TENDENCIAS (GLOBAL)
        ===================================================== */
        $reportsSub = DB::table('technology_trends as tt')
            ->join('technology_trend_technology as ttt', 'ttt.technology_trend_id', '=', 'tt.id')
            ->join('technologies as t', 't.id', '=', 'ttt.technology_id')
            ->where('tt.year', $year)
            ->where('tt.quarter', $quarter)
            ->select(
                DB::raw('LOWER(t.name) as term'),
                DB::raw('COUNT(DISTINCT tt.id) as report_mentions')
            )
            ->groupBy('t.name');

        $maxReports = max(
            DB::query()->fromSub($reportsSub, 'r')->max('report_mentions') ?? 0,
            1
        );
// Opciones disponibles
$availableRegions = DB::table('job_offers')
    ->select('region')
    ->whereNotNull('region')
    ->distinct()
    ->orderBy('region')
    ->pluck('region');

$availableCareers = DB::table('careers')
    ->select('id', 'name', 'slug')
    ->orderBy('name')
    ->get();

        /* =====================================================
           4️⃣ MACRO-TENDENCIAS (JOIN REAL)
        ===================================================== */
        $ranking = DB::query()
            ->fromSub($laborAgg, 'labor')
            ->joinSub($reportsSub, 'reports', 'reports.term', '=', 'labor.term')
            ->select(
                'labor.term',

                DB::raw("ROUND((labor.labor_mentions / {$maxLabor}) * 100, 1) as labor_score"),
                DB::raw("ROUND((reports.report_mentions / {$maxReports}) * 100, 1) as trend_score"),

                DB::raw("
                    ROUND(
                        ((labor.labor_mentions / {$maxLabor}) * 100 * {$laborWeight})
                      + ((reports.report_mentions / {$maxReports}) * 100 * {$trendWeight}),
                        1
                    ) as final_score
                ")
            )
            ->orderByDesc('final_score')
            ->paginate(5)
            ->withQueryString();

        /* =====================================================
           5️⃣ Render
        ===================================================== */
        return Inertia::render(
            'DashboardMacroTrends/MacroTrendsIndicatorPage',
            [
                'ranking' => $ranking,

                'filters' => [
                    'year'   => $year,
                    'period' => $period,
                    'region' => $regions,
                    'career' => $careers,
                ],
                 'regions' => $availableRegions,
        'careers' => $availableCareers,

                'meta' => [
                    'year'   => $year,
                    'period' => $period,
                    'periodo_label' => $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",

                    'vacantes_analizadas' => $vacantesAnalizadas,
                    'reportes_analizados' => $reportesAnalizados,

                    'weights' => [
                        'labor' => 60,
                        'trend' => 40,
                    ],

                    'actualizado' => now()->toDateTimeString(),
                ],
            ]
        );
    }

    private function getPeriodRange(string $period, int $year): array
    {
        return $period === 's1'
            ? ['start' => "{$year}-01-01", 'end' => "{$year}-06-30"]
            : ['start' => "{$year}-07-01", 'end' => "{$year}-12-31"];
    }
}
