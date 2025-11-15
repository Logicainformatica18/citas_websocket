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
     * 📄 Listado general (Inertia)
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $technologies = Technology::query()
            ->with(['category:id,name', 'context:id,role_name'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        // 🔹 Listado de categorías y contextos
        $categories = TechnologyCategory::select('id', 'name')->orderBy('name')->get();
        $contexts   = SemanticContext::select('id', 'role_name', 'search_context')
            ->orderBy('role_name')
            ->get();

        return Inertia::render('technologies/Index', [
            'technologies' => $technologies->through(fn ($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'slug'        => $t->slug ?? Str::slug($t->name),
                'category'    => optional($t->category)->name,
                'context'     => optional($t->context)->role_name,
                'category_id' => $t->category_id,
                'context_id'  => $t->context_id,
                'enabled'     => $t->enabled,         // 👈 AGREGADO
                'created_at'  => optional($t->created_at)->format('Y-m-d'),
            ]),
            'categories' => $categories,
            'contexts'   => $contexts,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * 📄 API JSON (para DataTables o AJAX)
     */
    public function fetchPaginated(Request $request)
    {
        $search = $request->get('search');

        $technologies = Technology::query()
            ->with(['category:id,name', 'context:id,role_name'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $formatted = $technologies->through(fn ($t) => [
            'id'          => $t->id,
            'name'        => $t->name,
            'slug'        => $t->slug ?? Str::slug($t->name),
            'category'    => optional($t->category)->name,
            'context'     => optional($t->context)->role_name,
            'category_id' => $t->category_id,
            'context_id'  => $t->context_id,
            'enabled'     => $t->enabled,            // 👈 AGREGADO
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
            'enabled'     => 'nullable|boolean',     // 👈 AGREGADO
        ]);

        return DB::transaction(function () use ($validated) {
            $validated['slug'] = Str::slug($validated['name']);
            $technology = Technology::create($validated);

            return response()->json([
                'message'    => '✅ Tecnología creada correctamente.',
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
            'enabled'     => 'nullable|boolean',      // 👈 AGREGADO
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $technology = Technology::findOrFail($id);
            $technology->update(array_merge($validated, [
                'slug' => Str::slug($validated['name']),
            ]));

            return response()->json([
                'message'    => '✅ Tecnología actualizada correctamente.',
                'technology' => $technology,
            ]);
        });
    }
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
     * 🗑️ Eliminar tecnología
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $technology = Technology::findOrFail($id);
            $technology->delete();

            return response()->json(['message' => '🗑️ Tecnología eliminada correctamente.']);
        });
    }
}
