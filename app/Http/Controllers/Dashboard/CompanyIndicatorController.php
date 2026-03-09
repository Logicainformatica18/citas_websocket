<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;


class CompanyIndicatorController extends Controller
{
public function companyJobs(Request $request, $company)
{
    $jobs = DB::table('job_offers')
        ->select(
            'id',
            'title',
            'country',
            'published_at',
            'url'   // 🔹 agregar url
        )
        ->where('company', 'LIKE', "%{$company}%")
        ->orderByDesc('published_at')
        ->paginate(5);

    $jobs->withPath("/dashboard/indicators/companies/$company/jobs");

    return response()->json($jobs);
}
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

    /* =====================================================
       1️⃣ Vista principal
    ===================================================== */
    public function index(Request $request)
    {
        /* =========================================
           Defaults
        ========================================= */
        $year    = (int) $request->get('year', 2026);
        $period  = $request->get('period', 's1');
        $region  = $request->get('region'); // ya normalizada
        $country = $request->get('country');
        $perPage = (int) $request->get('per_page', 7);

        $range = $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];

        Log::info('🏢 [CompanyIndicator] Params', compact(
            'year',
            'period',
            'region',
            'country',
            'range',
            'perPage'
        ));

        $regionSql = $this->normalizedRegionSql();

        /* =========================================
           2️⃣ Query base (con región normalizada)
        ========================================= */
        $baseQuery = DB::table('job_offers')
            ->whereNotNull('company')
            ->whereBetween('published_at', [$range['start'], $range['end']]);

        if ($region) {
            $baseQuery->whereRaw("$regionSql = ?", [$region]);
        }

        if ($country) {
            $baseQuery->where('country', $country);
        }

        /* =========================================
           3️⃣ Ranking global (KPIs)
        ========================================= */
        $fullRanking = (clone $baseQuery)
            ->selectRaw("company, COUNT(*) as total_vacancies")
            ->groupBy('company')
            ->orderByDesc('total_vacancies')
            ->get();

        $empresaLider = $fullRanking->first();

        $top3Vacantes = $fullRanking->take(3)->sum('total_vacancies');
        $totalVacantes = $fullRanking->sum('total_vacancies');

        $concentracionTop3 = $totalVacantes > 0
            ? round(($top3Vacantes / $totalVacantes) * 100)
            : 0;

        /* =========================================
           4️⃣ Ranking paginado
        ========================================= */
        $companies = (clone $baseQuery)
            ->selectRaw("company, COUNT(*) as total_vacancies")
            ->groupBy('company')
            ->orderByDesc('total_vacancies')
            ->paginate($perPage)
            ->withQueryString();

        /* =========================================
           5️⃣ Regiones NORMALIZADAS (para filtro)
        ========================================= */
        $regions = DB::table('job_offers')
            ->selectRaw("$regionSql as region")
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        /* =========================================
           6️⃣ Meta
        ========================================= */
       $meta = [
    'year'                   => $year,
    'period'                 => $period,
    'periodo_label'          => $period === 's1' ? 'Ene - Jun' : 'Jul - Dic',

    // 🔹 Totales (HEADER)
    'vacantes_analizadas'    => (int) $totalVacantes,
    'empresas_activas'       => $fullRanking->count(),

    // 🔹 KPIs (CARDS)
    'empresa_lider'          => $empresaLider->company ?? null,
    'empresa_lider_vacantes' => $empresaLider->total_vacancies ?? 0,
    'concentracion_top_3'    => $concentracionTop3,
];

$jobMarketStatus = JobMarketStatusBuilder::build([
    'mode'   => 'market',   // 🔥 indicador transversal
    'year'   => $year,
    'period' => $period,
]);

        /* =========================================
           7️⃣ Response Inertia
        ========================================= */
      return Inertia::render(
    'DashboardCompanies/Index',
    [
        'ranking' => $companies,
        'meta'    => $meta,

        // 🔥 MISMO CONTRATO QUE SENIORITY / RANKINGS
        'jobMarketStatus' => $jobMarketStatus,

        'filters' => [
            'year'    => $year,
            'period'  => $period,
            'region'  => $region,
            'country' => $country,
            'perPage' => $perPage,
        ],
        'regions' => $regions,
    ]
);
}
    /* =====================================================
       2️⃣ Buscador incremental de países (respeta región)
    ===================================================== */
    public function searchCountries(Request $request)
    {
        $search = trim($request->get('q'));
        $region = $request->get('region');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $regionSql = $this->normalizedRegionSql();

        $query = DB::table('job_offers')
            ->select('country')
            ->whereNotNull('country')
            ->where('country', 'LIKE', "%{$search}%");

        if ($region) {
            $query->whereRaw("$regionSql = ?", [$region]);
        }

        return $query
            ->distinct()
            ->orderBy('country')
            ->limit(10)
            ->pluck('country');
    }
}
