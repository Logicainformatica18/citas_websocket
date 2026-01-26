<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JobDemandGeoIndicatorController extends Controller
{
    /* =====================================================
       Helper: SQL para normalizar región
    ===================================================== */
    private function normalizedRegionSql(): string
    {
        return "
            CASE
                WHEN region IS NULL OR region = '' THEN 'Desconocido'

                WHEN UPPER(region) IN ('AFRICA') THEN 'África'
                WHEN UPPER(region) IN ('ASIA') THEN 'Asia'
                WHEN UPPER(region) IN ('EUROPE', 'EUROPA') THEN 'Europa'

                WHEN UPPER(region) IN (
                    'LATAM',
                    'LATINOAMERICA',
                    'LATINOAMÉRICA',
                    'LATIN AMERICA'
                ) THEN 'Latinoamérica'

                WHEN UPPER(region) IN (
                    'NORTH_AMERICA',
                    'NORTEAMERICA',
                    'NORTEAMÉRICA',
                    'NORTH AMERICA'
                ) THEN 'Norteamérica'

                WHEN UPPER(region) IN ('OCEANIA') THEN 'Oceanía'
                WHEN UPPER(region) IN ('GLOBAL', 'WORLDWIDE') THEN 'Global'
                WHEN UPPER(region) IN ('REMOTE', 'REMOTO') THEN 'Remoto'

                ELSE 'Desconocido'
            END
        ";
    }

    public function index(Request $request)
    {
        /* =====================================================
           1️⃣ Filtros
        ===================================================== */
        $year    = (int) $request->input('year', 2026);
        $period  = $request->input('period', 's2');
        $region  = $request->input('region');
        $country = $request->input('country');

        $range = $period === 's1'
            ? [$year . '-01-01', $year . '-06-30']
            : [$year . '-07-01', $year . '-12-31'];

        $regionSql = $this->normalizedRegionSql();

        /* =====================================================
           2️⃣ Query base
        ===================================================== */
        $baseQuery = DB::table('job_offers')
            ->whereBetween('published_at', $range)
            ->whereNotNull('city');

        if ($region) {
            $baseQuery->whereRaw("$regionSql = ?", [$region]);
        }

        if ($country) {
            $baseQuery->where('country', $country);
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
       $perPage = (int) $request->input('per_page', 10);

$ranking = (clone $baseQuery)
    ->select(
        'city',
        'region',
        'country',
        DB::raw('COUNT(*) as total_jobs')
    )
    ->groupBy('city', 'region', 'country')
    ->orderByDesc('total_jobs')
    ->paginate($perPage)
    ->withQueryString();

/* 👇 agregar porcentaje a cada item */
$ranking->getCollection()->transform(function ($row) use ($totalJobs) {
    $row->percentage = $totalJobs > 0
        ? round(($row->total_jobs / $totalJobs) * 100, 1)
        : 0;
    return $row;
});


        /* =====================================================
           5️⃣ Regiones normalizadas (FILTRO)
        ===================================================== */
        $regions = DB::table('job_offers')
            ->selectRaw("$regionSql as region")
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        /* =====================================================
           6️⃣ Meta
        ===================================================== */
        $meta = [
            'year'               => $year,
            'period'             => $period,
            'periodo_label'      => strtoupper($period) . ' ' . $year,
            'total_jobs'         => $totalJobs,
            'cities_count'       => $citiesCount,
            'top_city'           => $topCity?->city,
            'top5_concentration' => $top5Concentration,
        ];

        /* =====================================================
           7️⃣ Response Inertia
        ===================================================== */
        return Inertia::render('DashboardJobDemandGeo/Index', [
              'ranking' => $ranking, // 👈 paginator completo
            'meta'    => $meta,
            'filters' => [
                'year'    => $year,
                'period'  => $period,
                'region'  => $region,
                'country' => $country,
            ],
            'regions' => $regions,
        ]);
    }
 public function getData(Request $request)
{
    /* =====================================================
       1️⃣ Filtros
    ===================================================== */
    $year    = (int) $request->get('year', 2026);
    $period  = $request->get('period', 's2');
    $region  = $request->get('region');
    $country = $request->get('country');

    /* =====================================================
       2️⃣ Periodo → rango
    ===================================================== */
    if ($period === 's1') {
        $startDate = "$year-01-01";
        $endDate   = "$year-06-30";
    } else {
        $startDate = "$year-07-01";
        $endDate   = "$year-12-31";
    }

    /* =====================================================
       3️⃣ Query base (MAPA REAL)
       ✔ solo coordenadas
    ===================================================== */
    $query = DB::table('job_offers')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->whereBetween('published_at', [$startDate, $endDate]);

    /* =====================================================
       4️⃣ Filtros geográficos
    ===================================================== */
    if ($country) {
        $query->where('country', $country);
    }

 if ($region) {
    $regionSql = $this->normalizedRegionSql();

    $query->whereRaw("$regionSql = ?", [$region]);
}


    /* =====================================================
       5️⃣ Agrupación por ciudad
    ===================================================== */
    $results = $query
        ->selectRaw("
            country,
            city,
            AVG(latitude)  as lat,
            AVG(longitude) as lng,
            COUNT(*)       as total
        ")
        ->groupBy('country', 'city')
        ->get();

    if ($results->isEmpty()) {
        return response()->json([
            'results' => [],
            'message' => 'Sin datos para los filtros.',
        ]);
    }

    /* =====================================================
       6️⃣ Intensidad VISUAL (CLAVE)
    ===================================================== */
    $max = max(1, $results->max('total'));

    $results->transform(function ($r) use ($max) {
        $r->intensity = max(
            round($r->total / $max, 3),
            0.15 // 👈 piso visible para heatmap
        );
        return $r;
    });

    /* =====================================================
       7️⃣ Response
    ===================================================== */
    return response()->json([
        'results' => $results,
        'max'     => $max,
        'message' => '📍 Mapa de calor – demanda laboral por ciudad',
    ]);
}

}
