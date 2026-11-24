<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\Indicators\Facts\ModalityFacts;
use App\Services\Indicators\IndicatorFacts;
use App\Services\Indicators\IndicatorScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\JobMarketStatusBuilder;

class JobModalityIndicatorController extends Controller
{
   public function index(Request $request)
    {
        $year   = (int) $request->get('year', 2026);
        $period = $request->get('period', 's1');

        $range = $this->getPeriodRange($year, $period);

        $filters = [
            'region'  => $request->get('region'),
            'country' => $request->get('country'),
            'city'    => $request->get('city'),
            'source'  => $request->get('source'),
            'year'    => $year,
            'period'  => $period,
        ];

        /* =====================================================
           CLASIFICACIÓN PRINCIPAL (KPIs + Donut)
           SOLO REMOTO / HÍBRIDO / PRESENCIAL
        ===================================================== */

        // Precalculado por periodo (ModalityFacts); los filtros se aplican al leer. Sin ofertas
        // excluidas por título. Cuenta ofertas.
        [$totalVacantes, $rows] = ModalityFacts::classified($year, $period, $filters);

        $data = $rows->map(fn ($row) => [
                'modalidad'  => $row->modalidad,
                'vacantes'   => (int) $row->vacantes,
                'porcentaje' => $totalVacantes > 0
                    ? round(($row->vacantes / $totalVacantes) * 100, 2)
                    : 0,
            ]);

        /* =====================================================
           TENDENCIA MENSUAL (%) — sin los filtros de la página, como antes
        ===================================================== */

        $trendData = ModalityFacts::trend($year, $period)
            ->map(fn ($row) => [
                'month'      => Carbon::create()->month($row->month_num)->translatedFormat('M'),
                'remoto'     => (float) $row->remoto,
                'hibrido'    => (float) $row->hibrido,
                'presencial' => (float) $row->presencial,
            ]);

        $jobMarketStatus = JobMarketStatusBuilder::build([
            'mode'   => 'market',
            'year'   => $year,
            'period' => $period,
        ]);

        return Inertia::render('Dashboard/Indicators/JobModalityIndicatorPage', [
            'data'      => $data,
            'trendData' => $trendData,
            'filters'   => $filters,
            'meta'      => [
                'year'   => $year,
                'period' => $period,
                'periodo_label' => $period === 's1'
                    ? "Semestre 1 – Enero a Junio {$year}"
                    : "Semestre 2 – Julio a Diciembre {$year}",
                'total_vacantes' => $totalVacantes,
                'updated_at' => IndicatorFacts::updatedAt('modality', $year, $period),
            ],
            'jobMarketStatus' => $jobMarketStatus,
        ]);
    }


    /* =====================================================
       AUTOCOMPLETADOS
    ===================================================== */
    public function searchRegions(Request $request)
    {
        $term = trim($request->get('q', ''));

        return DB::table('job_offers')
    ->whereNotNull('region')
    ->where('region','!=','Desconocido')
            ->when($term, fn ($q) =>
                $q->where('region', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('region')
            ->limit(15)
            ->pluck('region');
    }

    public function searchCountries(Request $request)
    {
        $term   = trim($request->get('q', ''));
        $region = $request->get('region');

        return DB::table('job_offers')
            ->whereNotNull('country')
             ->where('region','!=','Desconocido')
            ->when($region, fn ($q) =>
                $q->where('region', $region)
            )
            ->when($term, fn ($q) =>
                $q->where('country', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('country')
            ->limit(15)
            ->pluck('country');
    }

    public function searchCities(Request $request)
    {
        $term    = trim($request->get('q', ''));
        $country = $request->get('country');

        return DB::table('job_offers')
            ->whereNotNull('city')
            ->when($country, fn ($q) =>
                $q->where('country', $country)
            )
            ->when($term, fn ($q) =>
                $q->where('city', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('city')
            ->limit(15)
            ->pluck('city');
    }

    private function getPeriodRange(int $year, string $period): array
    {
        return $period === 's1'
            ? ['start' => "{$year}-01-01", 'end' => "{$year}-06-30"]
            : ['start' => "{$year}-07-01", 'end' => "{$year}-12-31"];
    }
}
