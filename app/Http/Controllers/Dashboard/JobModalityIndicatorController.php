<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JobModalityIndicatorController extends Controller
{
    public function index(Request $request)
    {
        /* =====================================================
           0. Período (ESTÁNDAR ISIL)
        ===================================================== */
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');

        $range = $this->getPeriodRange($year, $period);

        /* =====================================================
           1. Filtros activos
        ===================================================== */
        $filters = [
            'region'  => $request->get('region'),
            'country' => $request->get('country'),
            'city'    => $request->get('city'),
            'source'  => $request->get('source'),
            'year'    => $year,
            'period'  => $period,
        ];

        /* =====================================================
           2. Subquery clasificada
        ===================================================== */
        $classified = DB::table('job_offers')
            ->selectRaw("
                CASE
                    WHEN modality IN ('fully_remote', 'remote') THEN 'remoto'
                    WHEN modality IN ('hybrid', 'remote_local') THEN 'híbrido'
                    WHEN modality = 'no_remote' THEN 'presencial'
                    ELSE 'desconocido'
                END AS modalidad
            ")
            ->whereNotNull('modality')
            ->whereBetween('published_at', [$range['start'], $range['end']]);

        if ($filters['region']) {
            $classified->where('region', $filters['region']);
        }

        if ($filters['country']) {
            $classified->where('country', $filters['country']);
        }

        if ($filters['city']) {
            $classified->where('city', $filters['city']);
        }

        if ($filters['source']) {
            $classified->where('source', $filters['source']);
        }

        /* =====================================================
           3. Total
        ===================================================== */
        $totalVacantes = (clone $classified)->count();

        /* =====================================================
           4. Agregación
        ===================================================== */
        $data = DB::query()
            ->fromSub($classified, 't')
            ->select('modalidad', DB::raw('COUNT(*) as vacantes'))
            ->groupBy('modalidad')
            ->orderByDesc('vacantes')
            ->get()
            ->map(fn ($row) => [
                'modalidad'  => $row->modalidad,
                'vacantes'   => (int) $row->vacantes,
                'porcentaje' => $totalVacantes > 0
                    ? round(($row->vacantes / $totalVacantes) * 100, 2)
                    : 0,
            ]);

        /* =====================================================
           5. Render
        ===================================================== */
        return Inertia::render('Dashboard/Indicators/JobModalityIndicatorPage', [
            'filters' => $filters,
            'meta' => [
                'year'   => $year,
                'period' => $period,
                'periodo_label' => $period === 's1'
                    ? "Semestre 1 – Enero a Junio {$year}"
                    : "Semestre 2 – Julio a Diciembre {$year}",
                'total_vacantes' => $totalVacantes,
            ],
            'data' => $data,
        ]);
    }

    /* =====================================================
       AUTOCOMPLETE: REGIONES
    ===================================================== */
    public function searchRegions(Request $request)
    {
        $term = trim($request->get('q', ''));

        return DB::table('job_offers')
            ->whereNotNull('region')
            ->when($term, fn ($q) =>
                $q->where('region', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('region')
            ->limit(15)
            ->pluck('region');
    }

    /* =====================================================
       AUTOCOMPLETE: PAÍSES
    ===================================================== */
    public function searchCountries(Request $request)
    {
        $term   = trim($request->get('q', ''));
        $region = $request->get('region');

        return DB::table('job_offers')
            ->whereNotNull('country')
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

    /* =====================================================
       AUTOCOMPLETE: CIUDADES
    ===================================================== */
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

    /* =====================================================
       Helper período
    ===================================================== */
    private function getPeriodRange(int $year, string $period): array
    {
        return $period === 's1'
            ? ['start' => "{$year}-01-01", 'end' => "{$year}-06-30"]
            : ['start' => "{$year}-07-01", 'end' => "{$year}-12-31"];
    }
}
