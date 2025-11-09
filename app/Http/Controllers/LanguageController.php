<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\SemanticContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LanguageController extends Controller
{
    /**
     * 📄 Listado general (Inertia)
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $languages = Language::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('search_context', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        // 🔹 Listado de contextos semánticos
        $contexts = SemanticContext::select('id', 'role_name', 'search_context')
            ->orderBy('role_name')
            ->get();

        return Inertia::render('languages/Index', [
            'languages' => $languages->through(fn ($l) => [
                'id'             => $l->id,
                'name'           => $l->name,
                'slug'           => $l->slug,
                'search_context' => $l->search_context,
                'context_id'     => $l->context_id,
                'created_at'     => optional($l->created_at)->format('Y-m-d'),
            ]),
            'contexts' => $contexts, // 👈 Enviado a Inertia
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

        $languages = Language::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('search_context', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $formatted = $languages->through(fn ($l) => [
            'id'             => $l->id,
            'name'           => $l->name,
            'slug'           => $l->slug,
            'search_context' => $l->search_context,
            'context_id'     => $l->context_id,
            'created_at'     => optional($l->created_at)->format('Y-m-d'),
        ]);

        return response()->json($formatted);
    }

    /**
     * 🆕 Crear lenguaje
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'search_context' => 'nullable|string|max:255',
            'context_id'     => 'nullable|integer|exists:semantic_contexts,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $validated['slug'] = Str::slug($validated['name']);
            $language = Language::create($validated);

            return response()->json([
                'message'  => '✅ Lenguaje creado correctamente.',
                'language' => $language,
            ], 201);
        });
    }

    /**
     * ✏️ Actualizar lenguaje
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'search_context' => 'nullable|string|max:255',
            'context_id'     => 'nullable|integer|exists:semantic_contexts,id',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $language = Language::findOrFail($id);
            $language->update(array_merge($validated, [
                'slug' => Str::slug($validated['name']),
            ]));

            return response()->json([
                'message'  => '✅ Lenguaje actualizado correctamente.',
                'language' => $language,
            ]);
        });
    }

    /**
     * 🗑️ Eliminar lenguaje
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $language = Language::findOrFail($id);
            $language->delete();

            return response()->json(['message' => '🗑️ Lenguaje eliminado correctamente.']);
        });
    }
}
