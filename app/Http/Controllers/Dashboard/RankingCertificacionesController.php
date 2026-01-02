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
           0. Filtros recibidos (SOPORTA string o array)
        ================================================== */
        $categories = $request->get('category', []);
        $careers    = $request->get('career', []);

        // Normalizar a array
        $categories = is_array($categories)
            ? array_filter($categories)
            : array_filter([$categories]);

        $careers = is_array($careers)
            ? array_filter($careers)
            : array_filter([$careers]);

        /* ==================================================
           1. Query base del ranking
        ================================================== */
        $rankingQuery = DB::table('certification_job as cj')
            ->join('certifications as c', 'c.id', '=', 'cj.certification_id')
            ->select(
                'c.id',
                'c.name',
                'c.vendor',
                'c.level',
                'c.category',
                DB::raw('COUNT(DISTINCT cj.job_offer_id) as total_jobs')
            );

        /* ==================================================
           2. Filtro por ÁREAS TECNOLÓGICAS (MULTI)
        ================================================== */
        if (!empty($categories)) {
            $rankingQuery->whereIn('c.category', $categories);
        }

        /* ==================================================
           3. Filtro por CARRERAS ISIL (MULTI + mapping)
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
           4. Ejecutar ranking
        ================================================== */
        $ranking = $rankingQuery
            ->groupBy('c.id', 'c.name', 'c.vendor', 'c.level', 'c.category')
            ->orderByDesc('total_jobs')
            ->get();

        /* ==================================================
           5. Total de vacantes analizadas (filtrado)
        ================================================== */
        $totalJobs = $ranking->sum('total_jobs');

        /* ==================================================
           6. Certificación TOP
        ================================================== */
        $topCertification = $ranking->first();

        /* ==================================================
           7. Alta demanda (top 10)
        ================================================== */
        $altaDemanda = $ranking->take(10)->count();

        /* ==================================================
           8. Alta proyección
        ================================================== */
        $altaProyeccion = $ranking
            ->filter(fn ($r) => in_array($r->category, ['cloud', 'ai', 'data']))
            ->take(12)
            ->count();

        /* ==================================================
           9. Área destacada
        ================================================== */
        $areaDestacada = $ranking
            ->groupBy('category')
            ->map(fn ($items) => $items->sum('total_jobs'))
            ->sortDesc()
            ->keys()
            ->first();

        /* ==================================================
           10. Render
        ================================================== */
        return Inertia::render(
            'DashboardRankingCertificaciones/RankingCertificacionesPage',
            [
                'ranking' => $ranking,

                'kpis' => [
                    'top_certification' => $topCertification ? [
                        'name'   => $topCertification->name,
                        'vendor' => $topCertification->vendor,
                        'score'  => $totalJobs > 0
                            ? round(($topCertification->total_jobs / $totalJobs) * 100, 1)
                            : 0,
                    ] : null,

                    'alta_demanda'    => $altaDemanda,
                    'alta_proyeccion' => $altaProyeccion,
                    'area_destacada'  => $areaDestacada,
                ],

                /* ===============================
                   Filtros activos (frontend)
                =============================== */
                'filters' => [
                    'category' => array_values($categories),
                    'career'   => array_values($careers),
                ],

                /* ===============================
                   Metadata
                =============================== */
                'meta' => [
                    'periodo'     => 'Semestre 1 – Enero a Junio 2025',
                    'total_jobs'  => $totalJobs,
                    'actualizado' => now()->toDateTimeString(),
                ],
            ]
        );
    }
    public function jobsByCertification(Request $request, int $certificationId)
{
    $perPage = $request->get('per_page', 10);

    $jobs = DB::table('job_offers as j')
        ->join('certification_job as cj', 'cj.job_offer_id', '=', 'j.id')
        ->join('certifications as c', 'c.id', '=', 'cj.certification_id')
        ->where('c.id', $certificationId)
        ->select(
            'j.id',
            'j.title',
            'j.company',
            'j.location',
            'j.country',
            'j.modality',
            'j.seniority',
            'j.salary_min',
            'j.salary_max',
            'j.source',
            'j.published_at'
        )
        ->orderByDesc('j.published_at')
        ->paginate($perPage);

    return response()->json($jobs);
}

}
