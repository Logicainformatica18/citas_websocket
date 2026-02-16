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

        $availableCareers = $this->getAvailableCareers();
       $meta = $this->getGlobalMeta(
    (int) $careerId,
    (int) $year,
    (int) $quarter,
    $range,
    (string) $period
);


        if (!$careerId) {
            return $this->renderEmpty(
                $availableCareers,
                $meta,
                $year,
                $period
            );
        }

        $courses = $this->getCoursesCCTC(
            $careerId,
            $year,
            $quarter
        );

        return Inertia::render(
            'DashboardCourseAlignment/CourseAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => $careerId,
                    'year' => $year,
                    'period' => $period,
                ],
                'availableCareers' => $availableCareers,
                'courses' => $courses,
                'meta' => $meta,
            ]
        );
    }

    /* =====================================================
       CORE CCTC
    ===================================================== */
 private function getCoursesCCTC(
    int $careerId,
    int $year,
    int $quarter
)
{
    $rows = DB::table('career_course as cc')
        ->join('courses as c', 'c.id', '=', 'cc.course_id')

        ->leftJoin('competency_course as compc',
            'compc.course_id', '=', 'c.id')

        ->leftJoin('competency_market_entity as cme',
            'cme.competency_id', '=', 'compc.competency_id')

        ->leftJoin('market_entities as me', function ($join) {
            $join->on('me.id', '=', 'cme.market_entity_id')
                 ->whereIn('me.entity_type',
                    ['technology','language','methodology']);
        })

        ->leftJoin('entity_trends as et', function ($join) use ($year, $quarter) {
            $join->on('et.market_entity_id', '=', 'me.id')
                 ->where('et.year', $year)
                 ->where('et.quarter', $quarter);
        })

        ->where('cc.career_id', $careerId)

        ->select(
            'c.id as course_id',
            'c.name as course_name',
            'me.id as entity_id',
            'me.name as entity_name',
            'me.entity_type',
            'et.id as trend_id'
        )
        ->get();

    $grouped = $rows->groupBy('course_id');

    return $grouped->map(function ($items) {

        $alignedEntities = $items
            ->whereNotNull('trend_id')
            ->unique('entity_id')
            ->values()
            ->map(function ($e) {
                return [
                    'id' => $e->entity_id,
                    'name' => $e->entity_name,
                    'type' => $e->entity_type,
                ];
            });

        $cctc = $alignedEntities->count();

        $level = match (true) {
            $cctc == 0 => 'not_aligned',
            $cctc == 1 => 'aligned',
            $cctc == 2 => 'highly_aligned',
            $cctc >= 3 => 'strategically_aligned',
        };

        return [
            'id' => $items->first()->course_id,
            'name' => $items->first()->course_name,
            'cctc' => $cctc,
            'level' => $level,
            'aligned_entities' => $alignedEntities,
        ];
    })
    ->sortByDesc('cctc')
    ->values();
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
       1️⃣ ENTIDADES VÍA COMPETENCIAS
    ===================================================== */
    $entitiesFromCompetencies = DB::table('career_course as cc')
        ->join('competency_course as compc', 'compc.course_id', '=', 'cc.course_id')
        ->join('competency_market_entity as cme', 'cme.competency_id', '=', 'compc.competency_id')
        ->join('market_entities as me', 'me.id', '=', 'cme.market_entity_id')
        ->where('cc.career_id', $careerId)
        ->whereIn('me.entity_type', ['technology','language','methodology'])
        ->distinct()
        ->pluck('me.id');


    /* =====================================================
       2️⃣ ENTIDADES DIRECTAS DEL CURSO
       (si tienes estas tablas)
    ===================================================== */

    $entitiesFromDirect = collect();

    // Tecnologías
    if (DB::getSchemaBuilder()->hasTable('course_technology')) {
        $tech = DB::table('career_course as cc')
            ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
            ->where('cc.career_id', $careerId)
            ->pluck('ct.technology_id');

        $entitiesFromDirect = $entitiesFromDirect->merge($tech);
    }

    // Lenguajes
    if (DB::getSchemaBuilder()->hasTable('course_language')) {
        $lang = DB::table('career_course as cc')
            ->join('course_language as cl', 'cl.course_id', '=', 'cc.course_id')
            ->where('cc.career_id', $careerId)
            ->pluck('cl.language_id');

        $entitiesFromDirect = $entitiesFromDirect->merge($lang);
    }

    // Metodologías (si existe)
    if (DB::getSchemaBuilder()->hasTable('course_methodology')) {
        $meth = DB::table('career_course as cc')
            ->join('course_methodology as cm', 'cm.course_id', '=', 'cc.course_id')
            ->where('cc.career_id', $careerId)
            ->pluck('cm.methodology_id');

        $entitiesFromDirect = $entitiesFromDirect->merge($meth);
    }

    /* =====================================================
       3️⃣ UNIÓN FINAL DE ENTIDADES
    ===================================================== */

    $entityIds = $entitiesFromCompetencies
        ->merge($entitiesFromDirect)
        ->unique()
        ->values();


    /* =====================================================
       4️⃣ CONTAR REPORTES (SOLO ESAS ENTIDADES)
    ===================================================== */

    $reportes = DB::table('entity_trends')
        ->whereIn('market_entity_id', $entityIds)
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->count();


    /* =====================================================
       5️⃣ CONTAR VACANTES (SOLO ESAS ENTIDADES)
       Evitamos duplicados por job_offer_id
    ===================================================== */

    $vacantes = DB::table('job_offers as jo')
        ->leftJoin('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
        ->leftJoin('language_job as lj', 'lj.job_offer_id', '=', 'jo.id')
        ->leftJoin('certification_job as cj', 'cj.job_offer_id', '=', 'jo.id')
        ->whereBetween('jo.published_at', $range)
        ->where(function ($q) use ($entityIds) {
            $q->whereIn('tj.market_entity_id', $entityIds)
              ->orWhereIn('lj.market_entity_id', $entityIds)
              ->orWhereIn('cj.market_entity_id', $entityIds);
        })
        ->distinct('jo.id')
        ->count('jo.id');


    /* =====================================================
       6️⃣ RETORNO META
    ===================================================== */

    return [
        'year' => $year,
        'period' => $period,
        'periodo_label' => $period === 's1'
            ? "Semestre 1 – Enero a Junio {$year}"
            : "Semestre 2 – Julio a Diciembre {$year}",

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
