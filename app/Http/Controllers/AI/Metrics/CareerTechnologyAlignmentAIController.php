<?php

namespace App\Http\Controllers\AI\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class CareerTechnologyAlignmentAIController extends Controller
{
    /**
     * 📋 Devuelve metadata para filtros dinámicos
     */
    public function metadata()
    {
        return response()->json([
            'years' => DB::table('technology_metrics')
                ->selectRaw('YEAR(run_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year'),

            'careers' => DB::table('careers')
                ->select('id', 'name')
                ->where('name', 'NOT LIKE', '%Diseño y Desarrollo de Videojuegos%')
                ->where('name', 'NOT LIKE', '%Diseño de Medios Interactivos (UX)%') // 🚫 Excluye esta carrera
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * 📊 Devuelve métricas de alineación por carrera (basado en tecnologías)
     */
    public function getData(Request $request)
    {
        try {
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

            $sql = "
            WITH metricas_filtradas AS (
                SELECT
                    technology_id,
                    jobs_found_count AS empleos_actuales,
                    JSON_LENGTH(countries_breakdown) AS paises_actuales,
                    run_date
                FROM technology_metrics
                WHERE DATE(run_date) BETWEEN ? AND ?
            ),
            metricas_previas AS (
                SELECT
                    tm.technology_id,
                    tm.jobs_found_count AS empleos_previos
                FROM technology_metrics tm
                WHERE tm.run_date = (
                    SELECT MAX(run_date)
                    FROM technology_metrics
                    WHERE run_date < (
                        SELECT MIN(run_date)
                        FROM technology_metrics
                        WHERE DATE(run_date) BETWEEN ? AND ?
                    )
                )
            ),
            promedio_global AS (
                SELECT AVG(jobs_found_count) AS promedio_empleos
                FROM technology_metrics
                WHERE DATE(run_date) BETWEEN ? AND ?
            )
            SELECT
                c.id AS id_carrera,
                c.name AS carrera,

                IF(? = 'week',
                    CONCAT(
                        'Semana del ',
                        DATE_FORMAT(DATE_SUB(mf.run_date, INTERVAL WEEKDAY(mf.run_date) DAY), '%d %b'),
                        ' al ',
                        DATE_FORMAT(DATE_ADD(mf.run_date, INTERVAL (6 - WEEKDAY(mf.run_date)) DAY), '%d %b %Y')
                    ),
                    DATE_FORMAT(mf.run_date, '%M %Y')
                ) AS periodo,

                IFNULL(AVG(mf.empleos_actuales), 0) AS empleos_actuales,
                IFNULL(AVG(mp.empleos_previos), 0) AS empleos_previos,
                IFNULL(AVG(pg.promedio_empleos), 0) AS promedio_empleos,
                IFNULL(AVG(mf.paises_actuales), 0) AS paises_actuales,

                ROUND(AVG(
                    100 * (
                        0.35 * (CASE WHEN mf.empleos_actuales > 0 THEN 1 ELSE 0 END) +
                        0.35 * (CASE WHEN pg.promedio_empleos > 0 THEN LEAST(mf.empleos_actuales / pg.promedio_empleos, 1) ELSE 0 END) +
                        0.15 * LEAST(mf.paises_actuales / 5, 1) +
                        0.15 * (CASE WHEN mp.empleos_previos > 0 THEN LEAST((mf.empleos_actuales - mp.empleos_previos) / mp.empleos_previos, 1) ELSE 0 END)
                    )
                ), 2) AS alineacion_tecnologias

            FROM careers c
            JOIN career_course cc ON cc.career_id = c.id
            JOIN course_technology ct ON ct.course_id = cc.course_id
            LEFT JOIN metricas_filtradas mf ON mf.technology_id = ct.technology_id
            LEFT JOIN metricas_previas mp ON mp.technology_id = ct.technology_id
            CROSS JOIN promedio_global pg
            WHERE c.name NOT LIKE '%Diseño y Desarrollo de Videojuegos%
            and c.name NOT LIKE '%Diseño de Medios Interactivos (UX)%
            ";

            if (!empty($careerIds)) {
                $ids = implode(',', array_map('intval', $careerIds));
                $sql .= " AND c.id IN ($ids)";
            }

            $sql .= "
            GROUP BY c.id, c.name, periodo
            ORDER BY STR_TO_DATE(periodo, '%Y-%m-%d') ASC, alineacion_tecnologias DESC;
            ";

            $results = DB::select($sql, [
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate,
                $groupBy,
            ]);

            $collection = collect($results);
            $periods = $collection->pluck('periodo')->unique()->values();

            $trendData = $periods->map(function ($periodo) use ($collection) {
                $row = ['periodo' => $periodo];
                foreach ($collection->where('periodo', $periodo) as $item) {
                    $row[$item->carrera] = round($item->alineacion_tecnologias, 2);
                }
                return $row;
            });

            return response()->json([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'group_by' => $groupBy,
                'avg_alignment' => round($collection->avg('alineacion_tecnologias'), 2),
                'total_careers' => $collection->pluck('id_carrera')->unique()->count(),
                'trend_data' => $trendData,
                'results' => $results,
                'message' => "📊 Datos agrupados por $groupBy (modelo 4D, tecnologías)."
            ]);
        } catch (\Throwable $e) {
            \Log::error('❌ [CareerTechnologyAlignmentAIController@getData] Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error interno', 'details' => $e->getMessage()], 500);
        }
    }
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

        // 🧮 Query principal (basada en technology_metrics)
        $sql = "
        WITH metricas_filtradas AS (
            SELECT
                technology_id,
                jobs_found_count AS empleos_actuales,
                JSON_LENGTH(countries_breakdown) AS paises_actuales,
                run_date
            FROM technology_metrics
            WHERE DATE(run_date) BETWEEN ? AND ?
        ),
        metricas_previas AS (
            SELECT
                tm.technology_id,
                tm.jobs_found_count AS empleos_previos
            FROM technology_metrics tm
            WHERE tm.run_date = (
                SELECT MAX(run_date)
                FROM technology_metrics
                WHERE run_date < (
                    SELECT MIN(run_date)
                    FROM technology_metrics
                    WHERE DATE(run_date) BETWEEN ? AND ?
                )
            )
        ),
        promedio_global AS (
            SELECT AVG(jobs_found_count) AS promedio_empleos
            FROM technology_metrics
            WHERE DATE(run_date) BETWEEN ? AND ?
        )
        SELECT
            c.id AS id_carrera,
            c.name AS carrera,

            -- 🔹 Periodo formateado (IF compatible)
            IF(? = 'week',
                CONCAT(
                    'Semana del ',
                    DATE_FORMAT(DATE_SUB(mf.run_date, INTERVAL WEEKDAY(mf.run_date) DAY), '%d %b'),
                    ' al ',
                    DATE_FORMAT(DATE_ADD(mf.run_date, INTERVAL (6 - WEEKDAY(mf.run_date)) DAY), '%d %b %Y')
                ),
                DATE_FORMAT(mf.run_date, '%M %Y')
            ) AS periodo,

            -- 🔹 Promedios base (para desglose)
            IFNULL(AVG(mf.empleos_actuales), 0) AS empleos_actuales,
            IFNULL(AVG(mp.empleos_previos), 0) AS empleos_previos,
            IFNULL(AVG(pg.promedio_empleos), 0) AS promedio_empleos,
            IFNULL(AVG(mf.paises_actuales), 0) AS paises_actuales,

            -- 🔹 Cálculo del modelo 4D
            ROUND(AVG(
                100 * (
                    0.35 * (CASE WHEN mf.empleos_actuales > 0 THEN 1 ELSE 0 END) +
                    0.35 * (CASE WHEN pg.promedio_empleos > 0 THEN LEAST(mf.empleos_actuales / pg.promedio_empleos, 1) ELSE 0 END) +
                    0.15 * LEAST(mf.paises_actuales / 5, 1) +
                    0.15 * (CASE WHEN mp.empleos_previos > 0 THEN LEAST((mf.empleos_actuales - mp.empleos_previos) / mp.empleos_previos, 1) ELSE 0 END)
                )
            ), 2) AS alineacion_tecnologias

        FROM careers c
        JOIN career_course cc ON cc.career_id = c.id
        JOIN course_technology ct ON ct.course_id = cc.course_id
        LEFT JOIN metricas_filtradas mf ON mf.technology_id = ct.technology_id
        LEFT JOIN metricas_previas mp ON mp.technology_id = ct.technology_id
        CROSS JOIN promedio_global pg
        WHERE c.name NOT LIKE '%Diseño y Desarrollo de Videojuegos%
        and c.name NOT LIKE '%Diseño de Medios Interactivos (UX)%
        ";

        if (!empty($careerIds)) {
            $ids = implode(',', array_map('intval', $careerIds));
            $sql .= " AND c.id IN ($ids)";
        }

        $sql .= "
        GROUP BY c.id, c.name, periodo
        ORDER BY periodo ASC, alineacion_tecnologias DESC;
        ";

        // 🔹 Ejecutar consulta
        $data = collect(DB::select($sql, [
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $groupBy,
        ]));

        if ($data->isEmpty()) {
            return response()->json(['error' => 'No hay datos para exportar'], 404);
        }

        // 📤 Exportar a Excel
        if ($format === 'excel') {
            $exportData = $data->map(fn($row) => [
                'Carrera' => $row->carrera,
                'Periodo' => $row->periodo,
                'Alineación Tecnológica (%)' => $row->alineacion_tecnologias,
            ]);

            $filename = "Alineacion_Tecnologias_{$groupBy}_" . now()->format('Ymd_His') . ".xlsx";

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ArrayExport($exportData->toArray(), [
                    'title' => 'Alineación de Carreras por Tecnologías (Modelo 4D)',
                    'created_at' => now()->format('d/m/Y H:i'),
                ]),
                $filename
            );
        }

        // 📄 Exportar a PDF
        if ($format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.Technologiesalignment_report', [
                'data' => $data,
                'groupBy' => $groupBy,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ])->setPaper('a4', 'landscape');

            $filename = "Alineacion_Tecnologias_{$groupBy}_" . now()->format('Ymd_His') . ".pdf";
            return $pdf->download($filename);
        }

        return response()->json(['error' => 'Formato no soportado'], 400);
    } catch (\Throwable $e) {
        \Log::error("❌ [CareerTechnologyAlignmentAIController@export] Error", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => 'Error interno al exportar', 'details' => $e->getMessage()], 500);
    }
}

}
