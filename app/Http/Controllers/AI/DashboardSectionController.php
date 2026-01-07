<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DashboardSection;
use Illuminate\Support\Facades\Log;

class DashboardSectionController extends Controller
{
    /**
     * 📋 Listar secciones de un dashboard
     */
    public function index($dashboardId)
    {
        $sections = DashboardSection::where('dashboard_id', $dashboardId)
            ->orderBy('position')
            ->get();

        return response()->json(['sections' => $sections]);
    }

 public function store(Request $request, int $dashboard)
{
    Log::info('🧱 store DashboardSection', [
        'dashboard' => $dashboard,
        'payload' => $request->all(),
    ]);

    $validated = $request->validate([
        'title'    => 'required|string|max:255',
        'position' => 'nullable|integer|min:0',
        'height'   => 'nullable|integer|min:1',
        'colors'   => 'nullable|array',
    ]);

    $defaultColors = [
        'bg'     => '#0f172a',
        'text'   => '#60a5fa',
        'border' => '#1e293b',
    ];

    $section = DashboardSection::create([
        'dashboard_id' => $dashboard, // ✅ DESDE URL
        'title'        => $validated['title'],
        'position'     => $validated['position'] ?? 0,
        'height'       => $validated['height'] ?? 1,
        'colors'       => $validated['colors'] ?? $defaultColors,
    ]);

    Log::info('✅ sección creada', ['id' => $section->id]);

    return response()->json([
        'section' => $section,
    ], 201);
}
    /**
     * ✏️ Actualizar una sección existente (incluye colores)
     */
public function update(Request $request, int $dashboard, int $id)
{
    Log::info('✏️ update DashboardSection', [
        'dashboard' => $dashboard,
        'section' => $id,
        'payload' => $request->all(),
    ]);

    $section = DashboardSection::where('dashboard_id', $dashboard)
        ->where('id', $id)
        ->firstOrFail();

    $validated = $request->validate([
        'title'    => 'nullable|string|max:255',
        'position' => 'nullable|integer|min:0',
        'height'   => 'nullable|integer|min:1',
        'colors'   => 'nullable|array',
    ]);

    $section->update($validated);

    return response()->json([
        'section' => $section,
    ]);
}

    /**
     * 🗑️ Eliminar una sección (conservando widgets)
     */
 public function destroy(int $dashboard, int $id)
{
    $section = DashboardSection::where('id', $id)
        ->where('dashboard_id', $dashboard)
        ->firstOrFail();

    $section->delete();

    return response()->json([
        'message' => '🗑️ Sección eliminada correctamente',
    ]);
}

}
