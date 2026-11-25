<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\Indicators\Facts\CareerRankingFacts;
use App\Services\Indicators\IndicatorFacts;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Careers\SyncCareerRolesService;

class RankingCarrerasController extends Controller
{
public function syncRoles(SyncCareerRolesService $service)
{
    $result = $service->run();

    return response()->json([
        'status'  => 'ok',
        'message' => $result['message'] ?? 'Datos actualizados correctamente',
        'data'    => $result,
    ]);
}
    /**
     * 📊 Indicador: Demanda laboral por carrera (basado en roles)
     */
    public function index(Request $request)
    {
        /* ==================================================
           0. Parámetros base (MISMO PATRÓN ISIL)
        ================================================== */
      $DEFAULT_YEAR = 2025;
$DEFAULT_PERIOD = 's2';

$year = (int) $request->get('year', $DEFAULT_YEAR);
$period = $request->get('period', $DEFAULT_PERIOD);

        $period = $request->get('period', 's2');

        if (!in_array($period, ['s1', 's2'])) {
            $period = 's2';
        }

        $range = $this->getPeriodRange($period, $year);

        $countries = array_values(array_filter((array) $request->get('country', [])));
        $careers   = array_values(array_filter((array) $request->get('career', [])));

        /* ==================================================
           1. Carreras disponibles (filtros)
        ================================================== */
        $availableCareers = DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        /* ==================================================
           2. Query: demanda laboral por carrera
        ================================================== */
        // Ofertas únicas por carrera (puestos técnicos), precalculadas por semestre y país
        // (CareerRankingFacts); países y carreras se filtran al leer. Sin excluidas por título.
        $all = CareerRankingFacts::ranking($year, $period, $countries, $careers)->get();
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $ranking = (new \Illuminate\Pagination\LengthAwarePaginator(
            $all->slice(($page - 1) * 6, 6)->values(), $all->count(), 6, $page,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        ))->withQueryString();

        $totalVacantesAnalizadas = CareerRankingFacts::analysed($year, $period);

        $periodoLabel = $period === 's1'
            ? "Semestre 1 – Enero a Junio {$year}"
            : "Semestre 2 – Julio a Diciembre {$year}";

        /* ==================================================
           5. Render (CONTRATO ESTABLE)
        ================================================== */
        return Inertia::render(
            'DashboardRankingCarreras/RankingCarrerasPage',
            [
                'ranking' => $ranking,

                // 🔥 filtros SIEMPRE presentes
                'filters' => [
                    'year'    => $year,
                    'period'  => $period,
                    'career'  => $careers,
                    'country' => $countries,
                ],

                'availableCareers' => $availableCareers,

                // 🔥 meta EXACTAMENTE como lo espera el Header
                'meta' => [
                    'year'                 => $year,
                    'period'               => $period,
                    'periodo_label'        => $periodoLabel,
                    'vacantes_analizadas'  => $totalVacantesAnalizadas,
                    'peso_laboral'         => '100%',
                    'actualizado'          => now()->toDateTimeString(),
                    'updated_at'           => IndicatorFacts::updatedAt('careers_ranking', $year, $period),
                ],
            ]
        );
    }

    /* ==================================================
       Helpers
    ================================================== */
    private function getPeriodRange(string $period, int $year): array
    {
        if ($period === 's1') {
            return [
                'start' => "{$year}-01-01",
                'end'   => "{$year}-06-30",
            ];
        }

        return [
            'start' => "{$year}-07-01",
            'end'   => "{$year}-12-31",
        ];
    }
}
