<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;
use App\Services\ScrapingStatusService;

class SeniorityIndicatorController extends Controller
{
private function buildGeneralData(array $range, array $careers = []): array
{
    /* =========================================
       GLOBAL
    ========================================= */

    $total = DB::table('job_offers')->count();

    $monthStart = now()->startOfMonth();

    $newMonth = DB::table('job_offers')
        ->where('created_at', '>=', $monthStart)
        ->count();

    $first = DB::table('job_offers')
        ->orderBy('created_at')
        ->value('created_at');

    /* =========================================
       PERIOD (🔥 USA TU BASE REAL)
    ========================================= */

    $base = $this->baseCareerQuery($range, $careers);

    $offers = $base->count();

    $days = \Carbon\Carbon::parse($range['start'])
        ->diffInDays(\Carbon\Carbon::parse($range['end'])) + 1;

    return [
        'global' => [
            'offers_total'     => $total,
            'offers_new_month' => $newMonth,
            'history_age'      => $first
                ? \Carbon\Carbon::parse($first)->diffForHumans(null, true)
                : null,
        ],

        'period' => [
            'offers_analysed' => $offers,
            'avg_per_day'     => $days > 0 ? round($offers / $days, 2) : 0,
            'days_covered'    => $days,
            'date_range'      => [
                'from' => $range['start'],
                'to'   => $range['end'],
            ],
        ],

        /* 🔥 ESTO ES PARA EL BLOQUE DE SCRAPING */
        'scraping' => [
            'exists' => false // luego lo sobreescribes como ya haces
        ],
    ];
}
    /* =====================================================
       0️⃣ Vista principal (Inertia)
    ===================================================== */
   public function index(Request $request, JobMarketStatusBuilder $builder)
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');

    $careers = $request->filled('career')
        ? array_values(array_filter((array) $request->career))
        : [];

    $range = $this->getPeriodRange($period, $year);

    /* =====================================================
       BASE
    ===================================================== */
    $base = $this->baseCareerQuery($range, $careers);
    $vacantesAnalizadas = $base->count();

    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id','name','slug']);

    /* =====================================================
       🔥 JOB MARKET DATA (CLAVE)
    ===================================================== */
   $jobMarketData = $this->buildGeneralData($range, $careers);

    // Scraping status (opcional pero recomendado)
    try {
        $scrapingStatus = ScrapingStatusService::getByEntity('jobs');
    } catch (\Throwable $e) {
        $scrapingStatus = null;
    }

    // Unificar
    $jobMarketData['scraping'] = $scrapingStatus;

    /* =====================================================
       DISTRIBUCIÓN SENIORITY (TU LÓGICA)
    ===================================================== */
    $rows = $this->baseCareerQuery($range, $careers)
        ->select('jo.seniority', DB::raw('COUNT(*) as total'))
        ->groupBy('jo.seniority')
        ->get();

    $total = $rows->sum('total');

    $jobMarketStatus = collect(['junior','mid','senior'])
        ->map(function ($level) use ($rows, $total) {

            $row = $rows->firstWhere('seniority', $level);
            $jobs = $row ? (int)$row->total : 0;

            return [
                'level' => $level,
                'jobs' => $jobs,
                'percentage' => $total > 0
                    ? round(($jobs / $total) * 100, 2)
                    : 0,
            ];
        });



    /* =====================================================
       RENDER
    ===================================================== */
    return Inertia::render('DashboardSeniority/Index', [
        'filters' => [
            'year'   => $year,
            'period' => $period,
            'career' => $careers,
        ],

        'availableCareers' => $availableCareers,

        /* 🔥 ESTE ES EL QUE USA TU MODAL */
        'jobMarketData' => $jobMarketData,

        /* 👉 ESTE ES TU GRÁFICO */
        'jobMarketStatus' => $jobMarketStatus,

        'meta' => [
            'year' => $year,
            'period' => $period,
            'periodo_label' =>
                $period === 's1'
                    ? "Semestre 1 – Enero a Junio {$year}"
                    : "Semestre 2 – Julio a Diciembre {$year}",
            'vacantes_analizadas' => $vacantesAnalizadas,
        ],
    ]);
}

    /* =====================================================
       1️⃣ Normalización de seniority
    ===================================================== */
    public function updateSeniority(Request $request)
    {
        $onlyUnspecified = $request->boolean('only_unspecified', true);

        $sql = "
UPDATE job_offers
SET seniority = CASE
    WHEN LOWER(title) LIKE '%junior%' OR LOWER(title) LIKE '% jr %'
        THEN 'junior'
    WHEN LOWER(title) LIKE '%mid%' OR LOWER(title) LIKE '%semi senior%'
        THEN 'mid'
    WHEN LOWER(title) LIKE '%senior%' OR LOWER(title) LIKE '% sr %'
         OR LOWER(title) LIKE '%lead%' OR LOWER(title) LIKE '%principal%'
        THEN 'senior'
    WHEN experience_level IS NOT NULL AND LOWER(experience_level) IN
        ('junior','jr','entry level','internship')
        THEN 'junior'
    WHEN experience_level IS NOT NULL AND LOWER(experience_level) IN
        ('mid','associate','mid-senior level')
        THEN 'mid'
    WHEN experience_level IS NOT NULL AND LOWER(experience_level) IN
        ('senior','sr','lead','principal','director')
        THEN 'senior'
    ELSE 'unspecified'
END
";

        if ($onlyUnspecified) {
            $sql .= " WHERE seniority IS NULL OR seniority = 'unspecified'";
        }

        $affected = DB::affectingStatement($sql);

        return response()->json([
            'status' => 'ok',
            'rows_updated' => $affected,
        ]);
    }

    /* =====================================================
       2️⃣ Distribución de seniority por carrera
       🔥 NUEVO MODELO: career → technologies → jobs
    ===================================================== */
  public function distributionByCareer(Request $request)
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');

    $range = $this->getPeriodRange($period, $year);

    $careers = DB::table('careers')
        ->when($request->filled('career'), fn($q) =>
            $q->whereIn('slug', (array)$request->career)
        )
        ->where('active', 1)
        ->get(['id','name']);

    $result = [];

    foreach ($careers as $career) {

        // 🔥 Paso 1: obtener jobs únicos para esa carrera
        $jobIds = DB::table('job_offers as jo')
            ->join('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
            ->join('course_technology as ct', 'ct.technology_id', '=', 'tj.technology_id')
            ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
            ->where('cc.career_id', $career->id)
           ->where(function ($q) use ($range) {
    $q->whereBetween('jo.published_at', [$range['start'], $range['end']])
      ->orWhere(function ($q2) use ($range) {
          $q2->whereNull('jo.published_at')
             ->whereBetween('jo.created_at', [$range['start'], $range['end']]);
      });
})
          ->whereIn(DB::raw('LOWER(TRIM(jo.seniority))'), ['junior','mid','senior'])
            ->distinct()
            ->pluck('jo.id');

        if ($jobIds->isEmpty()) {
            $distribution = collect(['junior','mid','senior'])->map(fn($level) => [
                'seniority' => $level,
                'jobs' => 0,
                'percentage' => 0,
            ]);
        } else {

            // 🔥 Paso 2: contar sobre jobs únicos
            $rows = DB::table('job_offers')
                ->whereIn('id', $jobIds)
                ->select('seniority', DB::raw('COUNT(*) as jobs'))
                ->groupBy('seniority')
                ->get();

            $total = $rows->sum('jobs');

            $distribution = collect(['junior','mid','senior'])
                ->map(function ($level) use ($rows, $total) {

                    $row = $rows->firstWhere('seniority', $level);
                    $jobs = $row ? (int)$row->jobs : 0;

                    return [
                        'seniority'  => $level,
                        'jobs'       => $jobs,
                        'percentage' => $total > 0
                            ? round(($jobs / $total) * 100, 2)
                            : 0,
                    ];
                });
        }

        $result[] = [
            'career_id'   => $career->id,
            'career_name' => $career->name,
            'distribution'=> $distribution->values(),
        ];
    }

    return response()->json([
        'status' => 'ok',
        'data'   => $result,
        'meta'   => [
            'source' => 'career-driven (DISTINCT jobs)',
            'coherent_with_market' => true,
        ],
    ]);
}
private function baseCareerQuery(array $range, array $careers = [])
{
    return DB::table('job_offers as jo')
      ->where(function ($q) use ($range) {
    $q->whereBetween('jo.published_at', [$range['start'], $range['end']])
      ->orWhere(function ($q2) use ($range) {
          $q2->whereNull('jo.published_at')
             ->whereBetween('jo.created_at', [$range['start'], $range['end']]);
      });
})
      ->whereIn(DB::raw('LOWER(TRIM(jo.seniority))'), ['junior','mid','senior'])
        ->when(!empty($careers), function ($query) use ($careers) {

            $query->whereExists(function ($q) use ($careers) {
                $q->select(DB::raw(1))
                  ->from('career_course as cc')
                  ->join('careers as ca', 'ca.id', '=', 'cc.career_id')
                  ->whereIn('ca.slug', $careers)
                  ->whereExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('technology_job as tj')
                          ->join('course_technology as ct', 'ct.technology_id', '=', 'tj.technology_id')
                          ->whereColumn('tj.job_offer_id', 'jo.id')
                          ->whereColumn('ct.course_id', 'cc.course_id');
                  });
            });

        });
}
    /* =====================================================
       Utils
    ===================================================== */
    private function getPeriodRange(string $period, int $year): array
    {
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
    public function modalityDistribution(Request $request)
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');

    $range = $this->getPeriodRange($period, $year);

    $data = DB::table('job_offers as jo')
        ->whereBetween('jo.created_at', [
            $range['start'],
            $range['end'],
        ])
        ->select(
            'jo.modality',
            DB::raw('COUNT(*) as total')
        )
        ->groupBy('jo.modality')
        ->get();

    $total = $data->sum('total');

    $result = $data->map(function ($item) use ($total) {
        return [
            'modality' => $item->modality ?? 'No especificado',
            'total' => $item->total,
            'percentage' => $total > 0
                ? round(($item->total / $total) * 100, 1)
                : 0,
        ];
    });

    return response()->json([
        'data' => $result,
    ]);
}

public function evolution(Request $request)
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');
    $filter = $request->get('filter', 'weekly');

    $perPage = min((int) $request->get('per_page', 6), 20);

    $range = $this->getPeriodRange($period, $year);

    /* =========================
       AGRUPADOR
    ========================= */
    switch ($filter) {
        case 'monthly':
            $group = "DATE_FORMAT(jo.created_at, '%Y-%m')";
            break;

        case 'biweekly':
            $group = "
                CONCAT(
                    YEAR(jo.created_at), '-',
                    LPAD(MONTH(jo.created_at),2,'0'), '-',
                    IF(DAY(jo.created_at) <= 15, 1, 2)
                )
            ";
            break;

        default:
            $group = "YEARWEEK(jo.created_at,1)";
            break;
    }

    $rows = DB::table('job_offers as jo')
        ->where(function ($q) use ($range) {
            $q->whereBetween('jo.published_at', [$range['start'], $range['end']])
              ->orWhere(function ($q2) use ($range) {
                  $q2->whereNull('jo.published_at')
                     ->whereBetween('jo.created_at', [$range['start'], $range['end']]);
              });
        })
        ->whereIn(DB::raw('LOWER(TRIM(jo.seniority))'), ['junior','mid','senior'])
        ->select(
            DB::raw("$group as period"),
            DB::raw("MIN(jo.created_at) as start_date"),
            DB::raw("MAX(jo.created_at) as end_date"),
            'jo.seniority',
            DB::raw('COUNT(*) as total')
        )
        ->groupBy(DB::raw($group), 'jo.seniority')
        ->get();

    /* =========================
       TRANSFORMACIÓN
    ========================= */
    $collection = $rows
        ->groupBy('period')
        ->map(function ($items, $period) use ($filter) {

            $total = $items->sum('total');
            $start = $items->min('start_date');
            $end   = $items->max('end_date');

            /* ===== LABEL ===== */
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

            $levels = collect(['junior','mid','senior'])
                ->map(function ($level) use ($items, $total) {

                    $row = $items->firstWhere('seniority', $level);
                    $jobs = $row ? $row->total : 0;

                    return [
                        'level' => strtoupper($level),
                        'jobs' => $jobs,
                        'percentage' => $total > 0
                            ? round(($jobs / $total) * 100, 1)
                            : 0,
                    ];
                })
                ->values();

            return [
                'period' => $period,
                'label' => $label,
                'start_date' => $start,
                'end_date' => $end,
                'total_jobs' => $total,
                'distribution' => $levels,
            ];
        })
        ->sortByDesc('start_date')
        ->values();

    /* =========================
       PAGINACIÓN MANUAL 🔥
    ========================= */
    $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();

    $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
        $collection->slice(($page - 1) * $perPage, $perPage)->values(),
        $collection->count(),
        $perPage,
        $page,
        [
            'path' => request()->url(),
            'query' => request()->query(),
        ]
    );

    return response()->json([
        'data' => $paginated->items(),
        'pagination' => [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ],
    ]);
}
}
