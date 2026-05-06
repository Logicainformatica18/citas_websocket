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
    DB::statement("SET lc_time_names = 'es_ES'");

    \Carbon\Carbon::setLocale('es');

    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');
    $filter = $request->get('filter', 'weekly');

    $perPage = min(
        (int) $request->get('per_page', 5),
        20
    );

    $range = $period === 's1'
        ? [
            'start' => "$year-01-01",
            'end'   => "$year-06-30",
        ]
        : [
            'start' => "$year-07-01",
            'end'   => "$year-12-31",
        ];

    /*
    ==================================================
    🔥 FILTROS
    ==================================================
    */

    switch ($filter) {

        /*
        ==================================================
        📅 MONTHLY
        ==================================================
        */
        case 'monthly':

            $group = "
                DATE_FORMAT(
                    COALESCE(published_at, created_at),
                    '%Y-%m'
                )
            ";

            $labelSql = "
                CONCAT(
                    UCASE(
                        LEFT(
                            MONTHNAME(
                                MIN(
                                    COALESCE(
                                        published_at,
                                        created_at
                                    )
                                )
                            ),
                            1
                        )
                    ),
                    SUBSTRING(
                        MONTHNAME(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            )
                        ),
                        2
                    ),
                    ' ',
                    YEAR(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        )
                    )
                )
            ";

            $startDate = "
                DATE_FORMAT(
                    MIN(
                        COALESCE(
                            published_at,
                            created_at
                        )
                    ),
                    '%Y-%m-01'
                )
            ";

            $endDate = "
                LAST_DAY(
                    MIN(
                        COALESCE(
                            published_at,
                            created_at
                        )
                    )
                )
            ";

            break;

        /*
        ==================================================
        📅 BIWEEKLY
        ==================================================
        */
        case 'biweekly':

            $group = "
                CONCAT(
                    YEAR(
                        COALESCE(
                            published_at,
                            created_at
                        )
                    ),
                    '-',
                    LPAD(
                        MONTH(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ),
                        2,
                        '0'
                    ),
                    '-',
                    IF(
                        DAY(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ) <= 15,
                        1,
                        2
                    )
                )
            ";

            $labelSql = "
                CASE

                    WHEN DAY(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        )
                    ) <= 15

                    THEN CONCAT(
                        'Primera quincena de ',
                        MONTHNAME(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            )
                        )
                    )

                    ELSE CONCAT(
                        'Segunda quincena de ',
                        MONTHNAME(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            )
                        )
                    )

                END
            ";

            $startDate = "
                CASE

                    WHEN DAY(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        )
                    ) <= 15

                    THEN DATE_FORMAT(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ),
                        '%Y-%m-01'
                    )

                    ELSE DATE_FORMAT(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ),
                        '%Y-%m-16'
                    )

                END
            ";

            $endDate = "
                CASE

                    WHEN DAY(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        )
                    ) <= 15

                    THEN DATE_FORMAT(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ),
                        '%Y-%m-15'
                    )

                    ELSE LAST_DAY(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        )
                    )

                END
            ";

            break;

        /*
        ==================================================
        📅 WEEKLY
        ==================================================
        */
        default:

            $group = "
                YEARWEEK(
                    COALESCE(
                        published_at,
                        created_at
                    ),
                    1
                )
            ";

            $labelSql = "
                CONCAT(
                    'Semana ',
                    CEIL(
                        DAY(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            )
                        ) / 7
                    ),
                    ' de ',
                    CONCAT(
                        UCASE(
                            LEFT(
                                MONTHNAME(
                                    MIN(
                                        COALESCE(
                                            published_at,
                                            created_at
                                        )
                                    )
                                ),
                                1
                            )
                        ),
                        SUBSTRING(
                            MONTHNAME(
                                MIN(
                                    COALESCE(
                                        published_at,
                                        created_at
                                    )
                                )
                            ),
                            2
                        )
                    )
                )
            ";

            $startDate = "
                DATE_SUB(
                    MIN(
                        DATE(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        )
                    ),
                    INTERVAL WEEKDAY(
                        MIN(
                            DATE(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            )
                        )
                    ) DAY
                )
            ";

            $endDate = "
                DATE_ADD(
                    DATE_SUB(
                        MIN(
                            DATE(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            )
                        ),
                        INTERVAL WEEKDAY(
                            MIN(
                                DATE(
                                    COALESCE(
                                        published_at,
                                        created_at
                                    )
                                )
                            )
                        ) DAY
                    ),
                    INTERVAL 6 DAY
                )
            ";

            break;
    }

    /*
    ==================================================
    🔥 BASE QUERY
    ==================================================
    */

    $base = DB::table('job_offers')

        ->whereNotNull('company')

        ->where(function ($q) use ($range) {

            $q->whereBetween('published_at', [
                $range['start'],
                $range['end'],
            ])

            ->orWhere(function ($q2) use ($range) {

                $q2->whereNull('published_at')

                   ->whereBetween('created_at', [
                       $range['start'],
                       $range['end'],
                   ]);
            });
        });

    /*
    ==================================================
    🔥 BUILDER
    ==================================================
    */

    $build = function ($query) use (
        $group,
        $labelSql,
        $startDate,
        $endDate,
        $perPage
    ) {

        $rows = $query

            ->select(

                DB::raw("$group as period"),

                DB::raw("$labelSql as label"),

                DB::raw("$startDate as start_date"),

                DB::raw("$endDate as end_date"),

                DB::raw("
                    UPPER(
                        TRIM(company)
                    ) as company
                "),

                DB::raw("COUNT(*) as total")
            )

            ->groupBy(
                DB::raw($group),
                DB::raw("UPPER(TRIM(company))")
            )

            ->get();

        $collection = $rows

            ->groupBy('period')

            ->map(function ($items) {

                $first = $items->first();

                $total = $items->sum('total');

                return [

                    'period' => $first->period,

                    'label' => $first->label,

                    'start_date' => $first->start_date,

                    'end_date' => $first->end_date,

                    'total_jobs' => $total,

                    'companies' => $items

                        ->sortByDesc('total')

                        ->take(5)

                        ->values()

                        ->map(function ($c) use ($total) {

                            return [

                                'company' => $c->company,

                                'jobs' => $c->total,

                                'percentage' => $total > 0
                                    ? round(
                                        ($c->total / $total) * 100,
                                        1
                                    )
                                    : 0,
                            ];
                        }),
                ];
            })

            ->sortByDesc('start_date')

            ->values();

        $page = \Illuminate\Pagination\LengthAwarePaginator
            ::resolveCurrentPage();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(

            $collection
                ->slice(
                    ($page - 1) * $perPage,
                    $perPage
                )
                ->values(),

            $collection->count(),

            $perPage,

            $page
        );

        return [

            'data' => $paginated->items(),

            'pagination' => [

                'current_page' =>
                    $paginated->currentPage(),

                'last_page' =>
                    $paginated->lastPage(),

                'per_page' =>
                    $paginated->perPage(),

                'total' =>
                    $paginated->total(),
            ],
        ];
    };

    /*
    ==================================================
    🚀 RESPONSE
    ==================================================
    */

    return response()->json([

        'national' => $build(
            (clone $base)
                ->where('country', 'Peru')
        ),

        'international' => $build(
            (clone $base)
                ->where('country', '!=', 'Peru')
        ),
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
