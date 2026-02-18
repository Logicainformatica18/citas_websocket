<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Artisan;
use App\Services\CCTCService;
class CourseCCTCIndicatorController extends Controller
{

protected CCTCService $cctc;

public function __construct(CCTCService $cctc)
{
    $this->cctc = $cctc;
}
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

    $mode = $request->get('view', 'courses');

    $availableCareers = $this->getAvailableCareers();

    if (!$careerId) {
        return $this->renderEmpty(
            $availableCareers,
            [],
            $year,
            $period
        );
    }

    $meta = $this->getGlobalMeta(
        $careerId,
        $year,
        $quarter,
        $range,
        $period
    );

    $data = $mode === 'competencies'
    ? $this->cctc->getCompetencies($careerId, $year)
    : $this->cctc->getCourses($careerId, $year);

$totalCourses = collect($data)->count();

/* ===============================
   1️⃣ Cursos con señal de mercado
=============================== */

$marketAligned = collect($data)
    ->whereIn('estado', [
        'Estrategicamente alineado',
        'Altamente alineado',
        'Alineado'
    ])
    ->count();

/* ===============================
   2️⃣ Cursos con tendencia
   (usamos entity_trends por curso)
=============================== */

$trendAligned = collect($data)
    ->filter(function ($course) use ($careerId) {

        $entityIds = DB::table('course_language as cl')
            ->join('languages as l', 'l.id', '=', 'cl.language_id')
            ->where('cl.course_id', $course['id'])
            ->pluck('l.market_entity_id')
            ->merge(
                DB::table('course_technology as ct')
                    ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                    ->where('ct.course_id', $course['id'])
                    ->pluck('t.market_entity_id')
            )
            ->merge(
                DB::table('course_methodology as cm')
                    ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                    ->where('cm.course_id', $course['id'])
                    ->pluck('m.market_entity_id')
            )
            ->unique();

        return DB::table('entity_trends')
            ->whereIn('market_entity_id', $entityIds)
            ->exists();
    })
    ->count();

/* ===============================
   3️⃣ Cálculos porcentuales
=============================== */

$marketRate = $totalCourses > 0
    ? ($marketAligned / $totalCourses) * 100
    : 0;

$trendRate = $totalCourses > 0
    ? ($trendAligned / $totalCourses) * 100
    : 0;

/* ===============================
   4️⃣ KPI FINAL (sin pesos)
=============================== */

$finalIndex = $totalCourses > 0
    ? (($marketRate + $trendRate) / 2)
    : 0;

/* ===============================
   5️⃣ GAP (sin ninguna señal)
=============================== */

$gapTotal = $totalCourses - $marketAligned;


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
            'meta' => $meta,
            'final_index' => round($finalIndex, 1),
'market_rate' => round($marketRate, 1),
'trend_rate'  => round($trendRate, 1),
'gap_total'   => $gapTotal,
'aligned_count' => $marketAligned,
'total_courses' => $totalCourses,

        ]
    );
}
private function getCompetencyAlignment(int $careerId)
{
    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Obtener cursos con su estado estratégico real
    |--------------------------------------------------------------------------
    */

    $courses = $this->getCoursesCCTC($careerId);

    if ($courses->isEmpty()) {
        return collect();
    }

    $courseStates = $courses->keyBy('id');

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Obtener competencias de la carrera
    |--------------------------------------------------------------------------
    */

    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get();

    if ($competencies->isEmpty()) {
        return collect();
    }

    /*
    |--------------------------------------------------------------------------
    | 3️⃣ Obtener relación competencia → cursos
    |--------------------------------------------------------------------------
    */

    $competencyCourses = DB::table('competency_course as cc')
        ->join('courses as c', 'c.id', '=', 'cc.course_id')
        ->whereIn('cc.competency_id', $competencies->pluck('id'))
        ->select('cc.competency_id', 'c.id as course_id', 'c.name')
        ->get()
        ->groupBy('competency_id');

    /*
    |--------------------------------------------------------------------------
    | 4️⃣ Evaluar estado estratégico por competencia
    |--------------------------------------------------------------------------
    */

    return $competencies->map(function ($comp) use ($competencyCourses, $courseStates) {

        $relatedCourses = $competencyCourses[$comp->id] ?? collect();

        if ($relatedCourses->isEmpty()) {
            return [
                'id' => $comp->id,
                'name' => $comp->name,
                'estado' => 'En riesgo',
                'cursos' => [],
            ];
        }

        $total = $relatedCourses->count();
        $estrategicos = 0;
        $parciales = 0;
        $noAlineados = 0;

        $cursosFormateados = [];

        foreach ($relatedCourses as $course) {

            $estadoCurso = $courseStates[$course->course_id]['estado'] ?? 'No alineado';

            // Contadores para estado de la competencia
            if ($estadoCurso === 'Estrategicamente alineado') {
                $estrategicos++;
            } elseif (
                $estadoCurso === 'Altamente alineado' ||
                $estadoCurso === 'Alineado'
            ) {
                $parciales++;
            } else {
                $noAlineados++;
            }

            // 🔥 IMPORTANTE: enviamos estado del curso al frontend
            $cursosFormateados[] = [
                'id' => $course->course_id,
                'name' => $course->name,
                'estado' => $estadoCurso,
            ];
        }
$cursosFormateados = collect($cursosFormateados)
    ->sortByDesc(function ($c) {
        return match($c['estado']) {
            'Estrategicamente alineado' => 3,
            'Altamente alineado' => 2,
            'Alineado' => 2,
            default => 1,
        };
    })
    ->values();

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Regla estratégica tipo Lovable
        |--------------------------------------------------------------------------
        */

        if ($estrategicos === $total) {
            $estadoFinal = 'Estrategicamente alineado';
        }
        elseif (($estrategicos + $parciales) >= ceil($total / 2)) {
            $estadoFinal = 'Parcialmente alineado';
        }
        else {
            $estadoFinal = 'En riesgo';
        }

        return [
            'id' => $comp->id,
            'name' => $comp->name,
            'estado' => $estadoFinal,
            'cursos' => $cursosFormateados, // 👈 ahora sí correcto
        ];
    });
}


public function getRecentJobsByCourse(int $courseId)
{
    /* =========================
       1️⃣ Lenguajes
    ========================= */

    $languages = DB::table('course_language as cl')
        ->join('languages as l', 'l.id', '=', 'cl.language_id')
        ->where('cl.course_id', $courseId)
        ->pluck('l.name');

    /* =========================
       2️⃣ Tecnologías
    ========================= */

    $technologies = DB::table('course_technology as ct')
        ->join('technologies as t', 't.id', '=', 'ct.technology_id')
        ->where('ct.course_id', $courseId)
        ->pluck('t.name');

    /* =========================
       3️⃣ Metodologías
    ========================= */

    $methodologies = DB::table('course_methodology as cm')
        ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
        ->where('cm.course_id', $courseId)
        ->pluck('m.name');

    /* =========================
       4️⃣ Entidades para jobs
    ========================= */

    $entityIds = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->where('cl.course_id', $courseId)
                ->pluck('l.market_entity_id')
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->where('ct.course_id', $courseId)
                ->pluck('t.market_entity_id')
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->where('cm.course_id', $courseId)
                ->pluck('m.market_entity_id')
        )
        ->filter()
        ->unique()
        ->values();

    $recentJobs = [];

    if ($entityIds->isNotEmpty()) {

        $jobIds = collect()
            ->merge(
               DB::table('technology_job as tj')
    ->join('technologies as t', 't.id', '=', 'tj.technology_id')
    ->whereIn('t.market_entity_id', $entityIds)
    ->pluck('tj.job_offer_id')

            )
            ->merge(
               DB::table('language_job as lj')
    ->join('languages as l', 'l.id', '=', 'lj.language_id')
    ->whereIn('l.market_entity_id', $entityIds)
    ->pluck('lj.job_offer_id')

            )
            ->merge(
                DB::table('methodology_job as mj')
    ->join('methodologies as m', 'm.id', '=', 'mj.methodology_id')
    ->whereIn('m.market_entity_id', $entityIds)
    ->pluck('mj.job_offer_id')

            )
            ->unique()
            ->values();

        if ($jobIds->isNotEmpty()) {
            $recentJobs = DB::table('job_offers')
                ->whereIn('id', $jobIds)
                ->orderByDesc('published_at')
                ->limit(10)
                ->get([
                    'id',
                    'title',
                    'company',
                    'city',
                    'published_at'
                ]);
        }
    }

    return response()->json([
        'connections' => [
            'languages' => $languages,
            'technologies' => $technologies,
            'methodologies' => $methodologies,
        ],
        'recent_jobs' => $recentJobs
    ]);
}
public function getCourseTrends(int $courseId)
{
    // 1️⃣ Obtener entidades del curso
    $entityIds = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->where('cl.course_id', $courseId)
                ->pluck('l.market_entity_id')
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->where('ct.course_id', $courseId)
                ->pluck('t.market_entity_id')
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->where('cm.course_id', $courseId)
                ->pluck('m.market_entity_id')
        )
        ->filter()
        ->unique()
        ->values();

    if ($entityIds->isEmpty()) {
        return response()->json([]);
    }

    // 2️⃣ Traer tendencias reales usando tus columnas correctas
    $trends = DB::table('entity_trends as et')
        ->join('market_entities as me', 'me.id', '=', 'et.market_entity_id')
        ->whereIn('et.market_entity_id', $entityIds)
        ->orderByDesc('et.created_at')
        ->limit(10)
        ->get([
            'et.id',
            'et.trend_name',       // ✅ correcto
            'et.source_title',     // ✅ correcto
            'et.source_url',
            'et.source_type',
            'et.year',
            'et.quarter',
            'me.name as entity_name'
        ]);

    return response()->json($trends);
}

public function getCourseGaps(int $courseId)
{
    // 1️⃣ Entidades del curso
    $entities = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->where('cl.course_id', $courseId)
                ->select('l.market_entity_id', 'l.name')
                ->get()
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->where('ct.course_id', $courseId)
                ->select('t.market_entity_id', 't.name')
                ->get()
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->where('cm.course_id', $courseId)
                ->select('m.market_entity_id', 'm.name')
                ->get()
        )
        ->unique('market_entity_id')
        ->values();

    if ($entities->isEmpty()) {
        return response()->json([
            'total' => 0,
            'conectadas' => 0,
            'brechas' => []
        ]);
    }

    $entityIds = $entities->pluck('market_entity_id');

    // 2️⃣ Entidades con señal de empleo
  $entitiesWithJobs = collect()
    ->merge(
        DB::table('technology_job as tj')
            ->join('technologies as t', 't.id', '=', 'tj.technology_id')
            ->pluck('t.market_entity_id')
    )
    ->merge(
        DB::table('language_job as lj')
            ->join('languages as l', 'l.id', '=', 'lj.language_id')
            ->pluck('l.market_entity_id')
    )
    ->merge(
        DB::table('methodology_job as mj')
            ->join('methodologies as m', 'm.id', '=', 'mj.methodology_id')
            ->pluck('m.market_entity_id')
    )
    ->filter()
    ->map(fn($id) => (int) $id)
    ->unique()
    ->values()
    ->flip();


    // 3️⃣ Entidades con tendencia
    $entitiesWithTrends = DB::table('entity_trends')
        ->whereIn('market_entity_id', $entityIds)
        ->pluck('market_entity_id')
        ->unique();

    $conectadas = $entitiesWithJobs
        ->merge($entitiesWithTrends)
        ->unique();

    $brechas = $entities
        ->filter(fn($e) => !$conectadas->contains($e->market_entity_id))
        ->values();

    return response()->json([
        'total' => $entities->count(),
        'conectadas' => $conectadas->count(),
        'brechas' => $brechas
    ]);
}

public function getCourseAIRecommendation(int $courseId)
{
    $record = DB::table('course_ai_recommendations')
        ->where('course_id', $courseId)
        ->orderByDesc('created_at')
        ->first();

    if (!$record) {
        return response()->json([
            'diagnosis' => null,
            'suggested_entities' => [],
            'suggested_certifications' => [],
            'suggested_methodologies' => [],
            'market_evidence' => [],
        ]);
    }

    return response()->json([
        'diagnosis' => $record->diagnosis,
        'suggested_entities' => json_decode($record->suggested_entities ?? '[]', true),
        'suggested_certifications' => json_decode($record->suggested_certifications ?? '[]', true),
        'suggested_methodologies' => json_decode($record->suggested_methodologies ?? '[]', true),
        'market_evidence' => json_decode($record->market_evidence ?? '[]', true),
    ]);
}


public function getCourseRecommendation(int $courseId)
{
    $recommendation = DB::table('course_ai_recommendations')
        ->where('course_id', $courseId)
        ->orderByDesc('created_at')
        ->first();

    if (!$recommendation) {
        return response()->json(null);
    }

    return response()->json([
        'diagnosis' => $recommendation->diagnosis,
        'suggested_entities' => json_decode($recommendation->suggested_entities ?? '[]', true),
        'suggested_methodologies' => json_decode($recommendation->suggested_methodologies ?? '[]', true),
        'suggested_certifications' => json_decode($recommendation->suggested_certifications ?? '[]', true),
        'market_evidence' => json_decode($recommendation->market_evidence ?? 'null', true),
        'created_at' => $recommendation->created_at,
    ]);
}

    /* =====================================================
       CORE CCTC
    ===================================================== */
// private function getCoursesCCTC(int $careerId)
// {
//     /* =========================
//        1️⃣ Courses of the career
//     ========================= */

//     $courses = DB::table('career_course as cc')
//         ->join('courses as c', 'c.id', '=', 'cc.course_id')
//         ->where('cc.career_id', $careerId)
//         ->select('c.id', 'c.name')
//         ->orderBy('c.name')
//         ->get();

//     if ($courses->isEmpty()) {
//         return collect();
//     }

//     $courseIds = $courses->pluck('id');

//     /* =========================
//        2️⃣ Dimension signals per course
//        (Precomputed once, optimized)
//     ========================= */

//     // Languages with signal (job OR trend)
//     $languageSignal = DB::table('course_language as cl')
//         ->join('languages as l', 'l.id', '=', 'cl.language_id')
//         ->leftJoin('language_job as lj', 'lj.language_id', '=', 'l.id')
//         ->leftJoin('entity_trends as et', 'et.market_entity_id', '=', 'l.market_entity_id')
//         ->whereIn('cl.course_id', $courseIds)
//         ->where(function($q){
//             $q->whereNotNull('lj.job_offer_id')
//               ->orWhereNotNull('et.id');
//         })
//         ->distinct()
//         ->pluck('cl.course_id')
//         ->flip();

//     // Technologies with signal
//     $technologySignal = DB::table('course_technology as ct')
//         ->join('technologies as t', 't.id', '=', 'ct.technology_id')
//         ->leftJoin('technology_job as tj', 'tj.technology_id', '=', 't.id')
//         ->leftJoin('entity_trends as et', 'et.market_entity_id', '=', 't.market_entity_id')
//         ->whereIn('ct.course_id', $courseIds)
//         ->where(function($q){
//             $q->whereNotNull('tj.job_offer_id')
//               ->orWhereNotNull('et.id');
//         })
//         ->distinct()
//         ->pluck('ct.course_id')
//         ->flip();

//     // Methodologies with signal
//     $methodologySignal = DB::table('course_methodology as cm')
//         ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
//         ->leftJoin('methodology_job as mj', 'mj.methodology_id', '=', 'm.id')
//         ->leftJoin('entity_trends as et', 'et.market_entity_id', '=', 'm.market_entity_id')
//         ->whereIn('cm.course_id', $courseIds)
//         ->where(function($q){
//             $q->whereNotNull('mj.job_offer_id')
//               ->orWhereNotNull('et.id');
//         })
//         ->distinct()
//         ->pluck('cm.course_id')
//         ->flip();

//     /* =========================
//        3️⃣ Competencies count
//     ========================= */

//     $courseCompetencies = DB::table('competency_course as cc')
//         ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
//         ->where('comp.career_id', $careerId)
//         ->whereIn('cc.course_id', $courseIds)
//         ->select('cc.course_id', DB::raw('COUNT(*) as total'))
//         ->groupBy('cc.course_id')
//         ->pluck('total', 'course_id');

//     /* =========================
//        4️⃣ Final mapping
//     ========================= */

//     return $courses->map(function ($course) use (
//         $languageSignal,
//         $technologySignal,
//         $methodologySignal,
//         $courseCompetencies
//     ) {

//         $hasLanguage     = isset($languageSignal[$course->id]);
//         $hasTechnology   = isset($technologySignal[$course->id]);
//         $hasMethodology  = isset($methodologySignal[$course->id]);

//         $connectionCount =
//             ($hasLanguage ? 1 : 0) +
//             ($hasTechnology ? 1 : 0) +
//             ($hasMethodology ? 1 : 0);

//         $status = match ($connectionCount) {
//             0 => 'No alineado',
//             1 => 'Alineado',
//             2 => 'Altamente alineado',
//             3 => 'Estrategicamente alineado',
//         };

//         return [
//             'id' => $course->id,
//             'name' => $course->name,
//             'estado' => $status,
//             'empleo' => $hasLanguage || $hasTechnology || $hasMethodology
//                 ? 'Demanda activa'
//                 : 'Sin demanda',
//             'tendencias' => $hasLanguage || $hasTechnology || $hasMethodology
//                 ? 'Detectado'
//                 : 'No detectado',
//             'gap_label' => null,
//             'gap_count' => null,
//             'competencias' => $courseCompetencies[$course->id] ?? 0,
//         ];
//     });
// }





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
public function analyzeWithAI(int $courseId)
{
    Artisan::call('curriculum:analyze-course', [
        'course_id' => $courseId
    ]);

    return response()->json([
        'status' => 'ok'
    ]);
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
    ->where('year', $year)
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
/* =====================================================
   3️⃣ TOTAL VACANTES (histórico acumulado por carrera)
===================================================== */

/* =====================================================
   3️⃣ TOTAL VACANTES (solo año/periodo actual)
===================================================== */

$jobIds = collect()
    ->merge(
       DB::table('technology_job as tj')
    ->join('technologies as t', 't.id', '=', 'tj.technology_id')
    ->whereIn('t.market_entity_id', $entityIds)
    ->pluck('tj.job_offer_id')

    )
    ->merge(
       DB::table('language_job as lj')
    ->join('languages as l', 'l.id', '=', 'lj.language_id')
    ->whereIn('l.market_entity_id', $entityIds)
    ->pluck('lj.job_offer_id')

    )
    ->merge(
       DB::table('methodology_job as mj')
    ->join('methodologies as m', 'm.id', '=', 'mj.methodology_id')
    ->whereIn('m.market_entity_id', $entityIds)
    ->pluck('mj.job_offer_id')

    )
    ->unique()
    ->values();

if ($jobIds->isEmpty()) {
    $vacantes = 0;
} else {
    $vacantes = DB::table('job_offers')
        ->whereIn('id', $jobIds)
        ->whereBetween('published_at', $range) // 🔥 aquí aplica año/periodo
        ->count();
}



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
            'viewMode' => request()->get('view', 'courses'), // 🔥 clave
            'filters' => [
                'career_id' => null,
                'year' => $year,
                'period' => $period,
            ],
            'availableCareers' => $careers,
            'data' => [],
            'meta' => $meta,
        ]
    );
}

}
