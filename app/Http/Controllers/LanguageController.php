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
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Listado de contextos
        $contexts = SemanticContext::select('id',  'search_context')
            ->get();

        return Inertia::render('languages/Index', [
            'languages' => $languages->through(fn ($l) => [
                'id'         => $l->id,
                'name'       => $l->name,
                'slug'       => $l->slug,
                'context_id' => $l->context_id,
                'enabled'    => $l->enabled,
                'created_at' => optional($l->created_at)->format('Y-m-d'),
            ]),
            'contexts' => $contexts,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * 📄 API JSON
     */
    public function fetchPaginated(Request $request)
    {
        $search = $request->get('search');

        $languages = Language::query()
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $formatted = $languages->through(fn ($l) => [
            'id'         => $l->id,
            'name'       => $l->name,
            'slug'       => $l->slug,
            'context_id' => $l->context_id,
            'enabled'    => $l->enabled,
            'created_at' => optional($l->created_at)->format('Y-m-d'),
        ]);

        return response()->json($formatted);
    }

    /**
     * 🆕 Crear lenguaje
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'context_id' => 'nullable|integer|exists:semantic_contexts,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $validated['slug'] = Str::slug($validated['name']);
            $validated['enabled'] = 1;

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
            'name'       => 'required|string|max:255',
            'context_id' => 'nullable|integer|exists:semantic_contexts,id',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $language = Language::findOrFail($id);

            $language->update([
                'name'       => $validated['name'],
                'slug'       => Str::slug($validated['name']),
                'context_id' => $validated['context_id'] ?? null,
            ]);

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

    /**
     * 🔄 Activar / desactivar
     */
    public function toggle($id, Request $request)
    {
        $lang = Language::findOrFail($id);
        $lang->enabled = $request->enabled;
        $lang->save();

        return response()->json(['success' => true]);
    }
}
