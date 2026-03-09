<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\JobMarketStatusBuilder;

class SeniorityIndicatorController extends Controller
{
    /* =====================================================
       0️⃣ Vista principal (Inertia)
    ===================================================== */
    public function index(Request $request)
    {
        $year   = (int) $request->get('year', 2026);
        $period = $request->get('period', 's1');

        $careers = $request->filled('career')
            ? array_values(array_filter((array) $request->career))
            : [];

        $range = $this->getPeriodRange($period, $year);

 $base = $this->baseCareerQuery($range, $careers);
$vacantesAnalizadas = $base->count();

        $availableCareers = DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id','name','slug']);

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
            ->whereBetween(
                DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
                [$range['start'], $range['end']]
            )
            ->whereIn('jo.seniority', ['junior','mid','senior'])
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
        ->whereBetween(
            DB::raw('DATE(COALESCE(jo.published_at, jo.created_at))'),
            [$range['start'], $range['end']]
        )
        ->whereIn('jo.seniority', ['junior','mid','senior'])
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
}
