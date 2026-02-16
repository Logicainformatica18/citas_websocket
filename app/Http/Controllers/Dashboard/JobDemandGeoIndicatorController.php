<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;

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
    $year     = (int) $request->input('year', 2025);
    $period   = $request->input('period', 's2');
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
    $alignedSubquery = DB::table(DB::raw("
        (
            SELECT tj.job_offer_id
            FROM technology_job tj
            JOIN course_technology ct ON ct.technology_id = tj.technology_id
            JOIN career_course cc ON cc.course_id = ct.course_id
            " . ($careerId ? "WHERE cc.career_id = {$careerId}" : "") . "

            UNION ALL

            SELECT lj.job_offer_id
            FROM language_job lj
            JOIN course_language cl ON cl.language_id = lj.language_id
            JOIN career_course cc ON cc.course_id = cl.course_id
            " . ($careerId ? "WHERE cc.career_id = {$careerId}" : "") . "

            UNION ALL

            SELECT mj.job_offer_id
            FROM methodology_job mj
            JOIN course_methodology cm ON cm.methodology_id = mj.methodology_id
            JOIN career_course cc ON cc.course_id = cm.course_id
            " . ($careerId ? "WHERE cc.career_id = {$careerId}" : "") . "
        ) as aligned
    "))
    ->selectRaw("DISTINCT aligned.job_offer_id");

    /* =====================================================
       3️⃣ Base Query (join reducido)
    ===================================================== */
    $baseQuery = DB::table('job_offers as jo')
        ->joinSub($alignedSubquery, 'a', function ($join) {
            $join->on('a.job_offer_id', '=', 'jo.id');
        })
        ->whereBetween('jo.published_at', $range)
        ->whereNotNull('jo.city');

    if ($region) {
        $baseQuery->whereRaw("$regionSql = ?", [$region]);
    }

    if ($country) {
        $baseQuery->where('jo.country', $country);
    }

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

    /* Carreras activas */
    $careersWithDemand = DB::table('career_course as cc')
        ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
        ->join('technology_job as tj', 'tj.technology_id', '=', 'ct.technology_id')
        ->distinct()
        ->count('cc.career_id');

    /* Carrera líder */
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
       5️⃣ Ranking
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
            ->distinct()
            ->orderBy('region')
            ->pluck('region'),
        'careers' => DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name']),
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
    $year     = (int) $request->get('year', 2025);
    $period   = $request->get('period', 's2');
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
        ->whereNotNull('jo.longitude');

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
