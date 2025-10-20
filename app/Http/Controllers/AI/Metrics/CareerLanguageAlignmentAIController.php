<?php

namespace App\Http\Controllers\AI\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CareerLanguageAlignmentAIController extends Controller
{
    /**
     * 📋 Devuelve metadata para filtros dinámicos
     */
    public function metadata()
    {
        return response()->json([
            'years' => DB::table('language_metrics')
                ->selectRaw('YEAR(run_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year'),
            'careers' => DB::table('careers')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * 📊 Devuelve métricas de alineación por carrera (basado en lenguajes)
     */
    public function getData(Request $request)
    {
        try {
            // 🔹 Filtros recibidos
            $year = (int) $request->get('year', now()->year);
            $careerIds = (array) $request->get('careers', []);
            $periodType = $request->get('period_type');   // "quarter" o "semester"
            $periodValue = $request->get('period_value'); // "Q1", "S2", etc.

            \Log::info("🧩 [CareerLanguageAlignmentAIController@getData] Filtros recibidos", [
                'year' => $year,
                'careers' => $careerIds,
                'period_type' => $periodType,
                'period_value' => $periodValue,
            ]);

            // 🔹 Mapeo de trimestres y semestres
            $quarters = [
                'Q1' => [1, 2, 3],
                'Q2' => [4, 5, 6],
                'Q3' => [7, 8, 9],
                'Q4' => [10, 11, 12],
            ];
            $semesters = [
                'S1' => [1, 2, 3, 4, 5, 6],
                'S2' => [7, 8, 9, 10, 11, 12],
            ];

            // 🔹 Promedio global de jobs_found_count (para normalización)
            $avgJobsFound = DB::table('language_metrics')
                ->avg('jobs_found_count') ?: 1;

            // 🔹 Query base
            $query = DB::table('careers as c')
                ->join('career_course as cc', 'cc.career_id', '=', 'c.id')
                ->join('course_language as cl', 'cl.course_id', '=', 'cc.course_id')
                ->leftJoin('language_metrics as lm', function ($join) use ($year, $periodType, $periodValue, $quarters, $semesters) {
                    $join->on('lm.language_id', '=', 'cl.language_id')
                         ->whereYear('lm.run_date', '=', $year);

                    // 🔸 Filtrado por trimestre o semestre
                    if ($periodType === 'quarter' && isset($quarters[$periodValue])) {
                        $join->whereIn(DB::raw('MONTH(lm.run_date)'), $quarters[$periodValue]);
                    }
                    if ($periodType === 'semester' && isset($semesters[$periodValue])) {
                        $join->whereIn(DB::raw('MONTH(lm.run_date)'), $semesters[$periodValue]);
                    }
                })
                ->select(
                    'c.id',
                    'c.name as career',
                    DB::raw("ROUND(AVG(
                        0.4 * (CASE WHEN lm.jobs_found_count > 0 THEN 1 ELSE 0 END) +
                        0.4 * LEAST(lm.jobs_found_count / {$avgJobsFound}, 1) +
                        0.2 * (
                            CASE
                                WHEN JSON_VALID(lm.countries_breakdown)
                                THEN JSON_LENGTH(lm.countries_breakdown) / 5
                                ELSE 0
                            END
                        )
                    ) * 100, 2) AS language_alignment"),
                    DB::raw("COUNT(DISTINCT cl.language_id) AS total_languages")
                )
                ->groupBy('c.id', 'c.name')
                ->orderByDesc('language_alignment');

            // 🔹 Filtro por carrera (si aplica)
            if (!empty($careerIds)) {
                $query->whereIn('c.id', $careerIds);
            }

            // 🔹 Log del SQL
            \Log::debug("🧠 [CareerLanguageAlignmentAIController@getData] SQL generado", [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            // 🔹 Ejecutar consulta
            $results = $query->get();

            $avgAlignment = round($results->avg('language_alignment'), 2);

            // 🔹 Log final
            \Log::info("✅ [CareerLanguageAlignmentAIController@getData] Resultados obtenidos", [
                'count' => $results->count(),
                'avg_alignment' => $avgAlignment,
                'sample' => $results->take(5),
            ]);

            // 🔹 Respuesta final
            return response()->json([
                'year' => $year,
                'period_type' => $periodType,
                'period_value' => $periodValue,
                'count' => $results->count(),
                'results' => $results,
                'avg_alignment' => $avgAlignment,
                'message' => '📊 Alineación de carreras por lenguajes según trimestre o semestre seleccionado.',
            ]);
        } catch (\Throwable $e) {
            \Log::error("❌ [CareerLanguageAlignmentAIController@getData] Error", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error interno al procesar los datos',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 📤 Exporta resultados a Excel (opcional)
     */
    public function export(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $periodType = $request->get('period_type');
        $periodValue = $request->get('period_value');

        $quarters = [
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12],
        ];
        $semesters = [
            'S1' => [1, 2, 3, 4, 5, 6],
            'S2' => [7, 8, 9, 10, 11, 12],
        ];

        $data = DB::table('careers as c')
            ->join('career_course as cc', 'cc.career_id', '=', 'c.id')
            ->join('course_language as cl', 'cl.course_id', '=', 'cc.course_id')
            ->leftJoin('language_metrics as lm', function ($join) use ($year, $periodType, $periodValue, $quarters, $semesters) {
                $join->on('lm.language_id', '=', 'cl.language_id')
                     ->whereYear('lm.run_date', '=', $year);

                if ($periodType === 'quarter' && isset($quarters[$periodValue])) {
                    $join->whereIn(DB::raw('MONTH(lm.run_date)'), $quarters[$periodValue]);
                }
                if ($periodType === 'semester' && isset($semesters[$periodValue])) {
                    $join->whereIn(DB::raw('MONTH(lm.run_date)'), $semesters[$periodValue]);
                }
            })
            ->select('c.name as career', 'lm.*')
            ->orderBy('c.name')
            ->get();

        // return \Maatwebsite\Excel\Facades\Excel::download(
        //     new \App\Exports\GenericExport($data),
        //     "career-language-alignment-{$year}-{$periodValue}.xlsx"
        // );
    }
}
