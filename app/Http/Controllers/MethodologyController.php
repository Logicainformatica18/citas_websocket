<?php

namespace App\Http\Controllers;

use App\Models\Methodology;
use App\Models\SemanticContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                  ->orWhereHas('context', fn($c) =>
                      $c->where('search_context', 'like', "%{$search}%")
                  )
            )
            ->with('context:id,search_context')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Listado de contextos
        $contexts = SemanticContext::select('id', 'search_context')->get();

        return Inertia::render('methodologies/Index', [
            'methodologies' => $methodologies->through(fn ($m) => [
                'id'         => $m->id,
                'name'       => $m->name,
                'slug'       => $m->slug,
                'context_id' => $m->context_id,
                'enabled'    => $m->enabled,
                'context'    => $m->context?->only(['id', 'search_context']),
                'created_at' => optional($m->created_at)->format('Y-m-d'),
            ]),
            'contexts' => $contexts,
            'filters'  => ['search' => $search],
        ]);
    }

    /**
     * 🔄 API JSON — paginación AJAX
     */
    public function fetchPaginated(Request $request)
    {
        $search = $request->get('search');

        $methodologies = Methodology::query()
            ->when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('context', fn($c) =>
                      $c->where('search_context', 'like', "%{$search}%")
                  )
            )
            ->with('context:id,search_context')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $formatted = $methodologies->through(fn ($m) => [
            'id'         => $m->id,
            'name'       => $m->name,
            'slug'       => $m->slug,
            'context_id' => $m->context_id,
            'enabled'    => $m->enabled,
            'context'    => $m->context?->only(['id', 'search_context']),
            'created_at' => optional($m->created_at)->format('Y-m-d'),
        ]);

        return response()->json($formatted);
    }

    /**
     * 🆕 Crear metodología
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'context_id' => 'nullable|integer|exists:semantic_contexts,id',
        ]);

        return DB::transaction(function () use ($validated) {

            $methodology = Methodology::create([
                'name'       => $validated['name'],
                'slug'       => Str::slug($validated['name']),
                'context_id' => $validated['context_id'] ?? null,
                'enabled'    => 1,
            ]);

            return response()->json([
                'message'     => 'Creado correctamente',
                'methodology' => $methodology,
            ], 201);
        });
    }

    /**
     * ✏️ Actualizar metodología
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'context_id' => 'nullable|integer|exists:semantic_contexts,id',
        ]);

        return DB::transaction(function () use ($validated, $id) {

            $methodology = Methodology::findOrFail($id);

            $methodology->update([
                'name'       => $validated['name'],
                'slug'       => Str::slug($validated['name']),
                'context_id' => $validated['context_id'] ?? null,
            ]);

            return response()->json([
                'message'     => 'Actualizado correctamente',
                'methodology' => $methodology,
            ]);
        });
    }

    /**
     * 🗑️ Eliminar metodología
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $methodology = Methodology::findOrFail($id);
            $methodology->delete();

            return response()->json(['message' => 'Eliminado correctamente']);
        });
    }

    /**
     * 🚦 Activar / Desactivar (switch)
     */
    public function toggle($id, Request $request)
    {
        $methodology = Methodology::findOrFail($id);

        $methodology->enabled = $request->enabled;
        $methodology->save();

        return response()->json(['success' => true]);
    }
}
