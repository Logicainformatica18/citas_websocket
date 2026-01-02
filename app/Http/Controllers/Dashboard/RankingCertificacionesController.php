<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RankingCertificacionesController extends Controller
{
  public function index(Request $request)
{
    /* ==================================================
       0. Año y periodo (default: S2 2025)
    ================================================== */
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');

    $range = $this->getPeriodRange($period, $year);

    /* ==================================================
       1. Filtros recibidos (string o array)
    ================================================== */
    $categories = $request->get('category', []);
    $careers    = $request->get('career', []);

    $categories = is_array($categories)
        ? array_filter($categories)
        : array_filter([$categories]);

    $careers = is_array($careers)
        ? array_filter($careers)
        : array_filter([$careers]);

    /* ==================================================
       2. Query base del ranking (CON FECHAS)
    ================================================== */
    $rankingQuery = DB::table('certification_job as cj')
        ->join('certifications as c', 'c.id', '=', 'cj.certification_id')
        ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->select(
            'c.id',
            'c.name',
            'c.vendor',
            'c.level',
            'c.category',
            DB::raw('COUNT(DISTINCT cj.job_offer_id) as total_jobs')
        );

    /* ==================================================
       3. Filtro por categorías tecnológicas
    ================================================== */
    if (!empty($categories)) {
        $rankingQuery->whereIn('c.category', $categories);
    }

    /* ==================================================
       4. Filtro por carreras ISIL
    ================================================== */
    if (!empty($careers)) {
        $careerMap = [
            'architecture' => ['data'],
            'cyber'        => ['security', 'cloud'],
            'data_ai'      => ['ai', 'data'],
            'cloud'        => ['cloud'],
            'software'     => ['cloud', 'ai', 'data'],
            'networks'     => ['networking', 'cloud'],
            'information_systems' => ['cloud', 'data'],
            'systems_engineering' => ['cloud', 'ai', 'data'],
            'it' => ['cloud', 'data'],
        ];

        $careerCategories = collect($careers)
            ->flatMap(fn ($career) => $careerMap[$career] ?? [])
            ->unique()
            ->values()
            ->toArray();

        if (!empty($careerCategories)) {
            $rankingQuery->whereIn('c.category', $careerCategories);
        }
    }

    /* ==================================================
       5. Ejecutar ranking (PAGINADO)
    ================================================== */
    $ranking = $rankingQuery
        ->groupBy('c.id', 'c.name', 'c.vendor', 'c.level', 'c.category')
        ->orderByDesc('total_jobs')
        ->paginate(4)
        ->withQueryString();

    /* ==================================================
       6. Vacantes analizadas reales (GLOBAL)
    ================================================== */
    $totalVacantesAnalizadas = DB::table('certification_job as cj')
        ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('cj.job_offer_id')
        ->count('cj.job_offer_id');

    /* ==================================================
       7. KPIs (basados en página actual)
    ================================================== */
    $rankingCollection = collect($ranking->items());
    $topCertification  = $rankingCollection->first();

    $altaDemanda = $rankingCollection->take(10)->count();

    $altaProyeccion = $rankingCollection
        ->filter(fn ($r) => in_array($r->category, ['cloud', 'ai', 'data']))
        ->take(12)
        ->count();

    $areaDestacada = $rankingCollection
        ->groupBy('category')
        ->map(fn ($items) => $items->sum('total_jobs'))
        ->sortDesc()
        ->keys()
        ->first();

    /* ==================================================
       8. Render
    ================================================== */
    return Inertia::render(
        'DashboardRankingCertificaciones/RankingCertificacionesPage',
        [
            'ranking' => $ranking,

            'kpis' => [
                'top_certification' => $topCertification ? [
                    'name'   => $topCertification->name,
                    'vendor' => $topCertification->vendor,
                    'jobs'   => $topCertification->total_jobs,
                ] : null,

                'alta_demanda'    => $altaDemanda,
                'alta_proyeccion' => $altaProyeccion,
                'area_destacada'  => $areaDestacada,
            ],

            'filters' => [
                'category' => array_values($categories),
                'career'   => array_values($careers),
                'year'     => $year,
                'period'   => $period,
            ],

            'meta' => [
                'year'   => $year,
                'period' => $period,
                'periodo_label' => $period === 's1'
                    ? "Semestre 1 – Enero a Junio $year"
                    : "Semestre 2 – Julio a Diciembre $year",
                'vacantes_analizadas' => $totalVacantesAnalizadas,
                'actualizado' => now()->toDateTimeString(),
            ],
        ]
    );
}

public function jobsByCertification(Request $request, int $certificationId)
{
    // ===============================
    // 1. Parámetros controlados
    // ===============================
    $perPage = min((int) $request->get('per_page', 10), 50); // límite de seguridad
    $page    = (int) $request->get('page', 1);

    // ===============================
    // 2. Query base (LIVIANA)
    // ===============================
    $jobs = DB::table('job_offers as j')
        ->join('certification_job as cj', 'cj.job_offer_id', '=', 'j.id')
        ->where('cj.certification_id', $certificationId)
        ->select(
            'j.id',
            'j.title',
            'j.company',
            'j.location',
            'j.country',
            'j.modality',
            'j.salary_min',
            'j.salary_max',
            'j.source',
            'j.published_at',
            'j.url' // 👈 URL original
        )
        ->orderByDesc('j.published_at')
        ->paginate(
            $perPage,
            ['*'],
            'page',
            $page
        );

    // ===============================
    // 3. Respuesta JSON paginada
    // ===============================
    return response()->json($jobs);
}

private function getPeriodRange(string $period, int $year): array
{
    if ($period === 's1') {
        return [
            'start' => "$year-01-01",
            'end'   => "$year-06-30",
        ];
    }

    return [
        'start' => "$year-07-01",
        'end'   => "$year-12-31",
    ];
}
}
