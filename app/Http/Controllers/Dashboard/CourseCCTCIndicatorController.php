<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CourseCCTCIndicatorController extends Controller
{
    /* =====================================================
       INDEX
    ===================================================== */
    public function index(Request $request)
{
    [
        $careerId,
        $year,
        $period,
        $quarter,
        $range
    ] = $this->resolveParams($request);

    $mode = $request->get('view', 'courses'); // 🔥 clave

    $availableCareers = $this->getAvailableCareers();

    if (!$careerId) {
        return $this->renderEmpty(
            $availableCareers,
            [],
            $year,
            $period
        );
    }

    if ($mode === 'competencies') {
        $data = $this->getCompetencyAlignment(
            $careerId,
            $year,
            $quarter
        );
    } else {
        $data = $this->getCoursesCCTC(
            $careerId,
            $year,
            $quarter
        );
    }

    return Inertia::render(
        'DashboardCourseAlignment/CourseAlignmentIndicatorPage',
        [
            'viewMode' => $mode,
            'filters' => [
                'career_id' => $careerId,
                'year'      => $year,
                'period'    => $period,
            ],
            'availableCareers' => $availableCareers,
            'data' => $data,
        ]
    );
}
private function getCompetencyAlignment(
    int $careerId,
    int $year,
    int $quarter
) {

    $competencies = DB::table('competencies as comp')
        ->where('comp.career_id', $careerId)
        ->select('comp.id', 'comp.name')
        ->orderBy('comp.name')
        ->get();

    if ($competencies->isEmpty()) {
        return collect();
    }

    $competencyIds = $competencies->pluck('id');

    /* =========================
       Entidades por competencia
    ========================= */

    $entities = DB::table('competency_course as cc')
        ->join('courses as c', 'c.id', '=', 'cc.course_id')
        ->leftJoinSub(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->select('cl.course_id', 'l.market_entity_id')
                ->unionAll(
                    DB::table('course_technology as ct')
                        ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                        ->select('ct.course_id', 't.market_entity_id')
                )
                ->unionAll(
                    DB::table('course_methodology as cm')
                        ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                        ->select('cm.course_id', 'm.market_entity_id')
                ),
            'ce',
            'ce.course_id',
            '=',
            'c.id'
        )
        ->whereIn('cc.competency_id', $competencyIds)
        ->select('cc.competency_id', 'ce.market_entity_id')
        ->get()
        ->filter(fn ($row) => !is_null($row->market_entity_id));

    /* =========================
       Señales globales
    ========================= */

    $entitiesWithJobs = collect()
        ->merge(DB::table('technology_job')->pluck('market_entity_id'))
        ->merge(DB::table('language_job')->pluck('market_entity_id'))
        ->merge(DB::table('methodology_job')->pluck('market_entity_id'))
        ->filter()
        ->unique()
        ->flip();

    $entitiesWithTrends = DB::table('entity_trends')
        ->pluck('market_entity_id')
        ->filter()
        ->unique()
        ->flip();

    /* =========================
       Construcción final
    ========================= */

    return $competencies->map(function ($comp) use (
        $entities,
        $entitiesWithJobs,
        $entitiesWithTrends
    ) {

        $entityIds = $entities
            ->where('competency_id', $comp->id)
            ->pluck('market_entity_id')
            ->unique();

        $total = $entityIds->count();

        if ($total === 0) {
            return [
                'id'     => $comp->id,
                'name'   => $comp->name,
                'estado' => 'Sin señal',
                'cursos' => [],
            ];
        }

        $conJobs   = $entityIds->filter(fn ($id) => isset($entitiesWithJobs[$id]));
        $conTrend  = $entityIds->filter(fn ($id) => isset($entitiesWithTrends[$id]));
        $sinSenal  = $entityIds->reject(fn ($id) =>
            isset($entitiesWithJobs[$id]) ||
            isset($entitiesWithTrends[$id])
        );

        if ($conJobs->isNotEmpty() && $conTrend->isNotEmpty() && $sinSenal->isEmpty()) {
            $estado = 'Estrategicamente alineado';
        } elseif ($conJobs->isNotEmpty() || $conTrend->isNotEmpty()) {
            $estado = 'Parcialmente alineado';
        } else {
            $estado = 'En riesgo';
        }

        return [
            'id'     => $comp->id,
            'name'   => $comp->name,
            'estado' => $estado,
            'empleo' => $conJobs->isNotEmpty(),
            'tendencia' => $conTrend->isNotEmpty(),
            'gaps'   => $sinSenal->count(),
        ];
    });
}

    /* =====================================================
       CORE CCTC
    ===================================================== */
private function getCoursesCCTC(
    int $careerId,
    int $year,
    int $quarter
) {

    /* =========================
       1️⃣ Cursos
    ========================= */

    $courses = DB::table('career_course as cc')
        ->join('courses as c', 'c.id', '=', 'cc.course_id')
        ->where('cc.career_id', $careerId)
        ->select('c.id', 'c.name')
        ->orderBy('c.name')
        ->get();

    if ($courses->isEmpty()) {
        return collect();
    }

    $courseIds = $courses->pluck('id');

    /* =========================
       2️⃣ Todas las entidades de todos los cursos (UNA SOLA QUERY POR TIPO)
    ========================= */

    $entities = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->whereIn('cl.course_id', $courseIds)
                ->select('cl.course_id', 'l.market_entity_id')
                ->get()
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->whereIn('ct.course_id', $courseIds)
                ->select('ct.course_id', 't.market_entity_id')
                ->get()
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->whereIn('cm.course_id', $courseIds)
                ->select('cm.course_id', 'm.market_entity_id')
                ->get()
        )
        ->filter(fn($row) => !is_null($row->market_entity_id));

    /* =========================
       3️⃣ Conjuntos globales (UNA VEZ)
    ========================= */

$entitiesWithJobs = collect()
    ->merge(DB::table('technology_job')->pluck('market_entity_id'))
    ->merge(DB::table('language_job')->pluck('market_entity_id'))
    ->merge(DB::table('methodology_job')->pluck('market_entity_id'))
    ->filter(fn($id) => !is_null($id))
    ->map(fn($id) => (int) $id)
    ->unique()
    ->values()
    ->flip();


$entitiesWithTrends = DB::table('entity_trends')
    ->pluck('market_entity_id')
    ->filter(fn($id) => !is_null($id))
    ->map(fn($id) => (int) $id)
    ->unique()
    ->values()
    ->flip();


    /* =========================
       4️⃣ Competencias agrupadas
    ========================= */

    $courseCompetencies = DB::table('competency_course as cc')
        ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
        ->where('comp.career_id', $careerId)
        ->whereIn('cc.course_id', $courseIds)
        ->select('cc.course_id', DB::raw('COUNT(*) as total'))
        ->groupBy('cc.course_id')
        ->pluck('total', 'course_id');

    /* =========================
       5️⃣ Construcción final
    ========================= */

    return $courses->map(function ($course) use (
        $entities,
        $entitiesWithJobs,
        $entitiesWithTrends,
        $courseCompetencies
    ) {

        $entityIds = $entities
            ->where('course_id', $course->id)
            ->pluck('market_entity_id')
            ->unique();

        $totalEntidades = $entityIds->count();

        if ($totalEntidades === 0) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'estado' => 'Sin entidades',
                'empleo' => 'Sin demanda',
                'tendencias' => 'No detectado',
                'gaps' => '0 gaps',
                'competencias' => $courseCompetencies[$course->id] ?? 0,
            ];
        }

        $conDemanda = $entityIds->filter(fn($id) => isset($entitiesWithJobs[$id]));
        $conTendencia = $entityIds->filter(fn($id) => isset($entitiesWithTrends[$id]));

        $sinSenal = $entityIds->reject(fn($id) =>
            isset($entitiesWithJobs[$id]) ||
            isset($entitiesWithTrends[$id])
        )->count();

        $hasJobs = $conDemanda->isNotEmpty();
        $hasTrends = $conTendencia->isNotEmpty();

        if ($hasJobs && $hasTrends && $sinSenal === 0) {
            $estado = 'Estrategicamente alineado';
        } elseif ($hasJobs || $hasTrends) {
            $estado = 'Parcialmente alineado';
        } else {
            $estado = 'En riesgo';
        }

        return [
            'id' => $course->id,
            'name' => $course->name,
            'estado' => $estado,
            'empleo' => $hasJobs ? 'Demanda activa' : 'Sin demanda',
            'tendencias' => $hasTrends ? 'Detectado' : 'No detectado',
            'gaps' => $sinSenal === 0 ? 'Sin brechas' : $sinSenal . ' gaps',
            'competencias' => $courseCompetencies[$course->id] ?? 0,
        ];
    });
}




    /* =====================================================
       HELPERS (MISMA LÓGICA QUE TU OTRO INDICADOR)
    ===================================================== */
   private function resolveParams(Request $request): array
{
    $careerId = (int) $request->get('career_id');
    $year     = (int) $request->get('year', 2026);
    $period   = $request->get('period', 's1');

    $quarter  = $period === 's1' ? 1 : 4;

    $range = $period === 's1'
        ? ["{$year}-01-01", "{$year}-06-30"]
        : ["{$year}-07-01", "{$year}-12-31"];

    return [
        $careerId,
        $year,
        $period,
        $quarter,
        $range
    ];
}


    private function getAvailableCareers()
    {
        return DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

private function getGlobalMeta(
    int $careerId,
    int $year,
    int $quarter,
    array $range,
    string $period
): array {

    /* =====================================================
       1️⃣ ENTIDADES DIRECTAS DE LA CARRERA
    ===================================================== */

  $entityIds = collect()
    ->merge(
        DB::table('career_course as cc')
            ->join('courses as c', 'c.id', '=', 'cc.course_id')
            ->join('course_language as cl', 'cl.course_id', '=', 'c.id')
            ->join('languages as l', 'l.id', '=', 'cl.language_id')
            ->where('cc.career_id', $careerId)
            ->pluck('l.market_entity_id')
    )
    ->merge(
        DB::table('career_course as cc')
            ->join('courses as c', 'c.id', '=', 'cc.course_id')
            ->join('course_technology as ct', 'ct.course_id', '=', 'c.id')
            ->join('technologies as t', 't.id', '=', 'ct.technology_id')
            ->where('cc.career_id', $careerId)
            ->pluck('t.market_entity_id')
    )
    ->merge(
        DB::table('career_course as cc')
            ->join('courses as c', 'c.id', '=', 'cc.course_id')
            ->join('course_methodology as cm', 'cm.course_id', '=', 'c.id')
            ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
            ->where('cc.career_id', $careerId)
            ->pluck('m.market_entity_id')
    )
    ->filter()
    ->unique()
    ->values();


    /* =====================================================
       2️⃣ TOTAL REPORTES (histórico completo)
    ===================================================== */

    $reportes = DB::table('entity_trends')
        ->whereIn('market_entity_id', $entityIds)
        ->count();

    /* =====================================================
       3️⃣ TOTAL VACANTES (histórico completo)
    ===================================================== */

    // $vacantes = DB::table('job_offers as jo')
    //     ->leftJoin('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
    //     ->leftJoin('language_job as lj', 'lj.job_offer_id', '=', 'jo.id')
    //     ->leftJoin('methodology_job as mj', 'mj.job_offer_id', '=', 'jo.id')
    //     ->where(function ($q) use ($entityIds) {
    //         $q->whereIn('tj.market_entity_id', $entityIds)
    //           ->orWhereIn('lj.market_entity_id', $entityIds)
    //           ->orWhereIn('mj.market_entity_id', $entityIds);
    //     })
    //     ->distinct('jo.id')
    //     ->count('jo.id');
$vacantes = 0;

    return [
        'year' => $year,
        'period' => $period,
        'periodo_label' => "Histórico acumulado",
        'vacantes_analizadas' => $vacantes,
        'reportes_analizados' => $reportes,
        'actualizado' => now()->toDateTimeString(),
    ];
}



    private function renderEmpty($careers, $meta, $year, $period)
    {
        return Inertia::render(
            'DashboardCourseAlignment/CourseAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => null,
                    'year' => $year,
                    'period' => $period,
                ],
                'availableCareers' => $careers,
                'courses' => [],
                'meta' => $meta,
            ]
        );
    }
}
