<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;


class SeniorityIndicatorController extends Controller
{
    /* =====================================================
       0️⃣ Vista principal (Inertia)
    ===================================================== */
    public function index(Request $request)
    {
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');

        $careers = $request->filled('career')
            ? array_values(array_filter((array) $request->career))
            : [];

        $range = $this->getPeriodRange($period, $year);

        /* =================================================
           Vacantes analizadas (BASE DE MERCADO REAL)
        ================================================= */
        $vacantesQuery = DB::table('job_offers as jo')
            ->whereBetween(
                DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
                [$range['start'], $range['end']]
            )
            ->whereIn('jo.seniority', ['junior','mid','senior']);

        if (!empty($careers)) {
            $vacantesQuery
                ->join('tech_position_job_offer as tpjo', 'tpjo.job_offer_id', '=', 'jo.id')
                ->join('career_tech_position as ctp', 'ctp.tech_position_id', '=', 'tpjo.tech_position_id')
                ->join('careers as ca', 'ca.id', '=', 'ctp.career_id')
                ->whereIn('ca.slug', $careers);
        }

        $vacantesAnalizadas = $vacantesQuery
            ->distinct('jo.id')
            ->count('jo.id');

        $availableCareers = DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id','name','slug']);


$jobMarketStatus = JobMarketStatusBuilder::build([
    'mode'   => 'market', // ✅ AHORA SÍ
    'year'   => $year,
    'period' => $period,
]);



        return Inertia::render('DashboardSeniority/Index', [
            'filters' => [
                'year'   => $year,
                'period' => $period,
                'career' => $careers,
            ],
            'availableCareers' => $availableCareers,
            'meta' => [
                'year' => $year,
                'period' => $period,
                'periodo_label' =>
                    $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",
                'vacantes_analizadas' => $vacantesAnalizadas,
            ],
                // 🔥 MISMO NOMBRE, MISMA FORMA
    'jobMarketStatus' => $jobMarketStatus,
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

    WHEN LOWER(title) LIKE '%junior%'
         OR LOWER(title) LIKE '% jr %'
    THEN 'junior'

    WHEN LOWER(title) LIKE '%mid%'
         OR LOWER(title) LIKE '%semi senior%'
    THEN 'mid'

    WHEN LOWER(title) LIKE '%senior%'
         OR LOWER(title) LIKE '% sr %'
         OR LOWER(title) LIKE '%lead%'
         OR LOWER(title) LIKE '%principal%'
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
    ===================================================== */
    public function distributionByCareer(Request $request)
    {
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');

        $careers = $request->filled('career')
            ? array_values(array_filter((array) $request->career))
            : [];

        $range = $this->getPeriodRange($period, $year);

        /* ========= BASE FILTRADA ========= */
        $base = DB::table('job_offers as jo')
            ->join('tech_position_job_offer as tpjo', 'tpjo.job_offer_id', '=', 'jo.id')
            ->join('career_tech_position as ctp', 'ctp.tech_position_id', '=', 'tpjo.tech_position_id')
            ->join('careers as ca', 'ca.id', '=', 'ctp.career_id')
            ->whereBetween(
                DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
                [$range['start'], $range['end']]
            )
            ->whereIn('jo.seniority', ['junior','mid','senior'])
            ->when(!empty($careers), fn ($q) =>
                $q->whereIn('ca.slug', $careers)
            );

        /* ========= TOTALES POR CARRERA ========= */
        $totals = (clone $base)
            ->select(
                'ca.id as career_id',
                DB::raw('COUNT(DISTINCT jo.id) as total_jobs')
            )
            ->groupBy('ca.id');

        /* ========= DISTRIBUCIÓN ========= */
        $rows = $base
            ->joinSub($totals, 't', fn ($j) =>
                $j->on('t.career_id', '=', 'ca.id')
            )
            ->select(
                'ca.id as career_id',
                'ca.name as career_name',
                'jo.seniority',
                DB::raw('COUNT(DISTINCT jo.id) as jobs'),
                DB::raw('ROUND((COUNT(DISTINCT jo.id) / t.total_jobs) * 100, 2) as percentage')
            )
            ->groupBy('ca.id','ca.name','jo.seniority','t.total_jobs')
            ->orderBy('ca.name')
            ->get();

        $data = $rows
            ->groupBy('career_id')
            ->map(fn ($items) => [
                'career_id'   => $items->first()->career_id,
                'career_name' => $items->first()->career_name,
                'distribution'=> $items->map(fn ($r) => [
                    'seniority'  => $r->seniority,
                    'jobs'       => (int) $r->jobs,
                    'percentage' => (float) $r->percentage,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'status' => 'ok',
            'data'   => $data,
            'meta'   => [
                'source' => 'job_offers → tech_positions → careers',
                'calculation' => '% nivel = vacantes_nivel / total_vacantes_carrera',
            ],
        ]);
    }

    /* =====================================================
       3️⃣ Distribución de modalidad laboral
    ===================================================== */
    public function modalityDistribution(Request $request)
    {
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');

        $careers = $request->filled('career')
            ? array_values(array_filter((array) $request->career))
            : [];

        $range = $this->getPeriodRange($period, $year);

        $query = DB::table('job_offers as jo')
            ->whereBetween(
                DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
                [$range['start'], $range['end']]
            )
            ->whereIn('jo.seniority', ['junior','mid','senior'])
            ->whereNotNull('jo.modality');

        if (!empty($careers)) {
            $query
                ->join('tech_position_job_offer as tpjo', 'tpjo.job_offer_id', '=', 'jo.id')
                ->join('career_tech_position as ctp', 'ctp.tech_position_id', '=', 'tpjo.tech_position_id')
                ->join('careers as ca', 'ca.id', '=', 'ctp.career_id')
                ->whereIn('ca.slug', $careers);
        }

        $row = $query->selectRaw("
            SUM(CASE WHEN jo.modality IN ('remote','fully_remote') THEN 1 ELSE 0 END) AS remoto,
            SUM(CASE WHEN jo.modality IN ('hybrid','remote_local') THEN 1 ELSE 0 END) AS hibrido,
            SUM(CASE WHEN jo.modality = 'no_remote' THEN 1 ELSE 0 END) AS presencial
        ")->first();

        $total = ($row->remoto + $row->hibrido + $row->presencial);

        return response()->json([
            'status' => 'ok',
            'data' => $total === 0 ? [
                'remote' => 0, 'hybrid' => 0, 'onsite' => 0,
            ] : [
                'remote' => round($row->remoto / $total * 100, 2),
                'hybrid' => round($row->hibrido / $total * 100, 2),
                'onsite' => round($row->presencial / $total * 100, 2),
            ],
            'meta' => [
                'total_jobs' => $total,
                'source' => 'job_offers → tech_positions → careers',
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
}
