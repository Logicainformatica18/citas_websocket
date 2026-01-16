<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\Ranking\RankingWeightService;
use App\Services\Ranking\RankingScoreService;

class RankingCertificacionesController extends Controller
{
    public function __construct(
        protected RankingWeightService $weightService,
        protected RankingScoreService $scoreService
    ) {}

    /* ==================================================
       GUARDAR PONDERACIONES
    ================================================== */
    public function storeWeights(Request $request)
    {
        $data = $request->validate([
            'labor_weight' => 'required|numeric|min:0|max:1',
            'trend_weight' => 'required|numeric|min:0|max:1',
        ]);

        $this->weightService->store(
            'certifications',
            $data['labor_weight'],
            $data['trend_weight'],
            auth()->id()
        );

        return redirect()->back();
    }

    /* ==================================================
       RANKING PRINCIPAL
    ================================================== */
    public function index(Request $request)
    {
        /* ===============================
           0. Parámetros base
        =============================== */
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');
        $quarter = $period === 's1' ? 1 : 4;

        $rankingType = $request->get('ranking_type', 'all');
        $trendCategory = $request->get('trend_category');

        $areas   = array_filter((array) $request->get('area', []));
        $careers = $request->filled('career')
            ? array_filter((array) $request->career)
            : [];

        $range = $this->getPeriodRange($period, $year);

        /* ===============================
           1. Pesos activos
        =============================== */
        $weights = $this->weightService->getActive('certifications');

        $laborWeight = (float) ($weights->labor_weight ?? 0.70);
        $trendWeight = (float) ($weights->trend_weight ?? 0.30);

        /* ===============================
           2. Subquery demanda laboral
        =============================== */
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

        /* ===============================
           3. Subquery tendencias
        =============================== */
        $reportsSub = $this->getCertificationReportsSubquery($year, $quarter);
        $totalReports = max(
            $this->getTrendReportsCount($year, $quarter),
            1
        );

        /* ===============================
           4. Query certificaciones
        =============================== */
        $certificationsQuery = DB::table('certifications as c')
            ->leftJoinSub($laborSub, 'labor', 'labor.certification_id', '=', 'c.id')
            ->leftJoinSub($reportsSub, 'reports', 'reports.certification_id', '=', 'c.id');

        if (!empty($areas)) {
            $certificationsQuery->whereIn('c.category', $areas);
        }

        if (!empty($careers)) {
            $certificationsQuery->whereExists(function ($q) use ($careers) {
                $q->select(DB::raw(1))
                  ->from('certification_course as cc')
                  ->join('career_course as crc', 'crc.course_id', '=', 'cc.course_id')
                  ->join('careers as ca', 'ca.id', '=', 'crc.career_id')
                  ->whereColumn('cc.certification_id', 'c.id')
                  ->whereIn('ca.slug', $careers);
            });
        }

        $certificationsQuery->select(
            DB::raw("'certification' as entity_type"),
            'c.id',
            'c.name',
            'c.vendor',
            'c.level',
            'c.category',
            DB::raw('COALESCE(labor.offers,0) as total_jobs'),
            DB::raw("ROUND((COALESCE(labor.offers,0)/{$maxLabor})*100,1) as labor_score"),
            DB::raw("ROUND((COALESCE(reports.report_mentions,0)/{$totalReports})*100,1) as trend_score"),
            DB::raw("
                ROUND(
                    ((COALESCE(labor.offers,0)/{$maxLabor})*100*{$laborWeight})
                  + ((COALESCE(reports.report_mentions,0)/{$totalReports})*100*{$trendWeight}),
                1) as final_score
            ")
        );

        /* ===============================
           5. Query tendencias
        =============================== */
        $trendsQuery = DB::table('technology_trends as tt')
            ->leftJoin('technology_trend_job as ttj', 'ttj.technology_trend_id', '=', 'tt.id')
            ->leftJoin('job_offers as j', function ($join) use ($range) {
                $join->on('j.id', '=', 'ttj.job_offer_id')
                     ->whereBetween('j.published_at', [$range['start'], $range['end']]);
            })
            ->where('tt.topic_category', 'like', 'Certificaciones%')
            ->where('tt.year', $year)
            ->where('tt.quarter', $quarter);

        if ($rankingType === 'trend' && $trendCategory) {
            $trendsQuery->where('tt.topic_category', $trendCategory);
        }

        $trendsQuery
            ->groupBy('tt.id', 'tt.topic_name', 'tt.topic_category', 'tt.trend_score')
            ->select(
                DB::raw("'trend' as entity_type"),
                'tt.id as id',
                'tt.topic_name as name',
                DB::raw('NULL as vendor'),
                DB::raw('NULL as level'),
                'tt.topic_category as category',
                DB::raw('COUNT(DISTINCT ttj.job_offer_id) as total_jobs'),
                DB::raw("ROUND(LEAST((COUNT(DISTINCT ttj.job_offer_id)/{$maxLabor})*100,100),1) as labor_score"),
                DB::raw('tt.trend_score as trend_score'),
                DB::raw("
                    ROUND(
                        (LEAST((COUNT(DISTINCT ttj.job_offer_id)/{$maxLabor})*100,100)*{$laborWeight})
                      + (tt.trend_score*{$trendWeight}),
                    1) as final_score
                ")
            );

        /* ===============================
           6. Unión + filtros
        =============================== */
        $rankingBase = DB::query()->fromSub(
            $certificationsQuery->unionAll($trendsQuery),
            'ranking'
        );

        if ($rankingType !== 'all') {
            $rankingBase->where('entity_type', $rankingType);
        }

        $ranking = $rankingBase
            ->orderByDesc('final_score')
            ->paginate(4)
            ->withQueryString();

        /* ===============================
           7. Render
        =============================== */
        return Inertia::render(
            'DashboardRankingCertificaciones/RankingCertificacionesPage',
            [
                'ranking' => $ranking,
                'filters' => [
                    'year' => $year,
                    'period' => $period,
                    'area' => $areas,
                    'career' => $careers,
                    'ranking_type' => $rankingType,
                    'trend_category' => $trendCategory,
                ],
                'weights' => [
                    'laborWeight' => round($laborWeight * 100, 1),
                    'trendsWeight' => round($trendWeight * 100, 1),
                ],
                'meta' => [
                    'year' => $year,
                    'period' => $period,
                    'actualizado' => now()->toDateTimeString(),
                ],
            ]
        );
    }

    /* ==================================================
       AUXILIARES
    ================================================== */

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
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
}
