<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class CCTCService
{
    /* =====================================================
       COURSES CCTC
    ===================================================== */

    public function getCourses(int $careerId, int $year): Collection
    {
        $t0 = microtime(true);

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

        // Rango explícito: permite usar el índice de published_at
        // (whereYear aplica una función sobre la columna y lo inhabilita)
        $desde = "{$year}-01-01 00:00:00";
        $hasta = "{$year}-12-31 23:59:59";

        /* =====================================
           1️⃣ DIMENSIONES (estructura)
           DISTINCT en el motor, no en PHP
        ===================================== */

        $languageCourses = DB::table('course_language')
            ->whereIn('course_id', $courseIds)
            ->distinct()
            ->pluck('course_id')
            ->flip();

        $technologyCourses = DB::table('course_technology')
            ->whereIn('course_id', $courseIds)
            ->distinct()
            ->pluck('course_id')
            ->flip();

        $methodologyCourses = DB::table('course_methodology')
            ->whereIn('course_id', $courseIds)
            ->distinct()
            ->pluck('course_id')
            ->flip();

        /* =====================================
           2️⃣ MERCADO (empleo real del año)
           EXISTS: corta en la 1ra oferta que matchea
        ===================================== */

        $marketCourses = collect()
            ->merge(
                DB::table('course_language as cl')
                    ->whereIn('cl.course_id', $courseIds)
                    ->whereExists(function ($q) use ($desde, $hasta) {
                        $q->from('language_job as lj')
                            ->join('job_offers as jo', 'jo.id', '=', 'lj.job_offer_id')
                            ->whereColumn('lj.language_id', 'cl.language_id')
                            ->whereBetween('jo.published_at', [$desde, $hasta]);
                    })
                    ->distinct()
                    ->pluck('cl.course_id')
            )
            ->merge(
                DB::table('course_technology as ct')
                    ->whereIn('ct.course_id', $courseIds)
                    ->whereExists(function ($q) use ($desde, $hasta) {
                        $q->from('technology_job as tj')
                            ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
                            ->whereColumn('tj.technology_id', 'ct.technology_id')
                            ->whereBetween('jo.published_at', [$desde, $hasta]);
                    })
                    ->distinct()
                    ->pluck('ct.course_id')
            )
            ->merge(
                DB::table('course_methodology as cm')
                    ->whereIn('cm.course_id', $courseIds)
                    ->whereExists(function ($q) use ($desde, $hasta) {
                        $q->from('methodology_job as mj')
                            ->join('job_offers as jo', 'jo.id', '=', 'mj.job_offer_id')
                            ->whereColumn('mj.methodology_id', 'cm.methodology_id')
                            ->whereBetween('jo.published_at', [$desde, $hasta]);
                    })
                    ->distinct()
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
                    ->whereIn('cl.course_id', $courseIds)
                    ->whereExists(function ($q) use ($year) {
                        $q->from('entity_trends as et')
                            ->whereColumn('et.market_entity_id', 'l.market_entity_id')
                            ->where('et.year', $year);
                    })
                    ->distinct()
                    ->pluck('cl.course_id')
            )
            ->merge(
                DB::table('course_technology as ct')
                    ->join('technologies as t', 't.id', '=', 'ct.technology_id')
                    ->whereIn('ct.course_id', $courseIds)
                    ->whereExists(function ($q) use ($year) {
                        $q->from('entity_trends as et')
                            ->whereColumn('et.market_entity_id', 't.market_entity_id')
                            ->where('et.year', $year);
                    })
                    ->distinct()
                    ->pluck('ct.course_id')
            )
            ->merge(
                DB::table('course_methodology as cm')
                    ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
                    ->whereIn('cm.course_id', $courseIds)
                    ->whereExists(function ($q) use ($year) {
                        $q->from('entity_trends as et')
                            ->whereColumn('et.market_entity_id', 'm.market_entity_id')
                            ->where('et.year', $year);
                    })
                    ->distinct()
                    ->pluck('cm.course_id')
            )
            ->unique()
            ->flip();

        /* =====================================
           4️⃣ COMPETENCIAS POR CURSO
        ===================================== */

        $courseCompetencies = DB::table('competency_course as cc')
            ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
            ->where('comp.career_id', $careerId)
            ->whereIn('cc.course_id', $courseIds)
            ->select('cc.course_id', DB::raw('COUNT(*) as total'))
            ->groupBy('cc.course_id')
            ->pluck('total', 'course_id');

        /* =====================================
           5️⃣ MAPEADO FINAL
        ===================================== */

        $result = $courses->map(function ($course) use (
            $languageCourses,
            $technologyCourses,
            $methodologyCourses,
            $marketCourses,
            $trendCourses,
            $courseCompetencies
        ) {

            $course   = (array) $course;
            $courseId = $course['id'];

            // 🔹 Dimensiones estructurales
            $hasLanguage    = isset($languageCourses[$courseId]);
            $hasTechnology  = isset($technologyCourses[$courseId]);
            $hasMethodology = isset($methodologyCourses[$courseId]);

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
            $hasMarket = isset($marketCourses[$courseId]);
            $hasTrend  = isset($trendCourses[$courseId]);

            return [
                'id'   => $courseId,
                'name' => $course['name'],

                'estado'            => $status,
                'connections'       => $connectionCount,
                'language_match'    => $hasLanguage,
                'technology_match'  => $hasTechnology,
                'methodology_match' => $hasMethodology,

                'market_match' => $hasMarket,
                'trend_match'  => $hasTrend,
                'empleo'       => $hasMarket ? 'Demanda activa' : 'Sin demanda',
                'tendencias'   => $hasTrend ? 'Detectado' : 'No detectado',

                'competencias' => $courseCompetencies[$courseId] ?? 0,
            ];
        });

        Log::info('CCTCService getCourses', [
            'career_id' => $careerId,
            'year'      => $year,
            'cursos'    => $result->count(),
            'ms'        => round((microtime(true) - $t0) * 1000),
            'mem_mb'    => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        return $result;
    }

    /* =====================================================
       COMPETENCIES CCTC (HEREDA CURSOS)
    ===================================================== */

    public function getCompetencies(int $careerId, int $year): Collection
    {
        // 1️⃣ Cursos ya calculados (hereda la optimización)
        $courses = $this->getCourses($careerId, $year);

        if ($courses->isEmpty()) {
            return collect();
        }

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

        // 4️⃣ Heredar el nivel más alto de alineación
        return $competencies->map(function ($comp) use ($competencyCourses, $courseStates) {

            $relatedCourses = $competencyCourses[$comp->id] ?? collect();

            if ($relatedCourses->isEmpty()) {
                return [
                    'id'          => $comp->id,
                    'name'        => $comp->name,
                    'estado'      => 'No alineado',
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
                'id'           => $comp->id,
                'name'         => $comp->name,
                'estado'       => $estado,
                'market_match' => $hasMarket,
                'trend_match'  => $hasTrend,
            ];
        });
    }
}
