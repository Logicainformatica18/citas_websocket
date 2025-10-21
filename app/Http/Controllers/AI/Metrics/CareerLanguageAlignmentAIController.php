<?php

namespace App\Http\Controllers\AI\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

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
                ->where('name', 'NOT LIKE', '%Diseño y Desarrollo de Videojuegos%') // 🚫 Excluye esta carrera
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
            // 📆 Parámetros recibidos
            $groupBy = in_array($request->get('group_by'), ['week', 'month'])
                ? $request->get('group_by')
                : 'week';

            $careerIds = (array) $request->get('careers', []);
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // 🕒 Por defecto: últimos 3 meses
            if (!$startDate || !$endDate) {
                $startDate = now()->subMonths(3)->toDateString();
                $endDate = now()->toDateString();
            }

            // 🧮 Query principal (MariaDB / MySQL 8 compatible)
            $sql = "
            WITH metricas_filtradas AS (
                SELECT
                    language_id,
                    jobs_found_count AS empleos_actuales,
                    JSON_LENGTH(countries_breakdown) AS paises_actuales,
                    run_date
                FROM language_metrics
                WHERE DATE(run_date) BETWEEN ? AND ?
            ),
            metricas_previas AS (
                SELECT
                    lm.language_id,
                    lm.jobs_found_count AS empleos_previos
                FROM language_metrics lm
                WHERE lm.run_date = (
                    SELECT MAX(run_date)
                    FROM language_metrics
                    WHERE run_date < (
                        SELECT MIN(run_date)
                        FROM language_metrics
                        WHERE DATE(run_date) BETWEEN ? AND ?
                    )
                )
            ),
            promedio_global AS (
                SELECT AVG(jobs_found_count) AS promedio_empleos
                FROM language_metrics
                WHERE DATE(run_date) BETWEEN ? AND ?
            )
            SELECT
                c.id AS id_carrera,
                c.name AS carrera,

                -- 🔹 Periodo dinámico
                IF(? = 'week',
                    CONCAT(
                        'Semana del ',
                        DATE_FORMAT(DATE_SUB(mf.run_date, INTERVAL WEEKDAY(mf.run_date) DAY), '%d %b'),
                        ' al ',
                        DATE_FORMAT(DATE_ADD(mf.run_date, INTERVAL (6 - WEEKDAY(mf.run_date)) DAY), '%d %b %Y')
                    ),
                    DATE_FORMAT(mf.run_date, '%M %Y')
                ) AS periodo,

                -- 🔹 Datos base para desglose
            -- 🔹 Datos base para desglose
-- 🔹 Datos base para desglose
IFNULL(AVG(mf.empleos_actuales), 0) AS empleos_actuales,
IFNULL(AVG(mp.empleos_previos), 0) AS empleos_previos,
IFNULL(AVG(pg.promedio_empleos), 0) AS promedio_empleos,
IFNULL(AVG(mf.paises_actuales), 0) AS paises_actuales,


                -- 🔹 Modelo 4D ponderado
                ROUND(AVG(
                    100 * (
                        0.35 * (CASE WHEN mf.empleos_actuales > 0 THEN 1 ELSE 0 END) +
                        0.35 * (CASE WHEN pg.promedio_empleos > 0 THEN LEAST(mf.empleos_actuales / pg.promedio_empleos, 1) ELSE 0 END) +
                        0.15 * LEAST(mf.paises_actuales / 5, 1) +
                        0.15 * (CASE WHEN mp.empleos_previos > 0 THEN LEAST((mf.empleos_actuales - mp.empleos_previos) / mp.empleos_previos, 1) ELSE 0 END)
                    )
                ), 2) AS alineacion_lenguajes

            FROM careers c
            JOIN career_course cc ON cc.career_id = c.id
            JOIN course_language cl ON cl.course_id = cc.course_id
            LEFT JOIN metricas_filtradas mf ON mf.language_id = cl.language_id
            LEFT JOIN metricas_previas mp ON mp.language_id = cl.language_id
            CROSS JOIN promedio_global pg
            WHERE c.name NOT LIKE '%Diseño y Desarrollo de Videojuegos%'
        ";

            // 🔹 Filtro por carrera (si se eligieron)
            if (!empty($careerIds)) {
                $ids = implode(',', array_map('intval', $careerIds));
                $sql .= " AND c.id IN ($ids)";
            }

            // 🔹 Agrupación y orden final
            $sql .= "
            GROUP BY c.id, c.name, periodo
            ORDER BY STR_TO_DATE(periodo, '%Y-%m-%d') ASC, alineacion_lenguajes DESC;
        ";

            // 🔹 Ejecución segura
            $results = DB::select($sql, [
                $startDate,
                $endDate,  // metricas_filtradas
                $startDate,
                $endDate,  // metricas_previas
                $startDate,
                $endDate,  // promedio_global
                $groupBy,              // tipo de agrupación
            ]);

            // 🔄 Colección
            $collection = collect($results);

            // 🔹 Periodos únicos
            $periods = $collection->pluck('periodo')->unique()->values();

            // 🔹 Dataset pivotado
            $trendData = $periods->map(function ($periodo) use ($collection) {
                $row = ['periodo' => $periodo];
                foreach ($collection->where('periodo', $periodo) as $item) {
                    $row[$item->carrera] = round($item->alineacion_lenguajes, 2);
                }
                return $row;
            });

            // 🔹 KPIs
            $avgAlignment = round($collection->avg('alineacion_lenguajes'), 2);
            $totalCareers = $collection->pluck('id_carrera')->unique()->count();

            return response()->json([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'group_by' => $groupBy,
                'avg_alignment' => $avgAlignment,
                'total_careers' => $totalCareers,
                'trend_data' => $trendData,
                'results' => $results,
                'message' => "📊 Datos agrupados por $groupBy (modelo 4D)."
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
        try {
            $format = $request->get('format', 'excel'); // excel | pdf
            $groupBy = in_array($request->get('group_by'), ['week', 'month'])
                ? $request->get('group_by')
                : 'week';
            $careerIds = (array) $request->get('careers', []);
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            if (!$startDate || !$endDate) {
                $startDate = now()->subMonths(3)->toDateString();
                $endDate = now()->toDateString();
            }

            // 🔹 Reutilizamos la misma query de getData()
            $sql = "
            WITH metricas_filtradas AS (
                SELECT
                    language_id,
                    jobs_found_count AS empleos_actuales,
                    JSON_LENGTH(countries_breakdown) AS paises_actuales,
                    run_date
                FROM language_metrics
                WHERE DATE(run_date) BETWEEN ? AND ?
            ),
            metricas_previas AS (
                SELECT
                    lm.language_id,
                    lm.jobs_found_count AS empleos_previos
                FROM language_metrics lm
                WHERE lm.run_date = (
                    SELECT MAX(run_date)
                    FROM language_metrics
                    WHERE run_date < (SELECT MIN(run_date) FROM language_metrics WHERE DATE(run_date) BETWEEN ? AND ?)
                )
            ),
            promedio_global AS (
                SELECT AVG(jobs_found_count) AS promedio_empleos
                FROM language_metrics
                WHERE DATE(run_date) BETWEEN ? AND ?
            )
            SELECT
                c.id AS id_carrera,
                c.name AS carrera,
                CASE
                    WHEN ? = 'week' THEN CONCAT(
                        'Semana del ',
                        DATE_FORMAT(DATE_SUB(mf.run_date, INTERVAL WEEKDAY(mf.run_date) DAY), '%d %b'),
                        ' al ',
                        DATE_FORMAT(DATE_ADD(mf.run_date, INTERVAL (6 - WEEKDAY(mf.run_date)) DAY), '%d %b %Y')
                    )
                    WHEN ? = 'month' THEN DATE_FORMAT(mf.run_date, '%M %Y')
                END AS periodo,
                ROUND(AVG(
                    100 * (
                        0.35 * (CASE WHEN mf.empleos_actuales > 0 THEN 1 ELSE 0 END) +
                        0.35 * (CASE WHEN pg.promedio_empleos > 0 THEN LEAST(mf.empleos_actuales / pg.promedio_empleos, 1) ELSE 0 END) +
                        0.15 * LEAST(mf.paises_actuales / 5, 1) +
                        0.15 * (CASE WHEN mp.empleos_previos > 0 THEN LEAST((mf.empleos_actuales - mp.empleos_previos) / mp.empleos_previos, 1) ELSE 0 END)
                    )
                ), 2) AS alineacion_lenguajes
            FROM careers c
            JOIN career_course cc ON cc.career_id = c.id
            JOIN course_language cl ON cl.course_id = cc.course_id
            LEFT JOIN metricas_filtradas mf ON mf.language_id = cl.language_id
            LEFT JOIN metricas_previas mp ON mp.language_id = cl.language_id
            CROSS JOIN promedio_global pg
            WHERE c.name NOT LIKE '%Diseño y Desarrollo de Videojuegos%'
        ";

            if (!empty($careerIds)) {
                $ids = implode(',', array_map('intval', $careerIds));
                $sql .= " AND c.id IN ($ids)";
            }

            $sql .= "
            GROUP BY c.id, c.name, periodo
            ORDER BY periodo ASC, alineacion_lenguajes DESC;
        ";

            $data = collect(DB::select($sql, [
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                $groupBy,
                $groupBy,
            ]));

            if ($data->isEmpty()) {
                return response()->json(['error' => 'No hay datos para exportar'], 404);
            }

            // 🔹 Si el formato es Excel
            if ($format === 'excel') {
                $exportData = $data->map(fn($row) => [
                    'Carrera' => $row->carrera,
                    'Periodo' => $row->periodo,
                    'Alineación Total (%)' => $row->alineacion_lenguajes,
                ]);

                $filename = "Alineacion_Carreras_{$groupBy}_" . now()->format('Ymd_His') . ".xlsx";

                return Excel::download(
                    new \App\Exports\ArrayExport($exportData->toArray(), [
                        'title' => 'Alineación de Carreras por Lenguajes (Modelo 4D)',
                        'created_at' => now()->format('d/m/Y H:i'),
                    ]),
                    $filename
                );
            }

            // 🔹 Si el formato es PDF
            if ($format === 'pdf') {
                // Asegurar colección
                $dataCollection = collect($data);

                // Generar PDF con Blade
                $pdf = Pdf::loadView('exports.alignment_report', [
                    'data' => collect($data), // 👈 convierte array de stdClass a colección
                    'groupBy' => $groupBy,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'generatedAt' => now()->format('d/m/Y H:i'),
                ])->setPaper('a4', 'landscape')
                    ->setOption('isHtml5ParserEnabled', true)
                    ->setOption('isRemoteEnabled', true); // permite cargar imágenes o fuentes externas (ej: QuickChart)

                $filename = "Alineacion_Carreras_{$groupBy}_" . now()->format('Ymd_His') . ".pdf";

                return $pdf->download($filename);
            }


            return response()->json(['error' => 'Formato no soportado'], 400);

        } catch (\Throwable $e) {
            \Log::error("❌ [CareerLanguageAlignmentAIController@export] Error", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error interno al exportar', 'details' => $e->getMessage()], 500);
        }
    }
}
