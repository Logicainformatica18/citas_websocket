<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CCTCService
{
    /* =====================================================
       COURSES CCTC
    ===================================================== */

public function getCourses(
    int $careerId,
    int $year
): Collection {

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
       MERCADO (solo año)
    ========================= */

    $marketCourses = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('language_job as lj', 'lj.language_id', '=', 'l.id')
                ->join('job_offers as jo', 'jo.id', '=', 'lj.job_offer_id')
                ->whereIn('cl.course_id', $courseIds)
                ->whereYear('jo.published_at', $year)
                ->pluck('cl.course_id')
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('technology_job as tj', 'tj.technology_id', '=', 't.id')
                ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
                ->whereIn('ct.course_id', $courseIds)
                ->whereYear('jo.published_at', $year)
                ->pluck('ct.course_id')
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('methodology_job as mj', 'mj.methodology_id', '=', 'm.id')
                ->join('job_offers as jo', 'jo.id', '=', 'mj.job_offer_id')
                ->whereIn('cm.course_id', $courseIds)
                ->whereYear('jo.published_at', $year)
                ->pluck('cm.course_id')
        )
        ->unique()
        ->flip();

    /* =========================
       TENDENCIAS (solo año)
    ========================= */

    $trendCourses = collect()
        ->merge(
            DB::table('course_language as cl')
                ->join('languages as l', 'l.id', '=', 'cl.language_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'l.market_entity_id')
                ->whereIn('cl.course_id', $courseIds)
                ->where('et.year', $year)
                ->pluck('cl.course_id')
        )
        ->merge(
            DB::table('course_technology as ct')
                ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 't.market_entity_id')
                ->whereIn('ct.course_id', $courseIds)
                ->where('et.year', $year)
                ->pluck('ct.course_id')
        )
        ->merge(
            DB::table('course_methodology as cm')
                ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                ->join('entity_trends as et', 'et.market_entity_id', '=', 'm.market_entity_id')
                ->whereIn('cm.course_id', $courseIds)
                ->where('et.year', $year)
                ->pluck('cm.course_id')
        )
        ->unique()
        ->flip();

    /* =========================
       MAPEADO FINAL (COMPATIBLE)
    ========================= */

    return $courses->map(function ($course) use ($marketCourses, $trendCourses) {

        $hasMarket = isset($marketCourses[$course->id]);
        $hasTrend  = isset($trendCourses[$course->id]);

        $connections =
            ($hasMarket ? 1 : 0) +
            ($hasTrend ? 1 : 0);

        $status = match (true) {
            $hasMarket && $hasTrend => 'Estrategicamente alineado',
            $hasMarket || $hasTrend => 'Alineado',
            default => 'No alineado',
        };

        return [
            'id' => $course->id,
            'name' => $course->name,
            'estado' => $status,
            'connections' => $connections,

            // 🔥 para tu frontend
            'market_match' => $hasMarket,
            'trend_match'  => $hasTrend,
            'empleo'       => $hasMarket ? 'Demanda activa' : 'Sin demanda',
            'tendencias'   => $hasTrend ? 'Detectado' : 'No detectado',

            'gap_label' => null,
            'gap_count' => null,
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
