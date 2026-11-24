<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\Indicators\Facts\SeniorityFacts;
use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorRefresher;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;
use App\Services\ScrapingStatusService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exports\SeniorityEvolutionExport;
use App\Exports\SeniorityEvolutionCareerExport;
use Maatwebsite\Excel\Facades\Excel;
class SeniorityIndicatorController extends Controller
{
private function buildGeneralData(array $range, int $offers): array
{
    // Totales globales: los de Datos Generales (caché de 1 h, sin ofertas excluidas por título)
    $global = JobMarketStatusController::get()['global'];

    $first = DB::table('job_offers')
        ->orderBy('created_at')
        ->value('created_at');

    $days = \Carbon\Carbon::parse($range['start'])
        ->diffInDays(\Carbon\Carbon::parse($range['end'])) + 1;

    return [
        'global' => [
            'offers_total'     => $global['offers_total'],
            'offers_new_month' => $global['offers_new_month'],
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
       BASE: ofertas por seniority (precalculado por periodo y carrera; varias carreras, en vivo)
    ===================================================== */
    $careerIds = $careers
        ? DB::table('careers')->whereIn('slug', $careers)->pluck('id')->map(fn ($id) => (int) $id)->all()
        : [];
    $rows = SeniorityFacts::bySeniority($year, $period, $careerIds);
    $vacantesAnalizadas = (int) $rows->sum('total');

    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id','name','slug']);

    /* =====================================================
       🔥 JOB MARKET DATA (CLAVE)
    ===================================================== */
   $jobMarketData = $this->buildGeneralData($range, $vacantesAnalizadas);

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
            'updated_at' => IndicatorFacts::updatedAt('seniority', $year, $period),
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

        // El seniority cambió: recalcular el precálculo del indicador en la cola por defecto
        IndicatorRefresher::queue(['seniority']);

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

    // MENCIONES: cada par (oferta, tecnología de los cursos de la carrera) suma 1; una oferta con
    // Java y Python, ambas de la carrera, suma 2. Sin ofertas excluidas por título; asociaciones
    // completas. Precalculado por periodo (SeniorityFacts); el total suma todas las variantes del
    // seniority guardado y cada nivel cuenta solo el valor exacto, como antes.
    $byCareer = SeniorityFacts::mentionsByCareer($year, $period, $careers->pluck('id')->map(fn ($id) => (int) $id)->all());

    $result = [];

    foreach ($careers as $career) {
        $rows = $byCareer->get($career->id, collect());
        $total = $rows->sum('jobs');

        $distribution = collect(['junior', 'mid', 'senior'])
            ->map(function ($level) use ($rows, $total) {
                $row = $rows->firstWhere('seniority', $level);
                $jobs = $row ? (int) $row->jobs : 0;

                return [
                    'seniority'  => $level,
                    'jobs'       => $jobs,
                    'percentage' => $total > 0
                        ? round(($jobs / $total) * 100, 2)
                        : 0,
                ];
            });

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
            'source' => 'career-driven (menciones: oferta × tecnología de la carrera)',
            'updated_at' => IndicatorFacts::updatedAt('seniority_career', $year, $period),
            'coherent_with_market' => true,
        ],
    ]);
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

    $data = SeniorityFacts::byModality($year, $period);   // precalculado por periodo

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
    $year    = (int) $request->get('year', 2026);
    $filter  = $request->get('filter', 'weekly'); // 'weekly', 'biweekly', 'monthly'

    // 🎯 CAPTURAMOS EL FILTRO DE CARRERA (Botones superiores). Si no viene, es 'global'
    $careerSlug = $request->get('career_slug', 'global');

    $perPage = min(
        (int) $request->get('per_page', 6),
        20
    );

    /*
    ==================================================
    🔵 EXTRAER DE CACHÉ SEGMENTADO POR CARRERA
    ==================================================
    */
    $cacheRows = DB::table('seniority_evolution_cache')
        ->where('year', $year)
        ->where('period_type', $filter)
        ->where('career_slug', $careerSlug)
        ->get();

    /*
    ==================================================
    📦 TRANSFORMACIÓN PARA EMPAREJAR TU ESTRUCTURA ANTERIOR
    ==================================================
    */
    $collection = $cacheRows->map(function ($row) use ($filter) {

        /*
        ==========================================
        📅 CORRECCIÓN DE LABEL ADAPTATIVO (ES)
        ==========================================
        Aprovechamos que el comando ya generó labels deterministas limpios,
        pero si prefieres forzar el formato nativo Carbon de tu método, se evalúa aquí:
        */
        $start = $row->start_date;
        $label = $row->period_label; // Toma el valor directo precalculado: "Semana 3 - Mayo 2026"

        // Si tu UI depende estrictamente del string en minúsculas "de [mes]", lo formateamos:
        if ($filter === 'monthly') {
            $label = \Carbon\Carbon::parse($start)->locale('es')->translatedFormat('F Y');
        } elseif ($filter === 'biweekly') {
            $month = \Carbon\Carbon::parse($start)->locale('es')->translatedFormat('F');
            $day = \Carbon\Carbon::parse($start)->day;
            $label = $day <= 15 ? "Primera quincena de {$month}" : "Segunda quincena de {$month}";
        }

        return [
            'period'     => $row->period_label,
            'label'      => $label,
            'start_date' => $start,
            'end_date'   => $row->end_date,
            'total_jobs' => (int) $row->total_jobs,

            // Reconstruimos la estructura exacta que tu frontend recorre con el v-for
            'distribution' => collect([
                [
                    'level'      => 'JUNIOR',
                    'jobs'       => (int) $row->junior_count,
                    'percentage' => (float) $row->junior_pct,
                ],
                [
                    'level'      => 'MID',
                    'jobs'       => (int) $row->mid_count,
                    'percentage' => (float) $row->mid_pct,
                ],
                [
                    'level'      => 'SENIOR',
                    'jobs'       => (int) $row->senior_count,
                    'percentage' => (float) $row->senior_pct,
                ],
            ])
        ];
    })
    ->sortByDesc('start_date') // Mantiene tu orden cronológico inverso
    ->values();

    /*
    ==================================================
    📄 PAGINATION (Tu lógica matemática original intacta)
    ==================================================
    */
    $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();

    $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
        $collection->slice(($page - 1) * $perPage, $perPage)->values(),
        $collection->count(),
        $perPage,
        $page,
        [
            'path'  => request()->url(),
            'query' => request()->query(),
        ]
    );

    /*
    ==================================================
    🚀 RESPONSE
    ==================================================
    */
    return response()->json([
        'filter'     => $filter,
        'career'     => $careerSlug, // Feedback para validar qué carrera devolvió la API
        'data'       => $paginated->items(),
        'pagination' => [
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
        ],
    ]);
}
public function exportEvolution(Request $request)
{
    // 1. Recibimos los parámetros dinámicos que el frontend demostró enviar
    $year       = (int) $request->get('year', date('Y'));
    $period     = $request->get('period', 's1');
    $filter     = $request->get('filter', 'weekly');

    // 🔥 AQUÍ ESTÁ LA CORRECCIÓN: Capturamos el career_slug ('global' o el slug de la carrera)
    $careerSlug = $request->get('career_slug', 'global');

    // 2. Calculamos el rango del segundo semestre del 2026 (2026-07-01 al 2026-12-31)
    $range = $this->getPeriodRange($period, $year);

    // 3. Pasamos el $careerSlug al constructor del Export para que el query no se rompa
    return Excel::download(
        new SeniorityEvolutionExport($range, $filter, $careerSlug),
        "seniority_evolution_{$careerSlug}_{$period}_{$year}.xlsx"
    );
}
public function evolutionCareers(Request $request)
{
    // 1️⃣ Capturamos los filtros que vienen del Frontend (con fallbacks seguros)
    $year   = (int) $request->get('year', date('Y'));
    $period = $request->get('period', 's1');
    $filter = $request->get('filter', 'weekly'); // 'weekly', 'biweekly', 'monthly'

    // 2️⃣ Obtenemos los límites del semestre para filtrar la caché
    $range = $this->getPeriodRange($period, $year);

    // 3️⃣ Consultamos la tabla caché usando los índices optimizados
    $cacheRows = DB::table('career_evolution_cache')
        ->where('year', $year)
        ->where('period_type', $filter)
        ->whereBetween('start_date', [$range['start'], $range['end']])
        ->orderBy('start_date', 'desc') // Para que muestre lo más reciente primero
        ->orderByDesc('jobs')            // Ordena las carreras internamente por volumen
        ->get();

    // 4️⃣ Agrupamos por el tramo temporal ('period_label') para estructurar el JSON idéntico a tu formato original
    $data = $cacheRows->groupBy('period_label')->map(function ($items, $periodLabel) {
        $first = $items->first();

        return [
            'period'     => $first->period_label, // Tu frontend espera el identificador/label del tramo
            'label'      => $first->period_label,
            'start_date' => $first->start_date,
            'end_date'   => $first->end_date,
            'total_jobs' => (int) $first->total_market_jobs, // El total global del mercado guardado en la fila

            // Mapeamos el listado de carreras dentro de este tramo específico
            'careers'    => $items->map(function ($row) {
                return [
                    'career_name' => $row->career_name,
                    'jobs'        => (int) $row->jobs,
                    'percentage'  => (float) $row->percentage, // Ya calculado exactamente por el comando
                ];
            })->values()
        ];
    })->values();

    // 5️⃣ Respuesta veloz al Frontend en milisegundos 🚀
    return response()->json([
        'filter' => $filter,
        'data'   => $data,
    ]);
}
public function exportEvolutionCareers(Request $request)
{
    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1');

    return Excel::download(
        new SeniorityEvolutionCareerExport($year, $period),
        "evolution_careers.xlsx"
    );
}
}
