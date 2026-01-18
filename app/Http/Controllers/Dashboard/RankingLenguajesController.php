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
       GUARDAR PONDERACIONES (LENGUAJES)
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
                'context'      => 'languages',
                'is_active'    => 1,
                'applied_at'   => now(),
                'updated_by'   => auth()->id(),
            ]);
        });

        return redirect()->back();
    }

    /* ==================================================
       RANKING PRINCIPAL – LENGUAJES
    ================================================== */
    public function index(Request $request)
    {
        /* ================= BASE ================= */
        $year        = (int) $request->get('year', 2025);
        $period      = $request->get('period', 's2');
        $quarter     = $period === 's1' ? 1 : 4;

        $rankingType = $request->get('ranking_type', 'all');
        if (!in_array($rankingType, ['all', 'language', 'trend'])) {
            $rankingType = 'all';
        }

        $careers = array_filter((array) $request->get('career', []));
        $range   = $this->getPeriodRange($period, $year);

        /* ================= PONDERACIONES ================= */
        $activeWeights = Prueba::getActive('languages');
        $laborWeight = (float) ($activeWeights?->labor_weight ?? 0.70);
        $trendWeight = (float) ($activeWeights?->trend_weight ?? 0.30);

        /* ================= CATÁLOGOS ================= */
        $availableCareers = DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        /* ================= DEMANDA LABORAL ================= */
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

        /* ================= TENDENCIAS ================= */
        $trendSub = DB::table('language_trends as lt')
            ->join('language_trend_language as ltl', 'ltl.language_trend_id', '=', 'lt.id')
            ->where('lt.year', $year)
            ->where('lt.quarter', $quarter)
            ->select(
                'ltl.language_id',
                DB::raw('COUNT(DISTINCT lt.id) as mentions')
            )
            ->groupBy('ltl.language_id');

        $totalReports = DB::table('language_trends')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->count() ?: 1;

        /* ================= LENGUAJES ISIL ================= */
        $isilQuery = DB::table('languages as l')
            ->leftJoinSub($laborSub, 'labor', 'labor.language_id', '=', 'l.id')
            ->leftJoinSub($trendSub, 'trends', 'trends.language_id', '=', 'l.id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('course_language as cl')
                  ->join('career_course as cc', 'cc.course_id', '=', 'cl.course_id')
                  ->whereColumn('cl.language_id', 'l.id');
            })
            ->select(
                DB::raw("'language' as entity_type"),
                DB::raw('1 as is_isil'),
                'l.id',
                'l.name',
                DB::raw('COALESCE(labor.offers,0) as total_jobs'),
                DB::raw("
                    ROUND((COALESCE(labor.offers,0) / {$maxLabor}) * 100, 1)
                    as labor_score
                "),
                DB::raw("
                    ROUND((COALESCE(trends.mentions,0) / {$totalReports}) * 100, 1)
                    as trend_score
                "),
                DB::raw("
                    ROUND(
                        ((COALESCE(labor.offers,0) / {$maxLabor}) * 100 * {$laborWeight})
                      + ((COALESCE(trends.mentions,0) / {$totalReports}) * 100 * {$trendWeight}),
                    1) as final_score
                ")
            );

        /* ================= TENDENCIAS PURAS ================= */
        $trendQuery = DB::table('language_trends as lt')
            ->select(
                DB::raw("'trend' as entity_type"),
                DB::raw('0 as is_isil'),
                'lt.id',
                'lt.topic_name as name',
                DB::raw('0 as total_jobs'),
                DB::raw('0 as labor_score'),
                DB::raw('lt.trend_score as trend_score'),
                DB::raw("ROUND(lt.trend_score * {$trendWeight}, 1) as final_score")
            )
            ->where('lt.year', $year)
            ->where('lt.quarter', $quarter);

        /* ================= UNION ================= */
        if ($rankingType === 'language') {
            $rankingBase = DB::query()->fromSub($isilQuery, 'ranking');
        } elseif ($rankingType === 'trend') {
            $rankingBase = DB::query()->fromSub($trendQuery, 'ranking');
        } else {
            $rankingBase = DB::query()
                ->fromSub($isilQuery->unionAll($trendQuery), 'ranking');
        }

        $ranking = $rankingBase
            ->orderByDesc('final_score')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'DashboardRankingLanguages/RankingLenguajesPage',
            [
                'ranking' => $ranking,
                'filters' => [
                    'year' => $year,
                    'period' => $period,
                    'ranking_type' => $rankingType,
                ],
                'availableCareers' => $availableCareers,
                'weights' => [
                    'laborWeight'  => round($laborWeight * 100, 1),
                    'trendsWeight' => round($trendWeight * 100, 1),
                ],
            ]
        );
    }

    private function getPeriodRange(string $period, int $year): array
    {
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
}
