<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SeniorityIndicatorController extends Controller
{
    /* =====================================================
       0️⃣ Vista principal (Inertia)
    ===================================================== */
public function index(Request $request)
{
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');

    // 🔑 FILTRO GLOBAL
    $careers = $request->filled('career')
        ? array_filter((array) $request->career)
        : [];

    $range = $this->getPeriodRange($period, $year);

    Log::info('🟦 [SeniorityIndex] Params', [
        'year'    => $year,
        'period'  => $period,
        'range'   => $range,
        'careers' => $careers,
    ]);

    /* =================================================
       Vacantes analizadas (MISMA LÓGICA QUE LOS GRÁFICOS)
    ================================================= */
    $vacantesQuery = DB::table('job_offers as jo')
        ->join('competency_job_offer as cjo', 'cjo.job_offer_id', '=', 'jo.id')
        ->join('competencies as comp', 'comp.id', '=', 'cjo.competency_id')
        ->join('careers as ca', 'ca.id', '=', 'comp.career_id')
        ->whereBetween(
            DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
            [$range['start'], $range['end']]
        )
        ->whereIn('jo.seniority', ['junior', 'mid', 'senior']);

    if (!empty($careers)) {
        $vacantesQuery->whereIn('ca.slug', $careers);
    }

    $vacantesAnalizadas = $vacantesQuery
        ->distinct('jo.id')
        ->count('jo.id');

    /* =================================================
       Carreras disponibles (para filtro)
    ================================================= */
    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    return Inertia::render('DashboardSeniority/Index', [
        'filters' => [
            'year'   => $year,
            'period' => $period,
            'career' => $careers, // 👈 CLAVE
        ],
        'availableCareers' => $availableCareers,
        'meta' => [
            'year'   => $year,
            'period' => $period,
            'periodo_label' => $period === 's1'
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

        Log::info('🔄 Recalculando seniority', [
            'only_unspecified' => $onlyUnspecified,
        ]);

        $sql = "
            UPDATE job_offers
            SET seniority =
              CASE
                WHEN experience_level IS NULL THEN 'unspecified'

                WHEN LOWER(experience_level) IN
                  ('1','2','entry level','internship','junior','jr')
                THEN 'junior'

                WHEN LOWER(experience_level) IN
                  ('3','4','mid','associate','mid-senior level','semi senior')
                THEN 'mid'

                WHEN LOWER(experience_level) IN
                  ('5','6','7','8','senior','sr','lead','principal','director','executive')
                THEN 'senior'

                ELSE 'unspecified'
              END
        ";

        if ($onlyUnspecified) {
            $sql .= " WHERE seniority IS NULL OR seniority = 'unspecified'";
        }

        $affected = DB::affectingStatement($sql);

        return response()->json([
            'status'       => 'ok',
            'rows_updated' => $affected,
        ]);
    }
    /* =====================================================
       2️⃣ Distribución de seniority por carrera
    ===================================================== */
  /* =====================================================
   2️⃣ Distribución de seniority por carrera (REAL)
   Basado en COMPETENCIAS – SIN SCORE
===================================================== */
public function distributionByCareer(Request $request)
{
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');

    $careers = $request->filled('career')
        ? array_filter((array) $request->career)
        : [];

    $range = $this->getPeriodRange($period, $year);

    Log::info('📊 [SeniorityDistribution] Params', [
        'year'    => $year,
        'period'  => $period,
        'range'   => $range,
        'careers' => $careers,
    ]);

    /* =================================================
       1️⃣ Totales por carrera (denominador)
    ================================================= */
    $totals = DB::table('job_offers as jo')
        ->join('competency_job_offer as cjo', 'cjo.job_offer_id', '=', 'jo.id')
        ->join('competencies as comp', 'comp.id', '=', 'cjo.competency_id')
        ->join('careers as ca', 'ca.id', '=', 'comp.career_id')
        ->whereBetween(
            DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
            [$range['start'], $range['end']]
        )
        ->whereIn('jo.seniority', ['junior', 'mid', 'senior'])
        ->when(!empty($careers), fn ($q) =>
            $q->whereIn('ca.slug', $careers)
        )
        ->select(
            'ca.id as career_id',
            DB::raw('COUNT(DISTINCT jo.id) as total_jobs')
        )
        ->groupBy('ca.id');

    /* =================================================
       2️⃣ Distribución por seniority
    ================================================= */
    $rows = DB::table('job_offers as jo')
        ->join('competency_job_offer as cjo', 'cjo.job_offer_id', '=', 'jo.id')
        ->join('competencies as comp', 'comp.id', '=', 'cjo.competency_id')
        ->join('careers as ca', 'ca.id', '=', 'comp.career_id')
        ->joinSub($totals, 't', fn ($j) =>
            $j->on('t.career_id', '=', 'ca.id')
        )
        ->whereBetween(
            DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
            [$range['start'], $range['end']]
        )
        ->whereIn('jo.seniority', ['junior', 'mid', 'senior'])
        ->when(!empty($careers), fn ($q) =>
            $q->whereIn('ca.slug', $careers)
        )
        ->select(
            'ca.id as career_id',
            'ca.name as career_name',
            'jo.seniority',
            DB::raw('COUNT(DISTINCT jo.id) as jobs'),
            DB::raw('ROUND((COUNT(DISTINCT jo.id) / t.total_jobs) * 100, 2) as percentage')
        )
        ->groupBy(
            'ca.id',
            'ca.name',
            'jo.seniority',
            't.total_jobs'
        )
        ->orderBy('ca.name')
        ->get();

    /* =================================================
       3️⃣ Formato para frontend
    ================================================= */
    $data = $rows
        ->groupBy('career_id')
        ->map(function ($items) {
            return [
                'career_id'   => $items->first()->career_id,
                'career_name' => $items->first()->career_name,
                'distribution'=> $items->map(fn ($r) => [
                    'seniority'  => $r->seniority,
                    'jobs'       => (int) $r->jobs,
                    'percentage' => (float) $r->percentage,
                ])->values(),
            ];
        })
        ->values();

    Log::info('✅ [SeniorityDistribution] Result rows', [
        'careers' => $data->count(),
    ]);

    return response()->json([
        'status' => 'ok',
        'data'   => $data,
        'meta'   => [
            'year'   => $year,
            'period'=> $period,
            'calculation'=> '% nivel = (vacantes_nivel / total_vacantes_carrera) × 100',
            'source'=> 'job_offers → competencies → careers',
        ],
    ]);
}

/* =====================================================
   3️⃣ Distribución de modalidad por seniority (REAL)
===================================================== */
/* =====================================================
   3️⃣ Distribución de modalidad laboral (NORMALIZADA)
   remoto | híbrido | presencial
===================================================== */
public function modalityDistribution(Request $request)
{
    $year   = (int) $request->get('year', 2025);
    $period = $request->get('period', 's2');

    $careers = $request->filled('career')
        ? array_filter((array) $request->career)
        : [];

    $range = $this->getPeriodRange($period, $year);

    Log::info('📊 [SeniorityModality] Params', [
        'year'    => $year,
        'period'  => $period,
        'range'   => $range,
        'careers' => $careers,
    ]);

    $query = DB::table('job_offers as jo')
        ->join('competency_job_offer as cjo', 'cjo.job_offer_id', '=', 'jo.id')
        ->join('competencies as comp', 'comp.id', '=', 'cjo.competency_id')
        ->join('careers as ca', 'ca.id', '=', 'comp.career_id')
        ->whereBetween(
            DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
            [$range['start'], $range['end']]
        )
        ->whereIn('jo.seniority', ['junior', 'mid', 'senior'])
        ->whereNotNull('jo.modality');

    // 🔑 MISMO FILTRO QUE LOS OTROS GRÁFICOS
    if (!empty($careers)) {
        $query->whereIn('ca.slug', $careers);
    }

    $row = $query->selectRaw("
        SUM(
            CASE
                WHEN jo.modality IN ('remote','fully_remote') THEN 1
                ELSE 0
            END
        ) AS remoto,

        SUM(
            CASE
                WHEN jo.modality IN ('hybrid','remote_local') THEN 1
                ELSE 0
            END
        ) AS hibrido,

        SUM(
            CASE
                WHEN jo.modality = 'no_remote' THEN 1
                ELSE 0
            END
        ) AS presencial
    ")->first();

    $total = ($row->remoto + $row->hibrido + $row->presencial);

    if ($total === 0) {
        return response()->json([
            'status' => 'ok',
            'data' => [
                'remote'  => 0,
                'hybrid'  => 0,
                'onsite'  => 0,
            ],
            'meta' => [
                'note' => 'No existen vacantes con modalidad para los filtros seleccionados',
            ],
        ]);
    }

    return response()->json([
        'status' => 'ok',
        'data' => [
            'remote'  => round(($row->remoto / $total) * 100, 2),
            'hybrid'  => round(($row->hibrido / $total) * 100, 2),
            'onsite'  => round(($row->presencial / $total) * 100, 2),
        ],
        'meta' => [
            'year'        => $year,
            'period'      => $period,
            'total_jobs'  => $total,
            'calculation' => '% modalidad = (vacantes_modalidad / total_vacantes) × 100',
            'source'      => 'job_offers → competencies → careers',
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
