<?php

namespace App\Http\Controllers;

use App\Models\Methodology;
use App\Models\SemanticContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MethodologyController extends Controller
{
    /**
     * 📄 Listado principal (Inertia)
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $methodologies = Methodology::query()
            ->when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('context', fn($c) => $c->where('search_context', 'like', "%{$search}%"))
            )
            ->with('context:id,role_name,search_context')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $contexts = SemanticContext::select('id', 'role_name', 'search_context')->orderBy('role_name')->get();

        return Inertia::render('methodologies/Index', [
            'methodologies' => $methodologies,
            'contexts' => $contexts,
        ]);
    }

    /**
     * 🔄 Paginación AJAX
     */
    public function fetchPaginated(Request $request)
    {
        $search = $request->get('search');

        $methodologies = Methodology::query()
            ->when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('context', fn($c) => $c->where('search_context', 'like', "%{$search}%"))
            )
            ->with('context:id,role_name,search_context')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return response()->json(['methodologies' => $methodologies]);
    }

    /**
     * 🆕 Crear
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'context_id' => 'nullable|exists:semantic_contexts,id',
        ]);

        Methodology::create($validated);

        return response()->json(['message' => 'Creado correctamente']);
    }

    /**
     * ✏️ Actualizar
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'context_id' => 'nullable|exists:semantic_contexts,id',
        ]);

        $methodology = Methodology::findOrFail($id);
        $methodology->update($validated);

        return response()->json(['message' => 'Actualizado correctamente']);
    }

    /**
     * 🗑️ Eliminar
     */
    public function destroy($id)
    {
        Methodology::findOrFail($id)->delete();

        return response()->json(['message' => 'Eliminado correctamente']);
    }
}
