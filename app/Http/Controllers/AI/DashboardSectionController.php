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
            'colors' => 'nullable|array', // 👈 ahora acepta colores personalizados
        ]);

        // 🎨 Colores por defecto si no se envían
        $defaultColors = [
            'bg' => '#0f172a',     // Fondo oscuro
            'text' => '#60a5fa',   // Azul claro
            'border' => '#1e293b', // Borde gris azulado
        ];

        $section = DashboardSection::create([
            'dashboard_id' => $validated['dashboard_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'position' => $validated['position'] ?? 0,
            'width' => $validated['width'] ?? 12,
            'height' => $validated['height'] ?? 1,
            'colors' => json_encode($validated['colors'] ?? $defaultColors, JSON_UNESCAPED_UNICODE),
        ]);

        Log::info('🧱 Nueva sección creada', [
            'section_id' => $section->id,
            'colors' => $section->colors
        ]);

        return response()->json([
            'message' => '✅ Sección creada correctamente.',
            'section' => $section,
        ]);
    }

    /**
     * ✏️ Actualizar una sección existente (incluye colores)
     */
    public function update(Request $request, $id)
    {
        $section = DashboardSection::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'position' => 'nullable|integer',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'colors' => 'nullable|array', // 👈 puede venir desde el front
        ]);

        if (isset($validated['colors'])) {
            $validated['colors'] = json_encode($validated['colors'], JSON_UNESCAPED_UNICODE);
        }

        $section->update($validated);

        Log::info('🎨 Sección actualizada', [
            'section_id' => $section->id,
            'updated_fields' => $validated,
        ]);

        return response()->json([
            'message' => '🧩 Sección actualizada correctamente.',
            'section' => $section,
        ]);
    }

    /**
     * 🗑️ Eliminar una sección (conservando widgets)
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
