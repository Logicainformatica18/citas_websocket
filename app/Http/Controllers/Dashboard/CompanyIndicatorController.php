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
    $filter = $request->get('filter', 'weekly');

    $perPage = min((int) $request->get('per_page', 5), 20);

    $range = $period === 's1'
        ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
        : ['start' => "$year-07-01", 'end' => "$year-12-31"];

    /* =========================
       AGRUPADOR
    ========================= */
    switch ($filter) {
        case 'monthly':
            $group = "DATE_FORMAT(published_at, '%Y-%m')";
            break;

        case 'biweekly':
            $group = "
                CONCAT(
                    YEAR(published_at), '-',
                    LPAD(MONTH(published_at),2,'0'), '-',
                    IF(DAY(published_at) <= 15, 1, 2)
                )
            ";
            break;

        default:
            $group = "YEARWEEK(published_at,1)";
            break;
    }

    /* =========================
       BASE QUERY
    ========================= */
    $base = DB::table('job_offers')
        ->whereNotNull('company')
        ->whereBetween('published_at', [$range['start'], $range['end']]);

    /* =========================
       FUNCION REUTILIZABLE
    ========================= */
    $build = function ($query) use ($group, $filter, $perPage) {

        $rows = $query
            ->select(
                DB::raw("$group as period"),
                DB::raw("MIN(published_at) as start_date"),
                DB::raw("MAX(published_at) as end_date"),
                DB::raw("UPPER(TRIM(company)) as company"),
                DB::raw("COUNT(*) as total")
            )
            ->groupBy(DB::raw($group), DB::raw("UPPER(TRIM(company))"))
            ->get();

        $collection = $rows
            ->groupBy('period')
            ->map(function ($items, $period) use ($filter) {

                $total = $items->sum('total');
                $start = $items->min('start_date');
                $end   = $items->max('end_date');

                switch ($filter) {
                    case 'monthly':
                        $label = \Carbon\Carbon::parse($start)
                            ->locale('es')
                            ->translatedFormat('F Y');
                        break;

                    case 'biweekly':
                        $label = \Carbon\Carbon::parse($start)->format('d M')
                            . " – " .
                            \Carbon\Carbon::parse($end)->format('d M');
                        break;

                    default:
                        $week = \Carbon\Carbon::parse($start)->weekOfYear;
                        $label = "Semana {$week} (" .
                            \Carbon\Carbon::parse($start)->format('d M') .
                            " – " .
                            \Carbon\Carbon::parse($end)->format('d M') .
                            ")";
                        break;
                }

                $topCompanies = $items
                    ->sortByDesc('total')
                    ->take(5)
                    ->values()
                    ->map(function ($c) use ($total) {
                        return [
                            'company' => $c->company,
                            'jobs' => $c->total,
                            'percentage' => $total > 0
                                ? round(($c->total / $total) * 100, 1)
                                : 0,
                        ];
                    });

                return [
                    'label' => $label,
                    'start_date' => $start,
                    'end_date' => $end,
                    'total_jobs' => $total,
                    'companies' => $topCompanies,
                ];
            })
            ->sortByDesc('start_date')
            ->values();

        /* ===== PAGINACIÓN ===== */
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $collection->slice(($page - 1) * $perPage, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page
        );

        return [
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    };

    /* =========================
       RESULTADO FINAL
    ========================= */
    return response()->json([
        'national' => $build((clone $base)->where('country', 'Peru')),
        'international' => $build((clone $base)->where('country', '!=', 'Peru')),
    ]);
}
}
