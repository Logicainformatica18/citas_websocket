<?php

namespace App\Http\Controllers;

use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Models\SemanticContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TechnologyController extends Controller
{
    /**
     * 📄 Listado principal (Inertia)
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $technologies = Technology::query()
            ->with(['category:id,name', 'context:id,search_context'])
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        // 🔹 Listado de categorías (para combos)
        $categories = TechnologyCategory::select('id', 'name')
            ->orderBy('name')
            ->get();

        // 🔹 Contextos (opcional)
        $contexts = SemanticContext::select('id', 'search_context')->get();

        return Inertia::render('technologies/Index', [
            'technologies' => $technologies->through(fn ($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'slug'        => $t->slug,
                'category'    => $t->category?->name,
                'category_id' => $t->category_id,
                'context'     => $t->context?->search_context,
                'context_id'  => $t->context_id,
                'enabled'     => $t->enabled,
                'created_at'  => optional($t->created_at)->format('Y-m-d'),
            ]),
            'categories' => $categories,
            'contexts'   => $contexts,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * 📄 API JSON (AJAX)
     */
    public function fetchPaginated(Request $request)
    {
        $search = $request->get('search');

        $technologies = Technology::query()
            ->with(['category:id,name', 'context:id,search_context'])
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        $formatted = $technologies->through(fn ($t) => [
            'id'          => $t->id,
            'name'        => $t->name,
            'slug'        => $t->slug,
            'category'    => $t->category?->name,
            'category_id' => $t->category_id,
            'context'     => $t->context?->search_context,
            'context_id'  => $t->context_id,
            'enabled'     => $t->enabled,
            'created_at'  => optional($t->created_at)->format('Y-m-d'),
        ]);

        return response()->json($formatted);
    }

    /**
     * 🆕 Crear tecnología
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:technology_categories,id',
            'context_id'  => 'nullable|integer|exists:semantic_contexts,id',
            'enabled'     => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated) {
            $validated['slug'] = Str::slug($validated['name']);
            $validated['enabled'] = $validated['enabled'] ?? 1;

            $technology = Technology::create($validated);

            return response()->json([
                'message'    => 'Tecnología creada correctamente.',
                'technology' => $technology,
            ], 201);
        });
    }

    /**
     * ✏️ Actualizar tecnología
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:technology_categories,id',
            'context_id'  => 'nullable|integer|exists:semantic_contexts,id',
            'enabled'     => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $technology = Technology::findOrFail($id);

            $technology->update([
                'name'        => $validated['name'],
                'slug'        => Str::slug($validated['name']),
                'category_id' => $validated['category_id'] ?? null,
                'context_id'  => $validated['context_id'] ?? null,
                'enabled'     => $validated['enabled'] ?? $technology->enabled,
            ]);

            return response()->json([
                'message'    => 'Tecnología actualizada correctamente.',
                'technology' => $technology,
            ]);
        });
    }

    /**
     * 🚦 Activar / Desactivar
     */
    public function toggle($id)
    {
        $tech = Technology::findOrFail($id);
        $tech->enabled = !$tech->enabled;
        $tech->save();

        return response()->json([
            'message' => 'Estado actualizado',
            'enabled' => $tech->enabled,
        ]);
    }

    /**
     * 🗑️ Eliminar
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $technology = Technology::findOrFail($id);
            $technology->delete();

            return response()->json([
                'message' => 'Tecnología eliminada correctamente.',
            ]);
        });
    }
}
