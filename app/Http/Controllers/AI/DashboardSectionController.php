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

    /**
     * ➕ Crear una nueva sección (bloque de título)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'dashboard_id' => 'required|integer|exists:dashboards,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'position' => 'nullable|integer',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
        ]);

        $section = DashboardSection::create([
            ...$validated,
            'position' => $validated['position'] ?? 0,
            'width' => $validated['width'] ?? 12,
            'height' => $validated['height'] ?? 1,
        ]);

        Log::info('🧱 Nueva sección creada', ['section_id' => $section->id]);

        return response()->json([
            'message' => '✅ Sección creada correctamente.',
            'section' => $section,
        ]);
    }

    /**
     * ✏️ Actualizar una sección existente
     */
    public function update(Request $request, $id)
    {
        $section = DashboardSection::findOrFail($id);

        $section->update($request->only([
            'title', 'description', 'position', 'width', 'height'
        ]));

        return response()->json([
            'message' => '🧩 Sección actualizada correctamente.',
            'section' => $section,
        ]);
    }

    /**
     * 🗑️ Eliminar una sección
     */
  public function destroy($id)
{
    $section = DashboardSection::findOrFail($id);

    $section->delete();

    return response()->json([
        'message' => '🗑️ Sección eliminada correctamente (widgets conservados).'
    ]);
}

}
