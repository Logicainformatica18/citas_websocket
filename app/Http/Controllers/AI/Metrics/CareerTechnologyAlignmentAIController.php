<?php

namespace App\Http\Controllers\AI\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                ->orderByDesc('year')
                ->pluck('year'),

            'careers' => DB::table('careers')
                ->select('id', 'name')
                ->whereNotLike('name', '%Diseño y Desarrollo de Videojuegos%')
                ->whereNotLike('name', '%Diseño de Medios Interactivos (UX)%')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * 🧠 Construye la consulta base reutilizable
     */
    private function buildQuery($careerIds = [], $groupBy = 'week')
    {
        $periodCase = $groupBy === 'week'
            ? "CONCAT(
                    'Semana del ',
                    DATE_FORMAT(DATE_SUB(DATE(mf.run_date), INTERVAL WEEKDAY(DATE(mf.run_date)) DAY), '%d %b'),
                    ' al ',
                    DATE_FORMAT(DATE_ADD(DATE(mf.run_date), INTERVAL (6 - WEEKDAY(DATE(mf.run_date))) DAY), '%d %b %Y')
                )"
            : "DATE_FORMAT(DATE(mf.run_date), '%M %Y')";

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
        ),
        base AS (
            SELECT
                c.id AS id_carrera,
                c.name AS carrera,
                DATE(mf.run_date) AS fecha_ref,
                mf.empleos_actuales,
                mf.paises_actuales,
                mp.empleos_previos,
                pg.promedio_empleos,
                $periodCase AS periodo
            FROM careers c
            JOIN career_course cc ON cc.career_id = c.id
            JOIN course_technology ct ON ct.course_id = cc.course_id
            LEFT JOIN metricas_filtradas mf ON mf.technology_id = ct.technology_id
            LEFT JOIN metricas_previas mp ON mp.technology_id = ct.technology_id
            CROSS JOIN promedio_global pg
            WHERE c.name NOT LIKE '%Diseño y Desarrollo de Videojuegos%'
              AND c.name NOT LIKE '%Diseño de Medios Interactivos (UX)%'
        )
        SELECT
            b.id_carrera,
            b.carrera,
            MIN(b.fecha_ref) AS fecha_inicio,
            MAX(b.fecha_ref) AS fecha_fin,
            b.periodo,
            IFNULL(AVG(b.empleos_actuales), 0) AS empleos_actuales,
            IFNULL(AVG(b.empleos_previos), 0) AS empleos_previos,
            IFNULL(AVG(b.promedio_empleos), 0) AS promedio_empleos,
            IFNULL(AVG(b.paises_actuales), 0) AS paises_actuales,
            ROUND(AVG(
                100 * (
                    0.35 * (CASE WHEN b.empleos_actuales > 0 THEN 1 ELSE 0 END) +
                    0.35 * (CASE WHEN b.promedio_empleos > 0 THEN LEAST(b.empleos_actuales / b.promedio_empleos, 1) ELSE 0 END) +
                    0.15 * LEAST(b.paises_actuales / 5, 1) +
                    0.15 * (CASE WHEN b.empleos_previos > 0 THEN LEAST((b.empleos_actuales - b.empleos_previos) / b.empleos_previos, 1) ELSE 0 END)
                )
            ), 2) AS alineacion_tecnologias
        FROM base b
        GROUP BY b.id_carrera, b.carrera, b.periodo
        ORDER BY fecha_inicio ASC, alineacion_tecnologias DESC;
        ";

        if (!empty($careerIds)) {
            $ids = implode(',', array_map('intval', $careerIds));
            $sql = str_replace(
                "WHERE c.name NOT LIKE '%Diseño y Desarrollo de Videojuegos%'",
                "WHERE c.id IN ($ids) AND c.name NOT LIKE '%Diseño y Desarrollo de Videojuegos%'",
                $sql
            );
        }

        return $sql;
    }

    /**
     * 📊 Devuelve métricas (vista web / API)
     */
    public function getData(Request $request)
    {
        try {
            $groupBy = in_array($request->get('group_by'), ['week', 'month'])
                ? $request->get('group_by')
                : 'week';
            $careerIds = (array) $request->get('careers', []);
            $startDate = $request->get('start_date') ?? now()->subMonths(3)->toDateString();
            $endDate = $request->get('end_date') ?? now()->toDateString();

            $sql = $this->buildQuery($careerIds, $groupBy);

            Log::info('📊 Ejecutando getData (tecnologías)', compact('sql'));

            $results = DB::select($sql, [
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate
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
                'message' => "📊 Datos agrupados por $groupBy (modelo 4D - tecnologías)."
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ [CareerTechnologyAlignmentAIController@getData] Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error interno', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * 📤 Exporta resultados a Excel o PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $groupBy = in_array($request->get('group_by'), ['week', 'month'])
                ? $request->get('group_by')
                : 'week';

            $careerIds = (array) $request->get('careers', []);
            $startDate = $request->get('start_date') ?? now()->subMonths(3)->toDateString();
            $endDate = $request->get('end_date') ?? now()->toDateString();

            $sql = $this->buildQuery($careerIds, $groupBy);

            $data = collect(DB::select($sql, [
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate
            ]));

            if ($data->isEmpty()) {
                return response()->json(['error' => 'No hay datos para exportar'], 404);
            }

            // 🟦 Exportar Excel
            if ($format === 'excel') {
                $exportData = $data->map(fn($r) => [
                    'Carrera' => $r->carrera,
                    'Periodo' => $r->periodo,
                    'Empleos Actuales' => $r->empleos_actuales,
                    'Promedio Empleos' => $r->promedio_empleos,
                    'Alineación Tecnológica (%)' => $r->alineacion_tecnologias,
                ]);

                return Excel::download(
                    new \App\Exports\ArrayExport($exportData->toArray(), [
                        'title' => 'Alineación de Carreras (Modelo 4D - Tecnologías)',
                        'created_at' => now()->format('d/m/Y H:i'),
                    ]),
                    "Alineacion_Tecnologias_{$groupBy}_" . now()->format('Ymd_His') . ".xlsx"
                );
            }

            // 🟧 Exportar PDF
            if ($format === 'pdf') {
                $pdf = Pdf::loadView('exports.technologies_alignment_report', [
                    'data' => $data,
                    'groupBy' => $groupBy,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'generatedAt' => now()->format('d/m/Y H:i'),
                ])
                    ->setPaper('a4', 'landscape')
                    ->setOption('isHtml5ParserEnabled', true)
                    ->setOption('isRemoteEnabled', true);

                return $pdf->download("Alineacion_Tecnologias_{$groupBy}_" . now()->format('Ymd_His') . ".pdf");
            }

            return response()->json(['error' => 'Formato no soportado'], 400);
        } catch (\Throwable $e) {
            Log::error('❌ [CareerTechnologyAlignmentAIController@export] Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error interno al exportar',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
