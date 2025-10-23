<?php

namespace App\Http\Controllers;

use App\Models\ReportQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ReportQueryController extends Controller
{
    /**
     * 📋 Listado de preguntas (ReportQueries)
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $category = $request->get('category');

        $queries = ReportQuery::query()
            ->when($search, fn($q) =>
                $q->where('question', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
            )
            ->when($category, fn($q) => $q->where('category', $category))
            ->orderBy('category')
            ->orderByDesc('id')
            ->paginate(10);

        $categories = ReportQuery::select('category')->distinct()->pluck('category');

        if ($request->wantsJson()) {
            return response()->json([
                'queries' => $queries,
                'categories' => $categories
            ]);
        }

        return Inertia::render('admin/report-queries/index', [
            'queries' => $queries,
            'categories' => $categories
        ]);
    }

    /**
     * 🧩 Crear una nueva pregunta/report
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'        => 'required|string|max:150',
            'question'        => 'required|string',
            'interpreter'     => 'required|string',
            'component'       => 'nullable|string|max:150',
            'description'     => 'nullable|string',
            'tags'            => 'nullable|array',
            'is_active'       => 'boolean',
            'has_ai_response' => 'boolean',
        ]);

        $validated['tags'] = $validated['tags'] ?? [];
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['has_ai_response'] = $validated['has_ai_response'] ?? true;

        $query = ReportQuery::create($validated);

        return response()->json([
            'message' => '✅ Reporte creado correctamente',
            'query'   => $query
        ]);
    }

    /**
     * 📄 Mostrar un reporte específico
     */
    public function show($id)
    {
        $query = ReportQuery::findOrFail($id);
        return response()->json(['query' => $query]);
    }

    /**
     * ✏️ Actualizar un registro existente
     */
    public function update(Request $request, $id)
    {
        $query = ReportQuery::findOrFail($id);

        $validated = $request->validate([
            'category'        => 'required|string|max:150',
            'question'        => 'required|string',
            'interpreter'     => 'required|string',
            'component'       => 'nullable|string|max:150',
            'description'     => 'nullable|string',
            'tags'            => 'nullable|array',
            'is_active'       => 'boolean',
            'has_ai_response' => 'boolean',
        ]);

        $query->update($validated);

        return response()->json([
            'message' => '✅ Reporte actualizado correctamente',
            'query'   => $query
        ]);
    }

    /**
     * ❌ Eliminar un registro
     */
    public function destroy($id)
    {
        $query = ReportQuery::findOrFail($id);
        $query->delete();

        return response()->json(['message' => '🗑️ Reporte eliminado correctamente']);
    }

    /**
     * 🔁 Alternar estado activo/inactivo
     */
    public function toggleActive($id)
    {
        $query = ReportQuery::findOrFail($id);
        $query->is_active = !$query->is_active;
        $query->save();

        return response()->json([
            'message' => $query->is_active
                ? '✅ Reporte activado'
                : '🚫 Reporte desactivado',
            'is_active' => $query->is_active
        ]);
    }

    /**
     * 🤖 Alternar uso de IA (activar/desactivar explicación IA)
     */
    public function toggleAI($id)
    {
        $query = ReportQuery::findOrFail($id);
        $query->has_ai_response = !$query->has_ai_response;
        $query->save();

        return response()->json([
            'message' => $query->has_ai_response
                ? '🤖 Explicación IA activada'
                : '💤 Explicación IA desactivada',
            'has_ai_response' => $query->has_ai_response
        ]);
    }

    /**
     * 🧬 Duplicar una pregunta (para edición rápida)
     */
    public function duplicate($id)
    {
        $original = ReportQuery::findOrFail($id);
        $copy = $original->replicate();
        $copy->question = '[Copia] ' . $original->question;
        $copy->is_active = false;
        $copy->save();

        return response()->json([
            'message' => '📋 Copia creada correctamente',
            'query'   => $copy
        ]);
    }
}
