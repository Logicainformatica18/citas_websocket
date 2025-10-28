<?php

namespace App\Http\Controllers\AI\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetricsDashboardController extends Controller
{
    /**
     * 📈 Nivel global de alineación del portafolio con tendencias
     */
public function globalAlignment()
{
    try {
        // ============================================================
        // 🎯 1️⃣ Obtener solo métricas vinculadas a carreras activas
        // ============================================================
        $languageIds = DB::table('course_language AS cl')
            ->join('career_course AS cc', 'cc.course_id', '=', 'cl.course_id')
            ->join('careers AS c', 'c.id', '=', 'cc.career_id')
            ->where('c.active', 1)
            ->distinct()
            ->pluck('cl.language_id');

        $technologyIds = DB::table('course_technology AS ct')
            ->join('career_course AS cc', 'cc.course_id', '=', 'ct.course_id')
            ->join('careers AS c', 'c.id', '=', 'cc.career_id')
            ->where('c.active', 1)
            ->distinct()
            ->pluck('ct.technology_id');

        $methodologyIds = DB::table('course_methodology AS cm')
            ->join('career_course AS cc', 'cc.course_id', '=', 'cm.course_id')
            ->join('careers AS c', 'c.id', '=', 'cc.career_id')
            ->where('c.active', 1)
            ->distinct()
            ->pluck('cm.methodology_id');

        // ============================================================
        // 🧠 2️⃣ Calcular promedios base por dimensión (evita nulls)
        // ============================================================

        // 🔹 Lenguajes
        $avgLangJobs = DB::table('language_metrics')
            ->whereIn('language_id', $languageIds)
            ->avg('jobs_found_count') ?? 0;

        $avgLang = DB::table('language_metrics')
            ->whereIn('language_id', $languageIds)
            ->avg(DB::raw("
                100 * (
                    0.35 * (CASE WHEN jobs_found_count > 0 THEN 1 ELSE 0 END) +
                    0.35 * (CASE WHEN {$avgLangJobs} > 0 THEN LEAST(jobs_found_count / {$avgLangJobs}, 1) ELSE 0 END) +
                    0.15 * LEAST(JSON_LENGTH(countries_breakdown) / 5, 1) +
                    0.15 * (CASE WHEN jobs_new_count > 0 THEN 1 ELSE 0 END)
                )
            ")) ?? 0;

        // 🔹 Tecnologías
        $avgTechJobs = DB::table('technology_metrics')
            ->whereIn('technology_id', $technologyIds)
            ->avg('jobs_found_count') ?? 0;

        $avgTech = DB::table('technology_metrics')
            ->whereIn('technology_id', $technologyIds)
            ->avg(DB::raw("
                100 * (
                    0.35 * (CASE WHEN jobs_found_count > 0 THEN 1 ELSE 0 END) +
                    0.35 * (CASE WHEN {$avgTechJobs} > 0 THEN LEAST(jobs_found_count / {$avgTechJobs}, 1) ELSE 0 END) +
                    0.15 * LEAST(JSON_LENGTH(countries_breakdown) / 5, 1) +
                    0.15 * (CASE WHEN jobs_new_count > 0 THEN 1 ELSE 0 END)
                )
            ")) ?? 0;

        // 🔹 Metodologías
        $avgMethJobs = DB::table('methodology_metrics')
            ->whereIn('methodology_id', $methodologyIds)
            ->avg('jobs_found_count') ?? 0;

        $avgMeth = DB::table('methodology_metrics')
            ->whereIn('methodology_id', $methodologyIds)
            ->avg(DB::raw("
                100 * (
                    0.35 * (CASE WHEN jobs_found_count > 0 THEN 1 ELSE 0 END) +
                    0.35 * (CASE WHEN {$avgMethJobs} > 0 THEN LEAST(jobs_found_count / {$avgMethJobs}, 1) ELSE 0 END) +
                    0.15 * LEAST(JSON_LENGTH(countries_breakdown) / 5, 1) +
                    0.15 * (CASE WHEN jobs_new_count > 0 THEN 1 ELSE 0 END)
                )
            ")) ?? 0;

        // ============================================================
        // ⚖️ 3️⃣ Promedio ponderado global del portafolio ISIL
        // ============================================================
        $global = round(
            (0.4 * $avgLang) + (0.4 * $avgTech) + (0.2 * $avgMeth),
            2
        );

        // ============================================================
        // 🏆 4️⃣ Top 3 por dimensión (solo carreras activas)
        // ============================================================
        $topLanguages = DB::table('language_metrics')
            ->whereIn('language_id', $languageIds)
            ->select('language_name as name', DB::raw("
                ROUND(AVG(
                    100 * (
                        0.35 * (CASE WHEN jobs_found_count > 0 THEN 1 ELSE 0 END) +
                        0.35 * (CASE WHEN {$avgLangJobs} > 0 THEN LEAST(jobs_found_count / {$avgLangJobs}, 1) ELSE 0 END) +
                        0.15 * LEAST(JSON_LENGTH(countries_breakdown) / 5, 1) +
                        0.15 * (CASE WHEN jobs_new_count > 0 THEN 1 ELSE 0 END)
                    )
                ),2) as score
            "))
            ->groupBy('language_name')
            ->orderByDesc('score')
            ->limit(3)
            ->pluck('name');

        $topTechnologies = DB::table('technology_metrics')
            ->whereIn('technology_id', $technologyIds)
            ->select('technology_name as name', DB::raw("
                ROUND(AVG(
                    100 * (
                        0.35 * (CASE WHEN jobs_found_count > 0 THEN 1 ELSE 0 END) +
                        0.35 * (CASE WHEN {$avgTechJobs} > 0 THEN LEAST(jobs_found_count / {$avgTechJobs}, 1) ELSE 0 END) +
                        0.15 * LEAST(JSON_LENGTH(countries_breakdown) / 5, 1) +
                        0.15 * (CASE WHEN jobs_new_count > 0 THEN 1 ELSE 0 END)
                    )
                ),2) as score
            "))
            ->groupBy('technology_name')
            ->orderByDesc('score')
            ->limit(3)
            ->pluck('name');

        $topMethodologies = DB::table('methodology_metrics')
            ->whereIn('methodology_id', $methodologyIds)
            ->select('methodology_name as name', DB::raw("
                ROUND(AVG(
                    100 * (
                        0.35 * (CASE WHEN jobs_found_count > 0 THEN 1 ELSE 0 END) +
                        0.35 * (CASE WHEN {$avgMethJobs} > 0 THEN LEAST(jobs_found_count / {$avgMethJobs}, 1) ELSE 0 END) +
                        0.15 * LEAST(JSON_LENGTH(countries_breakdown) / 5, 1) +
                        0.15 * (CASE WHEN jobs_new_count > 0 THEN 1 ELSE 0 END)
                    )
                ),2) as score
            "))
            ->groupBy('methodology_name')
            ->orderByDesc('score')
            ->limit(3)
            ->pluck('name');

        // ============================================================
        // 📊 5️⃣ Respuesta final enriquecida
        // ============================================================
        return response()->json([
            'metric' => 'global_alignment',
            'label' => 'Nivel de alineación global del portafolio académico ISIL',
            'unit' => '%',
            'value' => round($global, 2),
            'has_data' => ($avgLang + $avgTech + $avgMeth) > 0,
            'by_dimension' => [
                'languages' => round($avgLang, 2),
                'technologies' => round($avgTech, 2),
                'methodologies' => round($avgMeth, 2),
            ],
            'insights' => [
                'dominant_dimension' => collect([
                    'languages' => $avgLang,
                    'technologies' => $avgTech,
                    'methodologies' => $avgMeth,
                ])->sortDesc()->keys()->first(),
                'lowest_dimension' => collect([
                    'languages' => $avgLang,
                    'technologies' => $avgTech,
                    'methodologies' => $avgMeth,
                ])->sort()->keys()->first(),
                'top_languages' => $topLanguages,
                'top_technologies' => $topTechnologies,
                'top_methodologies' => $topMethodologies,
            ],
            'meta' => [
                'year' => now()->year,
                'timestamp' => now()->toDateTimeString(),
                'records' => [
                    'languages' => DB::table('language_metrics')->whereIn('language_id', $languageIds)->count(),
                    'technologies' => DB::table('technology_metrics')->whereIn('technology_id', $technologyIds)->count(),
                    'methodologies' => DB::table('methodology_metrics')->whereIn('methodology_id', $methodologyIds)->count(),
                ],
            ],
        ]);

    } catch (\Throwable $e) {
        Log::error('💥 [globalAlignment] Error calculando alineación global', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error' => 'No se pudo calcular la alineación global del portafolio académico.',
            'details' => $e->getMessage(),
        ], 500);
    }
}




    /**
     * 🤖 Porcentaje de cursos que integran IA
     */
    public function aiIntegration()
    {
        $totalCourses = DB::table('courses')->count();
        $aiCourses = DB::table('courses')
            ->where('description', 'like', '%inteligencia artificial%')
            ->orWhere('name', 'like', '%IA%')
            ->count();

        $percent = $totalCourses > 0 ? ($aiCourses / $totalCourses) * 100 : 0;

        return response()->json([
            'metric' => 'ai_integration',
            'label' => 'Cursos con IA integrada',
            'value' => round($percent, 2),
            'unit' => '%'
        ]);
    }

    /**
     * 🔁 Actualizaciones curriculares realizadas este año
     */
    public function curricularUpdates()
    {
        $count = DB::table('curriculum_updates')
            ->whereYear('updated_at', now()->year)
            ->count();

        return response()->json([
            'metric' => 'curricular_updates',
            'label' => 'Actualizaciones curriculares del año',
            'value' => $count,
            'unit' => 'actualizaciones'
        ]);
    }

    /**
     * 📊 Tecnologías con mayor crecimiento trimestral
     */
    public function techGrowth()
    {
        $recent = now()->subMonths(3)->toDateString();

        $data = DB::table('technology_metrics')
            ->select('technology_name',
                DB::raw('AVG(jobs_found_count) as promedio_actual'),
                DB::raw('AVG(jobs_new_count) as nuevos')
            )
            ->where('run_date', '>=', $recent)
            ->groupBy('technology_name')
            ->orderByDesc('nuevos')
            ->limit(10)
            ->get();

        return response()->json([
            'metric' => 'tech_growth',
            'label' => 'Top 10 tecnologías con mayor crecimiento trimestral',
            'data' => $data
        ]);
    }

    /**
     * ⏳ Índice de obsolescencia promedio
     */
    public function obsolescenceIndex()
    {
        $avg = DB::table('technology_metrics')
            ->avg(DB::raw('100 - (jobs_found_count / (SELECT MAX(jobs_found_count) FROM technology_metrics) * 100)'));

        return response()->json([
            'metric' => 'obsolescence_index',
            'label' => 'Índice de obsolescencia promedio',
            'value' => round($avg, 2),
            'unit' => '%'
        ]);
    }

    /**
     * 🚀 Carreras con mayor mejora de alineación
     */
    public function careerImprovement()
    {
        $data = DB::select("
            SELECT c.name AS carrera,
                   ROUND(AVG(lm.jobs_found_count) -
                         (SELECT AVG(jobs_found_count)
                          FROM language_metrics
                          WHERE YEAR(run_date) = YEAR(CURDATE()) - 1), 2) AS mejora
            FROM careers c
            JOIN career_course cc ON cc.career_id = c.id
            JOIN course_language cl ON cl.course_id = cc.course_id
            JOIN language_metrics lm ON lm.language_id = cl.language_id
            WHERE YEAR(lm.run_date) = YEAR(CURDATE())
            GROUP BY c.name
            ORDER BY mejora DESC
            LIMIT 10;
        ");

        return response()->json([
            'metric' => 'career_improvement',
            'label' => 'Carreras con mejora interanual de alineación',
            'data' => $data
        ]);
    }
}
