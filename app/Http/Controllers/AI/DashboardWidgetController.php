<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class DashboardWidgetController extends Controller
{
    public function index(Request $request)
{
    $dashboardId = $request->get('dashboard_id', 1);

    $widgets = DB::table('dashboard_widgets')
        ->where('dashboard_id', $dashboardId)
        ->orderBy('id')
        ->get()
        ->map(function ($w) {
            $w->data_source = json_decode($w->data_source, true);
            $w->colors = json_decode($w->colors, true);
            return $w;
        });

    return response()->json([
        'dashboard_id' => $dashboardId,
        'widgets' => $widgets,
    ]);
}

public function storeFromTraining(Request $request)
{
    Log::info('🟢 [storeFromTraining] Iniciando creación de gráfico', [
        'payload' => $request->all()
    ]);

    try {
        // ============================================================
        // ✅ 1️⃣ Validación de parámetros
        // ============================================================
        $validated = $request->validate([
            'training_id' => 'required|integer|exists:aitrainings,id',
            'chart_type'  => 'required|string',
        ]);
        Log::info('✅ Validación OK', $validated);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::warning('⚠️ Error de validación', ['errors' => $e->errors()]);
        return response()->json([
            'error' => 'Datos inválidos.',
            'details' => $e->errors(),
        ], 400);
    }

    try {
        // ============================================================
        // 🧠 2️⃣ Obtener el entrenamiento
        // ============================================================
        $training = DB::table('aitrainings')->find($validated['training_id']);

        if (!$training) {
            return response()->json(['error' => 'Entrenamiento no encontrado.'], 404);
        }

        Log::info('✅ Entrenamiento encontrado', [
            'training_id' => $training->id,
            'sql_training_id' => $training->sql_training_id,
        ]);

        // ============================================================
        // 🧩 3️⃣ Verificar que tenga SQL asociada y válida
        // ============================================================
        if (!$training->sql_training_id) {
            return response()->json([
                'error' => 'El entrenamiento no tiene una SQL asociada (sql_training_id nulo).'
            ], 400);
        }

        $sqlTraining = DB::table('sqltrainings')
            ->where('id', $training->sql_training_id)
            ->first();

        if (!$sqlTraining) {
            return response()->json(['error' => 'SQL vinculada no encontrada.'], 404);
        }

        if ($sqlTraining->test_status !== 'ok') {
            return response()->json(['error' => 'La SQL vinculada no está validada.'], 400);
        }

        $query = $sqlTraining->sql_validated ?? $sqlTraining->sql_generated;

        if (empty($query)) {
            return response()->json(['error' => 'No se encontró una consulta SQL válida.'], 400);
        }

        // ============================================================
        // 🧮 4️⃣ Ejecutar la consulta SQL
        // ============================================================
        try {
            $rows = DB::select($query);
            // ============================================================
// 🧹 4.1️⃣ Sanear los datos antes de guardar
// ============================================================
$cleanRows = collect($rows)->map(function ($row) {
    $sanitized = [];
    foreach ($row as $key => $value) {
        // ✅ Si es numérico en string, lo convierte a float
        if (is_numeric($value)) {
            $sanitized[$key] = (float) $value;
        }
        // ✅ Si es nulo, lo reemplaza por 0 o cadena vacía
        elseif (is_null($value)) {
            $sanitized[$key] = 0;
        }
        // ✅ Si es booleano, lo convierte explícitamente
        elseif (is_bool($value)) {
            $sanitized[$key] = $value ? 1 : 0;
        }
        // ✅ Si es texto, limpia espacios y caracteres invisibles
        elseif (is_string($value)) {
            $sanitized[$key] = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
        } else {
            $sanitized[$key] = $value;
        }
    }
    return $sanitized;
})->values()->toArray();

        } catch (\Throwable $e) {
            Log::error('💥 Error ejecutando SQL', [
                'error' => $e->getMessage(),
                'sql' => $query,
            ]);
            return response()->json([
                'error' => 'Error al ejecutar SQL: ' . $e->getMessage(),
            ], 500);
        }

        // ============================================================
        // 🧱 5️⃣ Crear widget en dashboard
        // ============================================================
        $widgetId = DB::table('dashboard_widgets')->insertGetId([
            'dashboard_id'   => 1,
            'ai_training_id' => $training->id,
            'title'          => $training->prompt, // 👈 ahora siempre usa el prompt correcto
            'chart_type'     => $validated['chart_type'],
            'data_source'    => json_encode([
                'type' => 'sql',
                'sql_training_id' => $training->sql_training_id,
                'sql_query' => $query,
              'rows' => $cleanRows, // 👈 usa los datos saneados
            ], JSON_UNESCAPED_UNICODE),
            'colors' => json_encode([
                'primary' => '#1E88E5',
                'secondary' => '#90CAF9'
            ]),
            'position_x' => 0,
            'position_y' => 0,
            'width'      => 4,
            'height'     => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('✅ Widget creado correctamente', [
            'widget_id' => $widgetId,
            'training_id' => $training->id,
        ]);

        // ============================================================
        // 🎯 6️⃣ Respuesta final al frontend
        // ============================================================
        return response()->json([
            'message' => '📊 Gráfico generado correctamente.',
            'widget_id' => $widgetId,
            'training_prompt' => $training->prompt,
            'rows' => $rows,
        ]);

    } catch (\Throwable $e) {
        Log::error('💥 Error inesperado en storeFromTraining', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'error' => 'Error inesperado: ' . $e->getMessage(),
        ], 500);
    }
}

public function store(Request $request)
{
    $validated = $request->validate([
        'dashboard_id' => 'required|integer|exists:dashboards,id',
        'type' => 'required|string|in:chart,heading,divider',
        'title' => 'nullable|string|max:255',
        'chart_type' => 'nullable|string',
        'text' => 'nullable|string',
        'group_id' => 'nullable|integer|exists:dashboard_widgets,id',
        'data_source' => 'nullable|json',
        'colors' => 'nullable|json',
        'primary_color' => 'nullable|string|max:10',
        'position_x' => 'nullable|integer',
        'position_y' => 'nullable|integer',
        'width' => 'nullable|integer',
        'height' => 'nullable|integer',
    ]);

    $id = DB::table('dashboard_widgets')->insertGetId(array_merge($validated, [
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    return response()->json(['message' => '✅ Widget creado correctamente.', 'id' => $id]);
}
public function update(Request $request, $id)
{
    $widget = DB::table('dashboard_widgets')->find($id);
    if (!$widget) {
        return response()->json(['error' => 'Widget no encontrado.'], 404);
    }

    $data = $request->validate([
        'title' => 'nullable|string',
        'chart_type' => 'nullable|string',
        'colors' => 'nullable|array',
        'colors.primary' => 'nullable|string',
        'colors.secondary' => 'nullable|string',
        'position_x' => 'nullable|integer',
        'position_y' => 'nullable|integer',
        'width' => 'nullable|integer',
        'height' => 'nullable|integer',
    ]);

    // 🔹 Codificar JSON si viene como array
    if (isset($data['colors'])) {
        $data['colors'] = json_encode($data['colors'], JSON_UNESCAPED_UNICODE);
    }

    $data['updated_at'] = now();

    DB::table('dashboard_widgets')->where('id', $id)->update($data);

    return response()->json(['message' => '✅ Widget actualizado correctamente']);
}

public function destroy($id)
{
    $widget = DB::table('dashboard_widgets')->find($id);

    if (!$widget) {
        return response()->json(['error' => '❌ Widget no encontrado.'], 404);
    }

    // Si es un bloque heading, eliminar también sus hijos
    if ($widget->type === 'heading') {
        DB::table('dashboard_widgets')->where('group_id', $id)->delete();
    }

    DB::table('dashboard_widgets')->where('id', $id)->delete();

    return response()->json(['message' => '🗑️ Widget eliminado correctamente.']);
}
public function reorder(Request $request)
{
    $widgets = $request->input('widgets', []);
    if (!is_array($widgets)) {
        return response()->json(['error' => 'Formato inválido.'], 422);
    }

    foreach ($widgets as $w) {
        if (!isset($w['i'])) continue;

        // 🧱 Si es una sección (id tipo "section-3")
        if (str_starts_with($w['i'], 'section-')) {
            $sectionId = str_replace('section-', '', $w['i']);
            DB::table('dashboard_sections')
                ->where('id', $sectionId)
                ->update([
                    'position' => $w['y'] ?? 0,
                    'width'    => $w['w'] ?? 12,
                    'height'   => $w['h'] ?? 1,
                    'updated_at' => now(),
                ]);
            continue;
        }

        // 📊 Si es un widget normal
        DB::table('dashboard_widgets')
            ->where('id', $w['i'])
            ->update([
                'position_x' => $w['x'] ?? 0,
                'position_y' => $w['y'] ?? 0,
                'width'      => $w['w'] ?? 4,
                'height'     => $w['h'] ?? 3,
                'updated_at' => now(),
            ]);
    }

    return response()->json(['message' => '✅ Reordenado correctamente'], 200);
}


public function updateColor(Request $request, $id)
{
    try {
        $validated = $request->validate([
            'color' => 'required|string|max:20',
        ]);

        $widget = DB::table('dashboard_widgets')->find($id);

        if (!$widget) {
            return response()->json(['error' => 'Widget no encontrado.'], 404);
        }

        // ✅ Decodificar los colores actuales
        $colors = json_decode($widget->colors, true) ?? [
            'primary' => '#1E88E5',
            'secondary' => '#90CAF9',
        ];

        // ✅ Actualizar solo el color primario
        $colors['primary'] = $validated['color'];

        DB::table('dashboard_widgets')
            ->where('id', $id)
            ->update([
                'colors' => json_encode($colors, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => '🎨 Color actualizado correctamente.',
            'colors' => $colors,
        ]);
    } catch (\Throwable $e) {
        Log::error('💥 Error actualizando color de widget', [
            'id' => $id,
            'error' => $e->getMessage(),
        ]);
        return response()->json(['error' => 'Error al actualizar color.'], 500);
    }
}


}
