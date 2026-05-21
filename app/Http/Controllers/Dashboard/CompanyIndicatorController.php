<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CompanyEvolutionExport;

class CompanyIndicatorController extends Controller
{
public function companyJobs(Request $request, $company)
{
    $country = $request->get('country');
    $year    = (int) $request->get('year', 2026);
    $period  = $request->get('period', 's1');

    $range = $period === 's1'
        ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
        : ['start' => "$year-07-01", 'end' => "$year-12-31"];

    $regionSql = $this->normalizedRegionSql();

    $query = DB::table('job_offers')
        ->select('id', 'title', 'country', 'published_at', 'url')
        ->whereNotNull('company')
        ->whereBetween('published_at', [$range['start'], $range['end']])
        ->whereRaw("$regionSql <> 'Desconocido'")
        ->whereRaw('UPPER(TRIM(company)) = ?', [strtoupper(trim($company))]);

    if ($country) {
        $query->where('country', $country);
    }
Log::info('MODAL PARAMS', [
    'company' => $company,
    'period'  => $request->get('period'),
    'year'    => $request->get('year'),
    'country' => $request->get('country'),
]);
    $jobs = $query
        ->orderByDesc('published_at')
        ->paginate(5);

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

$baseQuery = DB::table('job_offers')
    ->whereNotNull('company')
    ->whereBetween('published_at', [$range['start'], $range['end']])
    ->whereRaw("$regionSql <> 'Desconocido'");

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
            ->selectRaw("
    UPPER(TRIM(company)) as company_normalized,
    MIN(company) as company,
    COUNT(*) as total_vacancies
")
->groupBy('company_normalized')
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
    ->selectRaw("
        UPPER(TRIM(company)) as company_normalized,
        MIN(company) as company,
        COUNT(*) as total_vacancies
    ")
    ->groupBy('company_normalized')
    ->orderByDesc('total_vacancies')
    ->paginate($perPage)
    ->withQueryString();

        /* =========================================
           5️⃣ Regiones NORMALIZADAS (para filtro)
        ========================================= */
      $regions = DB::table('job_offers')
    ->selectRaw("$regionSql as region")
    ->whereRaw("$regionSql <> 'Desconocido'")
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
public function evolutionCompanies(Request $request)
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');
    $filter = $request->get('filter', 'weekly'); // 'weekly', 'biweekly', 'monthly'

    $perPage = min((int) $request->get('per_page', 5), 20);

    // 1️⃣ Mapeamos los meses que corresponden al periodo seleccionado (s1 o s2)
    // Esto asegura que la paginación no mezcle semestres
    $mesesFiltro = $period === 's1'
        ? ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio']
        : ['Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // 2️⃣ Consultamos la tabla caché trayendo los datos ya consolidados y acumulados
    $rawCachedData = DB::table('company_evolution_cache')
        ->where('year', $year)
        ->where('period_type', $filter)
        ->where(function($query) use ($mesesFiltro) {
            foreach ($mesesFiltro as $mes) {
                // Buscamos que el label contenga el nombre del mes correspondiente
                $query->orWhere('period_label', 'LIKE', "%{$mes}%");
            }
        })
        ->orderBy('start_date', 'desc')
        ->orderBy('ranking_position', 'asc')
        ->get();

    // 3️⃣ Helper para agrupar y formatear la colección al contrato exacto que espera tu Frontend
    $formatMarketData = function ($items) use ($perPage) {
        $collection = $items->groupBy('period_label')->map(function ($companiesInPeriod) {
            $first = $companiesInPeriod->first();

            return [
                'period'     => $first->period_label, // Tu frontend lee el label identificador
                'label'      => $first->period_label,
                'start_date' => $first->start_date,
                'end_date'   => $first->end_date,
                'total_jobs' => (int) $first->total_market_jobs, // 🌟 ¡El acumulado real del mercado completo!
                'companies'  => $companiesInPeriod->map(function ($c) {
                    return [
                        'company'    => $c->company_original,
                        'jobs'       => (int) $c->jobs, // 🌟 Las vacantes acumuladas acumulativas de la empresa
                        // Calculamos el porcentaje real basado en el mercado global acumulado
                        'percentage' => $c->total_market_jobs > 0
                            ? round(($c->jobs / $c->total_market_jobs) * 100, 1)
                            : 0,
                    ];
                })->values()->all()
            ];
        })->values();

        // 4️⃣ Paginación manual de los bloques de tiempo (Semanas/Quincenas/Meses)
        $page      = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $collection->slice(($page - 1) * $perPage, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        return [
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ];
    };

    // 5️⃣ Separamos por mercados de forma limpia y ultra rápida en memoria
    $nationalData      = $rawCachedData->where('market_type', 'national');
    $internationalData = $rawCachedData->where('market_type', 'international');

    return response()->json([
        'national'      => $formatMarketData($nationalData),
        'international' => $formatMarketData($internationalData),
    ]);
}
public function exportEvolutionCompanies(Request $request)
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');
    $filter = $request->get('filter', 'weekly');
    $type   = $request->get('type', 'national'); // 🔥 clave

    return Excel::download(
        new CompanyEvolutionExport($year, $period, $filter, $type),
        "companies_{$type}_evolution.xlsx"
    );
}
}
