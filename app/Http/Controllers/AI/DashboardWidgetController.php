<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Dashboard;
use Illuminate\Support\Facades\Log;
class DashboardWidgetController extends Controller
{
public function index(Request $request)
{
    // 1️⃣ Obtener dashboard activo (default o primero)
    $dashboard = Dashboard::where('is_default', 1)->first()
        ?? Dashboard::orderBy('id')->first();

    if (!$dashboard) {
        return response()->json([
            'widgets' => [],
        ]);
    }

    // 2️⃣ Obtener widgets SOLO de ese dashboard
    $widgets = DB::table('dashboard_widgets')
        ->where('dashboard_id', $dashboard->id)
        ->orderBy('position_y')
        ->orderBy('position_x')
        ->get()
        ->map(function ($w) {

            $dataSource = json_decode($w->data_source ?? '{}', true) ?? [];

            // 🔥 fallback summary
            if (
                empty($dataSource['summary']) &&
                !empty($dataSource['sql_training_id'])
            ) {
                $summary = DB::table('sqltrainings')
                    ->where('id', $dataSource['sql_training_id'])
                    ->value('summary');

                if ($summary) {
                    $dataSource['summary'] = $summary;
                }
            }

            $w->data_source = array_merge([
                'type' => null,
                'rows' => [],
                'summary' => '',
            ], $dataSource);

            $w->colors  = json_decode($w->colors ?? '{}', true) ?? [];
            $w->options = json_decode($w->options ?? '{}', true) ?? [];

            return $w;
        });

    return response()->json([
        'dashboard' => [
            'id'    => $dashboard->id,
            'title' => $dashboard->title,
            'slug'  => $dashboard->slug,
        ],
        'widgets' => $widgets,
    ]);
}


public function refresh(int $dashboardId, int $widgetId)
{
    // 1️⃣ Buscar widget
    $widget = DB::table('dashboard_widgets')
        ->where('id', $widgetId)
        ->where('dashboard_id', $dashboardId)
        ->first();

    if (!$widget) {
        return response()->json([
            'error' => 'Widget no encontrado en este dashboard',
        ], 404);
    }

    // 2️⃣ Leer data_source
    $dataSource = json_decode($widget->data_source ?? '{}', true);

    if (
        empty($dataSource['sql_query']) &&
        empty($dataSource['sql_training_id'])
    ) {
        return response()->json([
            'error' => 'Este widget no tiene SQL para recalcular',
        ], 400);
    }

    // 3️⃣ Resolver SQL
    $query = $dataSource['sql_query'] ?? null;

    if (!$query && !empty($dataSource['sql_training_id'])) {
        $query = DB::table('sqltrainings')
            ->where('id', $dataSource['sql_training_id'])
            ->value('sql_validated');
    }

    if (!$query) {
        return response()->json([
            'error' => 'No se pudo resolver el SQL del widget',
        ], 400);
    }

    // 4️⃣ Ejecutar SQL (🔥 AQUÍ ESTÁ LA MAGIA)
    $rows = DB::select($query);

    $cleanRows = collect($rows)->map(function ($row) {
        $out = [];
        foreach ($row as $k => $v) {
            if (is_numeric($v)) $out[$k] = (float) $v;
            elseif (is_null($v)) $out[$k] = 0;
            elseif (is_bool($v)) $out[$k] = $v ? 1 : 0;
            elseif (is_string($v)) {
                $out[$k] = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $v));
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    })->values()->toArray();

    // 5️⃣ Reescribir rows
    $dataSource['rows'] = $cleanRows;

    DB::table('dashboard_widgets')
        ->where('id', $widgetId)
        ->update([
            'data_source' => json_encode($dataSource, JSON_UNESCAPED_UNICODE),
            'updated_at'  => now(),
        ]);

    return response()->json([
        'message' => '🔁 Widget recalculado correctamente',
        'rows'    => $cleanRows,
    ]);
}

public function storeFromTraining(Request $request)
{
    try {
        $validated = $request->validate([
            'training_id' => 'required|integer|exists:aitrainings,id',
            'chart_type'  => 'required|string',
        ]);

        // 1️⃣ Resolver dashboard activo
        $dashboard = Dashboard::where('is_default', 1)->first()
            ?? Dashboard::orderBy('id')->first();

        if (!$dashboard) {
            return response()->json([
                'error' => 'No existe un dashboard activo'
            ], 400);
        }

        // 2️⃣ Entrenamiento
        $training = DB::table('aitrainings')->find($validated['training_id']);
        if (!$training || !$training->sql_training_id) {
            return response()->json(['error' => 'Entrenamiento inválido'], 400);
        }

        // 3️⃣ SQL Training
        $sqlTraining = DB::table('sqltrainings')->find($training->sql_training_id);
        if (!$sqlTraining || $sqlTraining->test_status !== 'ok') {
            return response()->json(['error' => 'SQL no validada'], 400);
        }

        $query   = $sqlTraining->sql_validated ?? $sqlTraining->sql_generated;
        $summary = trim($sqlTraining->summary ?? '');

        if (!$query) {
            return response()->json(['error' => 'SQL vacía'], 400);
        }

        // 4️⃣ Ejecutar SQL
        $rows = DB::select($query);

        $cleanRows = collect($rows)->map(function ($row) {
            $out = [];
            foreach ($row as $k => $v) {
                if (is_numeric($v)) $out[$k] = (float) $v;
                elseif (is_null($v)) $out[$k] = 0;
                elseif (is_bool($v)) $out[$k] = $v ? 1 : 0;
                elseif (is_string($v)) {
                    $out[$k] = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $v));
                } else {
                    $out[$k] = $v;
                }
            }
            return $out;
        })->values()->toArray();

        // 5️⃣ Crear widget
        $widgetId = DB::table('dashboard_widgets')->insertGetId([
            'dashboard_id'   => $dashboard->id,
            'ai_training_id' => $training->id,
            'title'          => $training->prompt,
            'summary'        => $summary,
            'chart_type'     => $validated['chart_type'],
            'data_source'    => json_encode([
                'type'            => 'sql',
                'sql_training_id' => $training->sql_training_id,
                'sql_query'       => $query,
                'rows'            => $cleanRows,
            ], JSON_UNESCAPED_UNICODE),
            'colors' => json_encode([
                'primary'   => '#1E88E5',
                'secondary' => '#90CAF9',
            ]),
            'position_x' => 0,
            'position_y' => 0,
            'width'      => 4,
            'height'     => 3,
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        return response()->json([
            'message'   => '📊 Widget creado correctamente',
            'widget_id' => $widgetId,
        ]);

    } catch (\Throwable $e) {
        Log::error('💥 storeFromTraining', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'Error inesperado al crear el widget'
        ], 500);
    }
}

public function store(Request $request, int $dashboard)
{
    Log::info('🧱 [DashboardSection@store] HIT', [
        'dashboard_param' => $dashboard,
        'payload' => $request->all(),
    ]);

    // 1️⃣ Verificar dashboard
    $exists = DB::table('dashboards')->where('id', $dashboard)->exists();

    Log::info('🧱 [DashboardSection@store] Dashboard exists?', [
        'dashboard_id' => $dashboard,
        'exists' => $exists,
    ]);

    if (!$exists) {
        Log::warning('❌ Dashboard no válido', [
            'dashboard_id' => $dashboard,
        ]);

        return response()->json([
            'error' => 'Dashboard no válido'
        ], 404);
    }

    // 2️⃣ Validación
    try {
        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'position' => 'nullable|integer|min:0',
            'height'   => 'nullable|integer|min:1',
            'colors'   => 'nullable|array',
        ]);

        Log::info('✅ [DashboardSection@store] Payload validado', [
            'validated' => $validated,
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {

        Log::error('❌ [DashboardSection@store] VALIDATION FAILED', [
            'errors' => $e->errors(),
            'payload' => $request->all(),
        ]);

        // re-lanzar para que Laravel devuelva 422 normal
        throw $e;
    }

    // 3️⃣ Colores por defecto
    $defaultColors = [
        'bg'     => '#0f172a',
        'text'   => '#60a5fa',
        'border' => '#1e293b',
    ];

    // 4️⃣ Insert
    try {
        $id = DB::table('dashboard_sections')->insertGetId([
            'dashboard_id' => $dashboard,
            'title'        => $validated['title'],
            'position'     => $validated['position'] ?? 0,
            'height'       => $validated['height'] ?? 1,
            'colors'       => json_encode(
                $validated['colors'] ?? $defaultColors,
                JSON_UNESCAPED_UNICODE
            ),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        Log::info('✅ [DashboardSection@store] Sección creada', [
            'section_id' => $id,
            'dashboard_id' => $dashboard,
        ]);

        return response()->json([
            'message' => '✅ Sección creada correctamente',
            'section' => DB::table('dashboard_sections')->find($id),
        ], 201);

    } catch (\Throwable $e) {

        Log::error('💥 [DashboardSection@store] ERROR INSERTANDO', [
            'exception' => $e,
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'Error creando la sección'
        ], 500);
    }
}



public function update(Request $request, int $dashboard, int $widget)
{
    $row = DB::table('dashboard_widgets')
        ->where('id', $widget)
        ->where('dashboard_id', $dashboard)
        ->first();

    if (!$row) {
        return response()->json([
            'error' => 'Widget no encontrado en este dashboard.',
        ], 404);
    }

    $data = $request->validate([
        'title'      => 'nullable|string|max:255',
        'chart_type' => 'nullable|string',
        'colors'     => 'nullable',
        'position_x' => 'nullable|integer',
        'position_y' => 'nullable|integer',
        'width'      => 'nullable|integer',
        'height'     => 'nullable|integer',
    ]);

    if (isset($data['colors'])) {
        $existingColors = json_decode($row->colors, true) ?? [];
        $newColors = is_string($data['colors'])
            ? json_decode($data['colors'], true)
            : $data['colors'];

        $data['colors'] = json_encode(
            array_merge($existingColors, $newColors ?? []),
            JSON_UNESCAPED_UNICODE
        );
    }

    $data['updated_at'] = now();

    DB::table('dashboard_widgets')
        ->where('id', $widget)
        ->update($data);

    return response()->json([
        'message' => '✅ Widget actualizado correctamente',
    ]);
}



public function destroy(int $dashboardId, int $id)
{
    // 1️⃣ Verificar que el widget exista y pertenezca al dashboard
    $widget = DB::table('dashboard_widgets')
        ->where('id', $id)
        ->where('dashboard_id', $dashboardId)
        ->first();

    if (!$widget) {
        return response()->json([
            'error' => '❌ Widget no encontrado en este dashboard.',
        ], 404);
    }

    // 2️⃣ Si es un bloque heading, eliminar también sus hijos
    if ($widget->type === 'heading') {
        DB::table('dashboard_widgets')
            ->where('group_id', $id)
            ->where('dashboard_id', $dashboardId)
            ->delete();
    }

    // 3️⃣ Eliminar el widget
    DB::table('dashboard_widgets')
        ->where('id', $id)
        ->delete();

    return response()->json([
        'message' => '🗑️ Widget eliminado correctamente.',
    ]);
}

public function reorder(Request $request, int $dashboardId)
{
    $widgets = $request->input('widgets', []);

    if (!is_array($widgets)) {
        return response()->json(['error' => 'Formato inválido.'], 422);
    }

    foreach ($widgets as $w) {
        if (!isset($w['i'])) continue;

        /**
         * 🧱 SECCIÓN (ej: section-3)
         */
        if (str_starts_with($w['i'], 'section-')) {
            $sectionId = (int) str_replace('section-', '', $w['i']);

            DB::table('dashboard_sections')
                ->where('id', $sectionId)
                ->where('dashboard_id', $dashboardId)
                ->update([
                    'position'   => $w['y'] ?? 0,
                    'width'      => $w['w'] ?? 12,
                    'height'     => $w['h'] ?? 1,
                    'updated_at' => now(),
                ]);

            continue;
        }

        /**
         * 📊 WIDGET NORMAL
         */
        DB::table('dashboard_widgets')
            ->where('id', $w['i'])
            ->where('dashboard_id', $dashboardId)
            ->update([
                'position_x' => $w['x'] ?? 0,
                'position_y' => $w['y'] ?? 0,
                'width'      => $w['w'] ?? 4,
                'height'     => $w['h'] ?? 3,
                'updated_at' => now(),
            ]);
    }

    return response()->json([
        'message' => '✅ Reordenado correctamente',
    ], 200);
}


public function updateColor(Request $request, int $dashboardId, int $id)
{
    try {
        // 1️⃣ Validar payload
        $validated = $request->validate([
            'color' => 'required|string|max:20',
            'field' => 'nullable|string|in:primary,bg,text,border',
        ]);

        // 2️⃣ Buscar widget dentro del dashboard
        $widget = DB::table('dashboard_widgets')
            ->where('id', $id)
            ->where('dashboard_id', $dashboardId)
            ->first();

        if (!$widget) {
            return response()->json([
                'error' => 'Widget no encontrado en este dashboard.',
            ], 404);
        }

        // 3️⃣ Colores existentes (fallback)
        $existingColors = json_decode($widget->colors, true) ?? [
            'bg'      => '#1e293b',
            'text'    => '#e2e8f0',
            'border'  => '#334155',
            'primary' => '#1E88E5',
        ];

        // 4️⃣ Campo a actualizar
        $field = $validated['field'] ?? 'primary';
        $existingColors[$field] = $validated['color'];

        // 5️⃣ Guardar
        DB::table('dashboard_widgets')
            ->where('id', $id)
            ->update([
                'colors'     => json_encode($existingColors, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => "🎨 Color de '{$field}' actualizado correctamente.",
            'colors'  => $existingColors,
        ]);

    } catch (\Throwable $e) {
        Log::error('💥 Error actualizando color de widget', [
            'dashboard_id' => $dashboardId,
            'widget_id'    => $id,
            'error'        => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'Error al actualizar color.',
        ], 500);
    }
}

public function saveFilters(Request $request, int $dashboardId, int $id)
{
    try {
        Log::info('🧩 [saveFilters] Inicio', [
            'dashboard_id' => $dashboardId,
            'widget_id'    => $id,
            'payload'      => $request->all(),
        ]);

        // 1️⃣ Validar payload
        $validated = $request->validate([
            'filters' => 'required|array',
            'filters.activeLabels' => 'required|array',
            'filters.activeLabels.*' => 'string',
        ]);

        Log::info('✅ [saveFilters] Payload validado', [
            'validated' => $validated,
        ]);

        // 2️⃣ Buscar widget dentro del dashboard
        $widget = DB::table('dashboard_widgets')
            ->where('id', $id)
            ->where('dashboard_id', $dashboardId)
            ->first();

        if (!$widget) {
            Log::warning('⚠️ [saveFilters] Widget no encontrado en dashboard', [
                'dashboard_id' => $dashboardId,
                'widget_id'    => $id,
            ]);

            return response()->json([
                'error' => 'Widget no encontrado en este dashboard',
            ], 404);
        }

        Log::info('📦 [saveFilters] Widget encontrado', [
            'id'          => $widget->id,
            'options_raw' => $widget->options,
        ]);

        // 3️⃣ Leer OPTIONS actuales
        $existingOptions = [];
        if (!empty($widget->options)) {
            $existingOptions = json_decode($widget->options, true) ?? [];
        }

        Log::info('🧠 [saveFilters] Options decodificados', [
            'existingOptions' => $existingOptions,
        ]);

        // 4️⃣ Mezclar filtros UX (asegurar estructura)
        $existingOptions['filters'] = $existingOptions['filters'] ?? [];
        $existingOptions['filters']['activeLabels'] =
            $validated['filters']['activeLabels'];

        Log::info('🛠 [saveFilters] Options después de merge', [
            'mergedOptions' => $existingOptions,
        ]);

        // 5️⃣ Guardar en BD
        DB::table('dashboard_widgets')
            ->where('id', $id)
            ->update([
                'options'     => json_encode($existingOptions, JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);

        Log::info('💾 [saveFilters] Filtros guardados correctamente', [
            'widget_id' => $id,
        ]);

        return response()->json([
            'message' => '✅ Filtros guardados correctamente',
            'options' => $existingOptions,
        ]);

    } catch (\Throwable $e) {
        Log::error('💥 [saveFilters] Error guardando filtros del widget', [
            'dashboard_id' => $dashboardId,
            'widget_id'    => $id,
            'exception'    => $e,
            'message'      => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'No se pudieron guardar los filtros',
        ], 500);
    }
}




}
