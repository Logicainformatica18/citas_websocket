<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;
use Illuminate\Support\Facades\Artisan;

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
public function getMapData(Request $request)
{
    $year     = (int) $request->get('year', 2026);
    $period   = $request->get('period', 's1');
    $region   = $request->get('region');
    $country  = $request->get('country');

    $range = $period === 's1'
        ? [$year . '-01-01', $year . '-06-30']
        : [$year . '-07-01', $year . '-12-31'];

    $regionSql = $this->normalizedRegionSql();

    $query = DB::table('job_offers as jo')
        ->whereBetween('jo.published_at', $range)
        ->whereNotNull('jo.latitude')
        ->whereNotNull('jo.longitude')
    ->whereRaw("$regionSql <> 'Desconocido'");

    if ($country) {
        $query->where('jo.country', $country);
    }

    if ($region) {
        $query->whereRaw("$regionSql = ?", [$region]);
    }

    $results = $query
        ->selectRaw("
            jo.country,
            jo.city,
            AVG(jo.latitude)  as lat,
            AVG(jo.longitude) as lng,
            COUNT(DISTINCT jo.id) as total
        ")
        ->groupBy('jo.country', 'jo.city')
        ->get();

    $max = max(1, $results->max('total'));

    $results->transform(function ($r) use ($max) {
        $r->intensity = max(round($r->total / $max, 3), 0.15);
        return $r;
    });

    return response()->json([
        'results' => $results,
        'max'     => $max
    ]);
}
public function index(Request $request)
{
    /* =====================================================
       1️⃣ Filtros
    ===================================================== */

    $year     = (int) $request->input('year', 2026);
    $period   = $request->input('period', 's1');
    $region   = $request->input('region');
    $country  = $request->input('country');
    $careerId = $request->input('career_id');

    $range = $period === 's1'
        ? [$year . '-01-01', $year . '-06-30']
        : [$year . '-07-01', $year . '-12-31'];

    $regionSql = $this->normalizedRegionSql();

    $jobMarketStatus = JobMarketStatusBuilder::build([
        'mode'   => 'market',
        'year'   => $year,
        'period' => $period,
    ]);

    /* =====================================================
       2️⃣ Subquery curricular optimizada
    ===================================================== */


    /* =====================================================
       3️⃣ Base query
    ===================================================== */

    /* =====================================================
   3️⃣ Base query optimizada
===================================================== */
// aqui filtramos los datos que n oqueremos mostrar en la tabla de ciudades indicador 
$baseQuery = DB::table('job_offers as jo')
    ->join('job_offer_alignment as a', 'a.job_offer_id', '=', 'jo.id')
    ->whereBetween('jo.published_at', $range)
    ->whereNotNull('jo.city')
    ->whereRaw("$regionSql <> 'Desconocido'")
     ->where('jo.city', '<>', 'Desconocido');
if ($careerId) {
    $baseQuery->where('a.career_id', $careerId);
}
    if ($region) {
        $baseQuery->whereRaw("$regionSql = ?", [$region]);
    }

    if ($country) {
        $baseQuery->where('jo.country', $country);
    }

    /* =====================================================
       4️⃣ KPIs
    ===================================================== */

/* =====================================================
   4️⃣ KPIs
===================================================== */

$totalJobs = (clone $baseQuery)->count('jo.id');

$citiesCount = (clone $baseQuery)
    ->distinct()
    ->count('jo.city');

$topCity = (clone $baseQuery)
    ->select('jo.city', DB::raw('COUNT(jo.id) as total'))
    ->groupBy('jo.city')
    ->orderByDesc('total')
    ->first();

$top5Jobs = (clone $baseQuery)
    ->select(DB::raw('COUNT(jo.id) as total'))
    ->groupBy('jo.city')
    ->orderByDesc('total')
    ->limit(5)
    ->get()
    ->sum('total');

$top5Concentration = $totalJobs > 0
    ? round(($top5Jobs / $totalJobs) * 100, 1)
    : 0;


/* ===============================
   CARRERAS ACTIVAS
================================ */

$careersWithDemand = DB::table('careers as c')
    ->join('career_course as cc', 'cc.career_id', '=', 'c.id')
    ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
    ->join('technology_job as tj', 'tj.technology_id', '=', 'ct.technology_id')
    ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
    ->whereBetween('jo.published_at', $range)
    ->distinct()
    ->count('c.id');


/* ===============================
   CARRERA LÍDER
================================ */

$topCareer = DB::table('careers as c')
    ->join('career_course as cc', 'cc.career_id', '=', 'c.id')
    ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
    ->join('technology_job as tj', 'tj.technology_id', '=', 'ct.technology_id')
    ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
    ->whereBetween('jo.published_at', $range)
    ->groupBy('c.id', 'c.name')
    ->select('c.name', DB::raw('COUNT(DISTINCT jo.id) as total'))
    ->orderByDesc('total')
    ->first();

    /* =====================================================
       5️⃣ Ranking ciudades
    ===================================================== */

    $perPage = (int) $request->input('per_page', 10);

    $ranking = (clone $baseQuery)
        ->select(
            'jo.city',
            'jo.region',
            'jo.country',
            DB::raw('COUNT(jo.id) as total_jobs')
        )
        ->groupBy('jo.city', 'jo.region', 'jo.country')
        ->orderByDesc('total_jobs')
        ->paginate($perPage)
        ->withQueryString();

    $ranking->getCollection()->transform(function ($row) use ($totalJobs) {
        $row->percentage = $totalJobs > 0
            ? round(($row->total_jobs / $totalJobs) * 100, 1)
            : 0;
        return $row;
    });

    /* =====================================================
       6️⃣ Meta
    ===================================================== */

 $meta = [
    'year'              => $year,
    'period'            => $period,
    'periodo_label'     => strtoupper($period) . ' ' . $year,
    'total_jobs'        => $totalJobs,
    'cities_count'      => $citiesCount,
    'careers_count'     => $careersWithDemand,
    'top_city'          => $topCity?->city,
    'top_career'        => $topCareer?->name,
    'top5_concentration'=> $top5Concentration,
];

    /* =====================================================
       7️⃣ Render
    ===================================================== */

    return Inertia::render('DashboardJobDemandGeo/Index', [
        'ranking' => $ranking,
        'meta'    => $meta,
        'jobMarketStatus' => $jobMarketStatus,
        'filters' => [
            'year'      => $year,
            'period'    => $period,
            'region'    => $region,
            'country'   => $country,
            'career_id' => $careerId,
        ],
      'regions' => DB::table('job_offers')
    ->selectRaw("$regionSql as region")
    ->whereRaw("$regionSql <> 'Desconocido'")
    ->distinct()
    ->orderBy('region')
    ->pluck('region'),
      'careers' => DB::table('careers as c')
    ->join('career_course as cc', 'cc.career_id', '=', 'c.id')
    ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
    ->join('technology_job as tj', 'tj.technology_id', '=', 'ct.technology_id')
    ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
    ->whereBetween('jo.published_at', $range)
    ->groupBy('c.id','c.name')
    ->select(
        'c.id',
        'c.name',
        DB::raw('COUNT(DISTINCT jo.id) as total_jobs')
    )
    ->orderByDesc('total_jobs')
    ->get(),
    ]);
}

public function rebuildAlignment()
{
    $commands = [
        'normalize:asia-countries',
        'normalize:europe-countries',
        'joboffers:normalize-regions',
        'joboffers:normalize-north-america',
        'joboffers:normalize-oceania',
        'normalize:geo-regions',
        'normalize:peru-regions',
        'market:fix-null-entities',
    ];

    foreach ($commands as $command) {
        Artisan::queue($command);
    }

    Artisan::queue('jobs:build-alignment', [
        '--truncate' => true
    ]);

    return response()->json([
        'message' => 'Pipeline de normalización enviado a cola'
    ]);
}
public function searchCountries(Request $request)
{
    $term   = trim($request->get('q', ''));
    $region = $request->get('region');

    $regionSql = $this->normalizedRegionSql();

    return DB::table('job_offers')
        ->whereNotNull('country')
        ->when($region, fn ($q) =>
            $q->whereRaw("$regionSql = ?", [$region])
        )
        ->when($term, fn ($q) =>
            $q->where('country', 'like', "%{$term}%")
        )
        ->distinct()
        ->orderBy('country')
        ->limit(15)
        ->pluck('country');
}



public function getData(Request $request)
{
    $year     = (int) $request->get('year', 2026);
    $period   = $request->get('period', 's1');
    $region   = $request->get('region');
    $country  = $request->get('country');
    $careerId = $request->get('career_id');

    $range = $period === 's1'
        ? [$year . '-01-01', $year . '-06-30']
        : [$year . '-07-01', $year . '-12-31'];

    $regionSql = $this->normalizedRegionSql();

    $query = DB::table('job_offers as jo')
        ->whereBetween('jo.published_at', $range)
        ->whereNotNull('jo.latitude')
        ->whereNotNull('jo.longitude')
         ->whereRaw("$regionSql <> 'Desconocido'");

    if ($country) {
        $query->where('jo.country', $country);
    }

    if ($region) {
        $query->whereRaw("$regionSql = ?", [$region]);
    }

    $results = $query
        ->selectRaw("
            jo.country,
            jo.city,
            AVG(jo.latitude)  as lat,
            AVG(jo.longitude) as lng,
            COUNT(DISTINCT jo.id) as total
        ")
        ->groupBy('jo.country', 'jo.city')
        ->get();

    $max = max(1, $results->max('total'));

    $results->transform(function ($r) use ($max) {
        $r->intensity = max(round($r->total / $max, 3), 0.15);
        return $r;
    });

    return response()->json([
        'results' => $results,
        'max'     => $max,
        'message' => '📍 Mapa de calor – demanda laboral por ciudad',
    ]);
}


}
