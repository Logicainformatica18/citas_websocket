<?php

namespace App\Http\Controllers;

use App\Models\SqlTraining;
use App\Services\SqlAnalyzerService;
use App\Services\SemanticMapService;
use App\Services\FilterSuggestionService;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class SegmentAnalyzerController extends Controller
{
    public function segmentData(Request $request, $id)
{
    try {
        // 1️⃣ Obtener el widget
        $widget = DB::table('dashboard_widgets')->find($id);

        if (!$widget) {
            return response()->json(['error' => 'Widget no encontrado.'], 404);
        }

        $dataSource = json_decode($widget->data_source, true);
        $sql = $dataSource['sql_query'] ?? null;

        if (!$sql) {
            return response()->json(['error' => 'El widget no tiene una SQL asociada.'], 400);
        }

        // 2️⃣ Filtros dinámicos del frontend
        $filters = $request->input('filters', []);

        // 3️⃣ Ejecutar el servicio de reescritura (sin joins)
        $result = \App\Services\SqlRewriterService::applyFilters($sql, $filters);

        if ($result['error']) {
            return response()->json([
                'error' => true,
                'message' => $result['message'] ?? 'Error ejecutando SQL',
                'final_sql' => $result['final_sql'] ?? null,
            ], 500);
        }

        // 4️⃣ Retornar resultados al frontend
        return response()->json([
            'widget_id' => $widget->id,
            'sql_final' => $result['final_sql'],
            'rows' => $result['rows'],
        ]);

    } catch (\Throwable $e) {
        Log::error('💥 Error en segmentData', [
            'widget_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'Error al aplicar segmentación: ' . $e->getMessage(),
        ], 500);
    }
}

    public function analyze($id)
    {
        $training = SqlTraining::findOrFail($id);
        $sql = $training->sql_validated;

        $tables = SqlAnalyzerService::extractTables($sql);
        $map = config('semantic_map');
        $reachable = [];

        foreach ($tables as $table) {
            $reachable = array_merge($reachable, SemanticMapService::reachable($table, $map));
        }

        $filters = FilterSuggestionService::suggest(array_unique($reachable));

        return response()->json([
            'base_tables' => $tables,
            'reachable_tables' => array_unique($reachable),
            'filters' => $filters,
        ]);
    }
    public function execute(Request $request, $id)
{
    $training = \App\Models\SqlTraining::findOrFail($id);
    $sql = $training->sql_validated ?? $training->sql_generated;

    if (!$sql) {
        return response()->json(['error' => 'No hay SQL para ejecutar.'], 400);
    }

    $filters = $request->input('filters', []);

    $result = \App\Services\SqlRewriterService::applyFilters($sql, $filters);

    if ($result['error']) {
        return response()->json([
            'error' => true,
            'message' => $result['message'],
            'final_sql' => $result['final_sql'],
        ], 400);
    }

    return response()->json([
        'final_sql' => $result['final_sql'],
        'rows' => $result['rows'],
    ]);
}

}
