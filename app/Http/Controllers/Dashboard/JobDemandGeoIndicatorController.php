<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JobDemandGeoIndicatorController extends Controller
{
    public function index(Request $request)
    {
        /* =====================================================
           1️⃣ Filtros (misma lógica que otros indicadores)
        ===================================================== */
        $year   = (int) $request->input('year', now()->year);
        $period = $request->input('period', 's2');

        $country = $request->input('country');
        $region  = $request->input('region');
        $city    = $request->input('city');
        $modality = $request->input('modality'); // remote | hybrid | onsite

        $range = $period === 's1'
            ? [$year . '-01-01', $year . '-06-30']
            : [$year . '-07-01', $year . '-12-31'];

        /* =====================================================
           2️⃣ Base query
        ===================================================== */
        $baseQuery = DB::table('job_offers')
            ->whereBetween('published_at', $range)
            ->whereNotNull('city');

        if ($country) {
            $baseQuery->where('country', $country);
        }

        if ($region) {
            $baseQuery->where('region', $region);
        }

        if ($city) {
            $baseQuery->where('city', $city);
        }

        if ($modality) {
            $baseQuery->where('work_mode', $modality);
        }

        /* =====================================================
           3️⃣ KPIs
        ===================================================== */
        $totalJobs = (clone $baseQuery)->count();

        $citiesCount = (clone $baseQuery)
            ->distinct('city')
            ->count('city');

        $topCity = (clone $baseQuery)
            ->select('city', DB::raw('COUNT(*) as total'))
            ->groupBy('city')
            ->orderByDesc('total')
            ->first();

        $top5Jobs = (clone $baseQuery)
            ->select(DB::raw('COUNT(*) as total'))
            ->groupBy('city')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->sum('total');

        $top5Concentration = $totalJobs > 0
            ? round(($top5Jobs / $totalJobs) * 100, 1)
            : 0;

        /* =====================================================
           4️⃣ Ranking por ciudad
        ===================================================== */
        $ranking = (clone $baseQuery)
            ->select(
                'city',
                'region',
                'country',
                DB::raw('COUNT(*) as total_jobs')
            )
            ->groupBy('city', 'region', 'country')
            ->orderByDesc('total_jobs')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($totalJobs) {
                $row->percentage = $totalJobs > 0
                    ? round(($row->total_jobs / $totalJobs) * 100, 1)
                    : 0;
                return $row;
            });

        /* =====================================================
           5️⃣ Response Inertia
        ===================================================== */
        return Inertia::render('DashboardJobDemandGeo/Index', [
            'filters' => [
                'year'     => $year,
                'period'   => $period,
                'country'  => $country,
                'region'   => $region,
                'city'     => $city,
                'modality' => $modality,
            ],
            'meta' => [
                'total_jobs'         => $totalJobs,
                'cities_count'       => $citiesCount,
                'top_city'           => $topCity?->city,
                'top5_concentration' => $top5Concentration,
                'period_label'       => strtoupper($period) . ' ' . $year,
            ],
            'ranking' => $ranking,
        ]);
    }
}
