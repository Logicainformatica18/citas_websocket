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
       GUARDAR PONDERACIONES (LENGUAGES)
    ================================================== */
    public function storeWeights(Request $request)
    {
        $data = $request->validate([
            'labor_weight' => 'required|numeric|min:0|max:1',
        ]);

        DB::transaction(function () use ($data) {
            Prueba::where('context', 'languages')
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            Prueba::create([
                'labor_weight' => $data['labor_weight'],
                'trend_weight' => 0,
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
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');

        $careers = $request->filled('career')
            ? array_filter((array) $request->career)
            : [];

        $range = $this->getPeriodRange($period, $year);

        /* ================= PONDERACIONES ================= */
        try {
            $activeWeights = Prueba::getActive('languages');
        } catch (\Throwable $e) {
            $activeWeights = null;
        }

        $laborWeight = (float) ($activeWeights?->labor_weight ?? 1.0);

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

        /* ================= SUBQUERY DEMANDA LABORAL ================= */
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

        /* ================= QUERY BASE ================= */
        $languagesQuery = DB::table('languages as l')
            ->leftJoinSub($laborSub, 'labor', 'labor.language_id', '=', 'l.id')
            ->where('l.enabled', 1);

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
            'l.id',
            'l.name',

            DB::raw('COALESCE(labor.offers,0) as total_jobs'),

           DB::raw("
    ROUND(
        (
          LOG(COALESCE(labor.offers,0) + 1)
          / LOG({$maxLabor} + 1)
        ) * 100,
    1) as labor_score
"),

DB::raw("
    ROUND(
        (
          LOG(COALESCE(labor.offers,0) + 1)
          / LOG({$maxLabor} + 1)
        ) * 100 * {$laborWeight},
    1) as final_score
")

        );

        $ranking = DB::query()
            ->fromSub($languagesQuery, 'ranking')
            ->orderByDesc('final_score')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'DashboardRankingLanguages/RankingLenguajesPage',
            [
                'ranking' => $ranking,
                'filters' => [
                    'year'   => $year,
                    'period' => $period,
                    'career' => $careers,
                ],
                'availableCareers' => $availableCareers,
                'weights' => [
                    'laborWeight' => round($laborWeight * 100, 1),
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
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
}
