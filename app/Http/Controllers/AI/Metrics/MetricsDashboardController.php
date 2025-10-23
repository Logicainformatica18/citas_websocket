<?php

namespace App\Http\Controllers\AI\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricsDashboardController extends Controller
{
    /**
     * 📈 Nivel global de alineación del portafolio con tendencias
     */
    public function globalAlignment()
    {
        $avg = DB::table('language_metrics')
            ->avg(DB::raw("
                100 * (
                    0.35 * (CASE WHEN jobs_found_count > 0 THEN 1 ELSE 0 END) +
                    0.35 * (CASE WHEN jobs_found_count > 0 THEN LEAST(jobs_found_count / (SELECT AVG(jobs_found_count) FROM language_metrics), 1) ELSE 0 END) +
                    0.15 * (JSON_LENGTH(countries_breakdown) / 5) +
                    0.15 * (CASE WHEN jobs_new_count > 0 THEN 1 ELSE 0 END)
                )
            "));

        return response()->json([
            'metric' => 'global_alignment',
            'label' => 'Nivel de alineación global',
            'value' => round($avg, 2),
            'unit' => '%'
        ]);
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
