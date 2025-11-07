<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LanguageController extends Controller
{
    /**
     * 📄 Listado general (Inertia)
     */
    public function index()
    {
        $languages = Language::orderBy('id', 'desc')->paginate(10);

        return Inertia::render('languages/Index', [
            'languages' => $languages->through(fn ($l) => [
                'id'         => $l->id,
                'name'       => $l->name,
                'slug'       => $l->slug,
                'context_id' => $l->context_id,
                'created_at' => optional($l->created_at)->format('Y-m-d'),
            ]),
        ]);
    }

    /**
     * 📄 API JSON (para DataTables o AJAX)
     */
    public function fetchPaginated()
    {
        $languages = Language::orderBy('id', 'desc')->paginate(10);

        $formatted = $languages->through(fn ($l) => [
            'id'         => $l->id,
            'name'       => $l->name,
            'slug'       => $l->slug,
            'context_id' => $l->context_id,
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
