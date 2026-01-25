<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
        $demandQuery = DB::table('tech_position_job_offer as tpo')
            ->join('job_offers as jo', 'jo.id', '=', 'tpo.job_offer_id')
            ->join('career_tech_position as ctp', 'ctp.tech_position_id', '=', 'tpo.tech_position_id')
            ->join('careers as c', 'c.id', '=', 'ctp.career_id')
            ->whereBetween('jo.published_at', [$range['start'], $range['end']])
            ->select(
                'c.id',
                'c.name',
                DB::raw('COUNT(DISTINCT tpo.job_offer_id) as total_jobs')
            )
            ->groupBy('c.id', 'c.name');

        /* ==================================================
           2.1 Filtros opcionales
        ================================================== */
        if (!empty($countries)) {
            $demandQuery->whereIn('jo.country', $countries);
        }

        if (!empty($careers)) {
            $demandQuery->whereIn('c.slug', $careers);
        }

        /* ==================================================
           3. Ranking final
        ================================================== */
        $ranking = $demandQuery
            ->orderByDesc('total_jobs')
            ->paginate(6)
            ->withQueryString();

        /* ==================================================
           4. KPIs / META (ESTRUCTURA ISIL)
        ================================================== */
    /* ==================================================
   4. KPIs / META (CORRECTO PARA ESTE INDICADOR)
================================================== */
$totalVacantesAnalizadas = DB::table('tech_position_job_offer as tpo')
    ->join('job_offers as jo', 'jo.id', '=', 'tpo.job_offer_id')
    ->join('career_tech_position as ctp', 'ctp.tech_position_id', '=', 'tpo.tech_position_id')
    ->whereBetween('jo.published_at', [$range['start'], $range['end']])
    ->distinct('tpo.job_offer_id')
    ->count('tpo.job_offer_id');

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
