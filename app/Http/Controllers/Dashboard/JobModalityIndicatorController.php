<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\JobMarketStatusBuilder;

class JobModalityIndicatorController extends Controller
{
   public function index(Request $request)
    {
        $year   = (int) $request->get('year', 2026);
        $period = $request->get('period', 's1');

        $range = $this->getPeriodRange($year, $period);

        $filters = [
            'region'  => $request->get('region'),
            'country' => $request->get('country'),
            'city'    => $request->get('city'),
            'source'  => $request->get('source'),
            'year'    => $year,
            'period'  => $period,
        ];

        /* =====================================================
           CLASIFICACIÓN PRINCIPAL (KPIs + Donut)
           SOLO REMOTO / HÍBRIDO / PRESENCIAL
        ===================================================== */

        $classified = DB::table('job_offers')
            ->selectRaw("
                CASE
                    WHEN modality IN ('remote','fully_remote','remote_local')
                        THEN 'remoto'
                    WHEN modality = 'hybrid'
                        THEN 'híbrido'
                    WHEN modality = 'no_remote'
                        THEN 'presencial'
                END AS modalidad
            ")
            ->whereBetween('published_at', [$range['start'], $range['end']])

            // 🔥 ignorar no_precisa y null
         ->whereIn('modality', [
    'remote',
    'fully_remote',
    'remote_local',
    'hybrid',
    'no_remote'
]);

        if ($filters['region']) {
            $classified->where('region', $filters['region']);
        }

        if ($filters['country']) {
            $classified->where('country', $filters['country']);
        }

        if ($filters['city']) {
            $classified->where('city', $filters['city']);
        }

        if ($filters['source']) {
            $classified->where('source', $filters['source']);
        }

        $totalVacantes = (clone $classified)->count();

        $data = DB::query()
            ->fromSub($classified, 't')
            ->select('modalidad', DB::raw('COUNT(*) AS vacantes'))
            ->groupBy('modalidad')
            ->orderByDesc('vacantes')
            ->get()
            ->map(fn ($row) => [
                'modalidad'  => $row->modalidad,
                'vacantes'   => (int) $row->vacantes,
                'porcentaje' => $totalVacantes > 0
                    ? round(($row->vacantes / $totalVacantes) * 100, 2)
                    : 0,
            ]);

        /* =====================================================
           TENDENCIA MENSUAL (%)
           SOLO SOBRE LAS 3 MODALIDADES
        ===================================================== */

        $trendData = DB::table('job_offers')
            ->whereBetween('published_at', [$range['start'], $range['end']])

            // 🔥 ignorar no_precisa
            ->whereNotNull('modality')
            ->where('modality','!=','no_precisa')

            ->selectRaw("
                YEAR(published_at)  AS year,
                MONTH(published_at) AS month_num,

                ROUND(
                    SUM(modality IN ('remote','fully_remote','remote_local'))
                    / COUNT(*) * 100,
                1) AS remoto,

                ROUND(
                    SUM(modality = 'hybrid')
                    / COUNT(*) * 100,
                1) AS hibrido,

                ROUND(
                    SUM(modality = 'no_remote')
                    / COUNT(*) * 100,
                1) AS presencial
            ")
            ->groupByRaw('YEAR(published_at), MONTH(published_at)')
            ->orderByRaw('YEAR(published_at), MONTH(published_at)')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'month'      => Carbon::create()->month($row->month_num)->translatedFormat('M'),
                'remoto'     => (float) $row->remoto,
                'hibrido'    => (float) $row->hibrido,
                'presencial' => (float) $row->presencial,
            ]);

        $jobMarketStatus = JobMarketStatusBuilder::build([
            'mode'   => 'market',
            'year'   => $year,
            'period' => $period,
        ]);

        return Inertia::render('Dashboard/Indicators/JobModalityIndicatorPage', [
            'data'      => $data,
            'trendData' => $trendData,
            'filters'   => $filters,
            'meta'      => [
                'year'   => $year,
                'period' => $period,
                'periodo_label' => $period === 's1'
                    ? "Semestre 1 – Enero a Junio {$year}"
                    : "Semestre 2 – Julio a Diciembre {$year}",
                'total_vacantes' => $totalVacantes,
            ],
            'jobMarketStatus' => $jobMarketStatus,
        ]);
    }


    /* =====================================================
       AUTOCOMPLETADOS
    ===================================================== */
    public function searchRegions(Request $request)
    {
        $term = trim($request->get('q', ''));

        return DB::table('job_offers')
            ->whereNotNull('region')
            ->when($term, fn ($q) =>
                $q->where('region', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('region')
            ->limit(15)
            ->pluck('region');
    }

    public function searchCountries(Request $request)
    {
        $term   = trim($request->get('q', ''));
        $region = $request->get('region');

        return DB::table('job_offers')
            ->whereNotNull('country')
            ->when($region, fn ($q) =>
                $q->where('region', $region)
            )
            ->when($term, fn ($q) =>
                $q->where('country', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('country')
            ->limit(15)
            ->pluck('country');
    }

    public function searchCities(Request $request)
    {
        $term    = trim($request->get('q', ''));
        $country = $request->get('country');

        return DB::table('job_offers')
            ->whereNotNull('city')
            ->when($country, fn ($q) =>
                $q->where('country', $country)
            )
            ->when($term, fn ($q) =>
                $q->where('city', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('city')
            ->limit(15)
            ->pluck('city');
    }

    private function getPeriodRange(int $year, string $period): array
    {
        return $period === 's1'
            ? ['start' => "{$year}-01-01", 'end' => "{$year}-06-30"]
            : ['start' => "{$year}-07-01", 'end' => "{$year}-12-31"];
    }
}
