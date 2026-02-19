<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CCTCService
{
    /* =====================================================
       COURSES CCTC
    ===================================================== */

// public function getCourses(
//     int $careerId,
//     int $year
// ): Collection {

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
//        MERCADO (solo año)
//     ========================= */

//     $marketCourses = collect()
//         ->merge(
//             DB::table('course_language as cl')
//                 ->join('languages as l', 'l.id', '=', 'cl.language_id')
//                 ->join('language_job as lj', 'lj.language_id', '=', 'l.id')
//                 ->join('job_offers as jo', 'jo.id', '=', 'lj.job_offer_id')
//                 ->whereIn('cl.course_id', $courseIds)
//                 ->whereYear('jo.published_at', $year)
//                 ->pluck('cl.course_id')
//         )
//         ->merge(
//             DB::table('course_technology as ct')
//                 ->join('technologies as t', 't.id', '=', 'ct.technology_id')
//                 ->join('technology_job as tj', 'tj.technology_id', '=', 't.id')
//                 ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
//                 ->whereIn('ct.course_id', $courseIds)
//                 ->whereYear('jo.published_at', $year)
//                 ->pluck('ct.course_id')
//         )
//         ->merge(
//             DB::table('course_methodology as cm')
//                 ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
//                 ->join('methodology_job as mj', 'mj.methodology_id', '=', 'm.id')
//                 ->join('job_offers as jo', 'jo.id', '=', 'mj.job_offer_id')
//                 ->whereIn('cm.course_id', $courseIds)
//                 ->whereYear('jo.published_at', $year)
//                 ->pluck('cm.course_id')
//         )
//         ->unique()
//         ->flip();

//     /* =========================
//        TENDENCIAS (solo año)
//     ========================= */

//     $trendCourses = collect()
//         ->merge(
//             DB::table('course_language as cl')
//                 ->join('languages as l', 'l.id', '=', 'cl.language_id')
//                 ->join('entity_trends as et', 'et.market_entity_id', '=', 'l.market_entity_id')
//                 ->whereIn('cl.course_id', $courseIds)
//                 ->where('et.year', $year)
//                 ->pluck('cl.course_id')
//         )
//         ->merge(
//             DB::table('course_technology as ct')
//                 ->join('technologies as t', 't.id', '=', 'ct.technology_id')
//                 ->join('entity_trends as et', 'et.market_entity_id', '=', 't.market_entity_id')
//                 ->whereIn('ct.course_id', $courseIds)
//                 ->where('et.year', $year)
//                 ->pluck('ct.course_id')
//         )
//         ->merge(
//             DB::table('course_methodology as cm')
//                 ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
//                 ->join('entity_trends as et', 'et.market_entity_id', '=', 'm.market_entity_id')
//                 ->whereIn('cm.course_id', $courseIds)
//                 ->where('et.year', $year)
//                 ->pluck('cm.course_id')
//         )
//         ->unique()
//         ->flip();

//     /* =========================
//        MAPEADO FINAL (COMPATIBLE)
//     ========================= */

//     return $courses->map(function ($course) use ($marketCourses, $trendCourses) {

//         $hasMarket = isset($marketCourses[$course->id]);
//         $hasTrend  = isset($trendCourses[$course->id]);

//         $connections =
//             ($hasMarket ? 1 : 0) +
//             ($hasTrend ? 1 : 0);

//         $status = match (true) {
//             $hasMarket && $hasTrend => 'Estrategicamente alineado',
//             $hasMarket || $hasTrend => 'Alineado',
//             default => 'No alineado',
//         };

//         return [
//             'id' => $course->id,
//             'name' => $course->name,
//             'estado' => $status,
//             'connections' => $connections,

//             // 🔥 para tu frontend
//             'market_match' => $hasMarket,
//             'trend_match'  => $hasTrend,
//             'empleo'       => $hasMarket ? 'Demanda activa' : 'Sin demanda',
//             'tendencias'   => $hasTrend ? 'Detectado' : 'No detectado',

//             'gap_label' => null,
//             'gap_count' => null,
//         ];
//     });
// }

public function getCourses(int $careerId, int $year): Collection
{
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
/* =====================================
   4️⃣ Competencias por curso
===================================== */



    /* =====================================
       1️⃣ DIMENSIONES (estructura)
    ===================================== */

    $languageCourses = DB::table('course_language')
        ->whereIn('course_id', $courseIds)
        ->pluck('course_id')
        ->unique()
        ->flip();

    $technologyCourses = DB::table('course_technology')
        ->whereIn('course_id', $courseIds)
        ->pluck('course_id')
        ->unique()
        ->flip();

    $methodologyCourses = DB::table('course_methodology')
        ->whereIn('course_id', $courseIds)
        ->pluck('course_id')
        ->unique()
        ->flip();

    /* =====================================
       2️⃣ MERCADO (empleo real año)
    ===================================== */

    $marketCourses = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('language_job as lj', 'lj.language_id', '=', 'cl.language_id')
                ->join('job_offers as jo', 'jo.id', '=', 'lj.job_offer_id')
                ->whereYear('jo.published_at', $year)
                ->pluck('cl.course_id')
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technology_job as tj', 'tj.technology_id', '=', 'ct.technology_id')
                ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
                ->whereYear('jo.published_at', $year)
                ->pluck('ct.course_id')
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodology_job as mj', 'mj.methodology_id', '=', 'cm.methodology_id')
                ->join('job_offers as jo', 'jo.id', '=', 'mj.job_offer_id')
                ->whereYear('jo.published_at', $year)
                ->pluck('cm.course_id')
        )
        ->unique()
        ->flip();

    /* =====================================
       3️⃣ TENDENCIAS
    ===================================== */

    $trendCourses = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'l.market_entity_id')
                ->where('et.year', $year)
                ->pluck('cl.course_id')
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 't.market_entity_id')
                ->where('et.year', $year)
                ->pluck('ct.course_id')
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'm.market_entity_id')
                ->where('et.year', $year)
                ->pluck('cm.course_id')
        )
        ->unique()
        ->flip();

    /* =====================================
       4️⃣ MAPEADO FINAL
    ===================================== */
$courseCompetencies = DB::table('competency_course as cc')
    ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
    ->where('comp.career_id', $careerId)
    ->whereIn('cc.course_id', $courseIds)
    ->select('cc.course_id', DB::raw('COUNT(*) as total'))
    ->groupBy('cc.course_id')
    ->pluck('total', 'course_id');
    return $courses->map(function ($course) use (
        $languageCourses,
        $technologyCourses,
        $methodologyCourses,
        $marketCourses,
        $trendCourses,
         $courseCompetencies
    ) {

        // 🔹 Dimensiones estructurales
        $hasLanguage    = isset($languageCourses[$course->id]);
        $hasTechnology  = isset($technologyCourses[$course->id]);
        $hasMethodology = isset($methodologyCourses[$course->id]);

        $connectionCount =
            ($hasLanguage ? 1 : 0) +
            ($hasTechnology ? 1 : 0) +
            ($hasMethodology ? 1 : 0);

        $status = match ($connectionCount) {
            0 => 'No alineado',
            1 => 'Alineado',
            2 => 'Altamente alineado',
            3 => 'Estrategicamente alineado',
        };

        // 🔹 Señales de mercado
        $hasMarket = isset($marketCourses[$course->id]);
        $hasTrend  = isset($trendCourses[$course->id]);

        return [
            'id' => $course->id,
            'name' => $course->name,

            // 🟦 Estructural
            'estado' => $status,
            'connections' => $connectionCount,
            'language_match' => $hasLanguage,
            'technology_match' => $hasTechnology,
            'methodology_match' => $hasMethodology,
     

            // 🟩 Mercado
            'market_match' => $hasMarket,
            'trend_match'  => $hasTrend,
            'empleo' => $hasMarket ? 'Demanda activa' : 'Sin demanda',
            'tendencias' => $hasTrend ? 'Detectado' : 'No detectado',
                   'competencias' => $courseCompetencies[$course->id] ?? 0,

        ];
    });
}


    /* =====================================================
       COMPETENCIES CCTC (HEREDA CURSOS)
    ===================================================== */

public function getCompetencies(int $careerId, int $year): Collection
{
    // 1️⃣ Traer cursos ya calculados (lógica única)
    $courses = $this->getCourses($careerId, $year);

    if ($courses->isEmpty()) {
        return collect();
    }

    // indexamos por id para lookup rápido
    $courseStates = $courses->keyBy('id');

    // 2️⃣ Competencias de la carrera
    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get();

    if ($competencies->isEmpty()) {
        return collect();
    }

    // 3️⃣ Relaciones competencia → curso
    $competencyCourses = DB::table('competency_course')
        ->whereIn('competency_id', $competencies->pluck('id'))
        ->get()
        ->groupBy('competency_id');

    // 4️⃣ Heredar el nivel MÁS ALTO de alineación del curso
    return $competencies->map(function ($comp) use ($competencyCourses, $courseStates) {

        $relatedCourses = $competencyCourses[$comp->id] ?? collect();

        if ($relatedCourses->isEmpty()) {
            return [
                'id' => $comp->id,
                'name' => $comp->name,
                'estado' => 'No alineado',
                'connections' => 0,
            ];
        }

       $hasMarket = false;
$hasTrend  = false;

foreach ($relatedCourses as $rel) {

    if (!isset($courseStates[$rel->course_id])) {
        continue;
    }

    $hasMarket = $hasMarket || $courseStates[$rel->course_id]['market_match'];
    $hasTrend  = $hasTrend  || $courseStates[$rel->course_id]['trend_match'];
}

$estado = match (true) {
    $hasMarket && $hasTrend => 'Estrategicamente alineado',
    $hasMarket || $hasTrend => 'Alineado',
    default => 'No alineado',
};


        return [
    'id' => $comp->id,
    'name' => $comp->name,
    'estado' => $estado,
    'market_match' => $hasMarket,
    'trend_match'  => $hasTrend,
];

    });
}

}
