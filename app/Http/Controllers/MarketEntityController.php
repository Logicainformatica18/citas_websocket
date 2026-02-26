<?php

namespace App\Http\Controllers;

use App\Models\MarketEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MarketEntityController extends Controller
{
    /**
     * 📄 Listado principal (Inertia)
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $type   = $request->get('entity_type');

        $entities = MarketEntity::query()
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->when($type, fn ($q) =>
                $q->where('entity_type', $type)
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('market-entities/Index', [
            'entities' => $entities->through(fn ($e) => [
                'id'          => $e->id,
                'name'        => $e->name,
                'slug'        => $e->slug,
                'entity_type' => $e->entity_type,
                'origin'      => $e->origin,
                'category'    => $e->category,
                'vendor'      => $e->vendor,
                'level'       => $e->level,
                'has_isil'    => $e->has_isil,
                'has_trend'   => $e->has_trend,
            ]),
            'filters' => [
                'search'      => $search,
                'entity_type' => $type,
            ],
        ]);
    }

    /**
     * 📄 API JSON para paginación dinámica
     */
    public function fetchPaginated(Request $request)
    {
        $search = $request->get('search');
        $type   = $request->get('entity_type');

        $entities = MarketEntity::query()
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->when($type, fn ($q) =>
                $q->where('entity_type', $type)
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return response()->json(
            $entities->through(fn ($e) => [
                'id'          => $e->id,
                'name'        => $e->name,
                'slug'        => $e->slug,
                'entity_type' => $e->entity_type,
                'origin'      => $e->origin,
                'category'    => $e->category,
                'vendor'      => $e->vendor,
                'level'       => $e->level,
                'has_isil'    => $e->has_isil,
                'has_trend'   => $e->has_trend,
            ])
        );
    }

    /**
     * 🆕 Crear entidad
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'entity_type' => 'required|string|max:100',
            'origin'      => 'nullable|string|max:100',
            'category'    => 'nullable|string|max:100',
            'vendor'      => 'nullable|string|max:100',
            'level'       => 'nullable|string|max:100',
            'has_isil'    => 'nullable|boolean',
            'has_trend'   => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated) {

            $validated['slug'] = Str::slug($validated['name']);

            $entity = MarketEntity::create($validated);

            return response()->json([
                'message' => '✅ Entidad creada correctamente.',
                'entity'  => $entity,
            ], 201);
        });
    }

    /**
     * ✏️ Actualizar entidad
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'entity_type' => 'required|string|max:100',
            'origin'      => 'nullable|string|max:100',
            'category'    => 'nullable|string|max:100',
            'vendor'      => 'nullable|string|max:100',
            'level'       => 'nullable|string|max:100',
            'has_isil'    => 'nullable|boolean',
            'has_trend'   => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $id) {

            $entity = MarketEntity::findOrFail($id);

            $validated['slug'] = Str::slug($validated['name']);

            $entity->update($validated);

            return response()->json([
                'message' => '✅ Entidad actualizada correctamente.',
                'entity'  => $entity,
            ]);
        });
    }

    /**
     * 🗑️ Eliminar entidad
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {

            $entity = MarketEntity::findOrFail($id);

            // Seguridad opcional: no eliminar si tiene trends
            if ($entity->entityTrends()->exists()) {
                return response()->json([
                    'message' => '⚠️ No se puede eliminar. Tiene trends asociados.'
                ], 422);
            }

            $entity->delete();

            return response()->json([
                'message' => '🗑️ Entidad eliminada correctamente.'
            ]);
        });
    }

    /**
     * 🔄 Toggle has_trend
     */
    public function toggleTrend($id)
    {
        $entity = MarketEntity::findOrFail($id);

        $entity->has_trend = !$entity->has_trend;
        $entity->save();

        return response()->json([
            'success'   => true,
            'has_trend' => $entity->has_trend,
        ]);
    }
}
