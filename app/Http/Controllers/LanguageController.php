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
    /**
     * Ya no borra físicamente: borrar la fila de `languages` eliminaba en cascada sus
     * ofertas de language_job, pero dejaba la entidad en market_entities, y los
     * scrapers la volvían a crear (caso IDC). Ahora se desactiva el lenguaje y su
     * entidad (market_entities.is_active = 0): desaparece del ranking, de la
     * evolución, del export y de los scrapers, y se conserva el historial.
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $language = Language::findOrFail($id);
            $this->setActive($language, false, 'Eliminado desde la administración de lenguajes');

            return response()->json(['message' => '🗑️ Lenguaje eliminado (desactivado) correctamente.']);
        });
    }

    /**
     * 🔄 Activar / desactivar (también la entidad de market_entities vinculada)
     */
    public function toggle($id, Request $request)
    {
        $lang = Language::findOrFail($id);

        DB::transaction(fn () => $this->setActive(
            $lang,
            $request->boolean('enabled'),
            'Desactivado desde la administración de lenguajes'
        ));

        return response()->json(['success' => true]);
    }

    private function setActive(Language $language, bool $active, string $reason): void
    {
        $language->enabled = $active;
        $language->save();

        $entity = $language->marketEntity;

        if ($entity) {
            $active ? $entity->activate() : $entity->deactivate($reason);
        }
    }
}
