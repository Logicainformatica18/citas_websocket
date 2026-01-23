<?php

namespace App\Http\Controllers;

use App\Models\TechPosition;
use App\Models\Career;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class TechPositionController extends Controller
{
    /**
     * 📄 LISTADO GENERAL
     */
    public function index(Request $request)
    {
        $positions = TechPosition::with(['careers'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        $careers = Career::where('active', 1)
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'positions' => $positions,
                'careers'   => $careers,
            ]);
        }

        return Inertia::render('techpositions/index', [
            'positions' => $positions,
            'careers'   => $careers,
        ]);
    }

    /**
     * 📌 LISTA SIMPLE PARA SELECTS
     */
    public function listAll()
    {
        $positions = TechPosition::select('id', 'position_name')
            ->where('active', 1)
            ->orderBy('position_name')
            ->get();

        return response()->json(['positions' => $positions]);
    }

    /**
     * 🆕 CREAR ROL TECNOLÓGICO
     */
    public function store(Request $request)
    {
        $request->validate([
            'position_name'    => 'required|string|max:255',
            'position_name_en' => 'nullable|string|max:255',
            'category'         => 'nullable|string|max:255',
            'subcategory'      => 'nullable|string|max:255',
            'description'      => 'nullable|string',

            'careers'          => 'array',
            'careers.*'        => 'integer|exists:careers,id',
        ]);

        $position = TechPosition::create([
            'position_name'    => $request->position_name,
            'position_name_en' => $request->position_name_en,
            'position_slug'    => Str::slug($request->position_name),
            'category'         => $request->category,
            'subcategory'      => $request->subcategory,
            'description'      => $request->description,
            'active'           => true,
        ]);

        // 🔗 Asociar carreras (regla académica)
        if ($request->filled('careers')) {
            $position->careers()->sync($request->careers);
        }

        return response()->json([
            'message'  => '✅ Rol creado correctamente',
            'position' => $position->load('careers'),
        ]);
    }

    /**
     * 📌 VER ROL + CARRERAS ASOCIADAS / NO ASOCIADAS
     */
    public function show($id)
    {
        $position = TechPosition::with('careers')->findOrFail($id);

        $selectedCareerIds = $position->careers->pluck('id')->toArray();

        $careers = Career::where('active', 1)
            ->get()
            ->sortByDesc(fn ($c) => in_array($c->id, $selectedCareerIds))
            ->values();

        return response()->json([
            'position' => $position,
            'careers'  => $careers,
        ]);
    }

    /**
     * ✏ ACTUALIZAR ROL
     */
    public function update(Request $request, $id)
    {
        $position = TechPosition::findOrFail($id);

        $request->validate([
            'position_name'    => 'required|string|max:255',
            'position_name_en' => 'nullable|string|max:255',
            'category'         => 'nullable|string|max:255',
            'subcategory'      => 'nullable|string|max:255',
            'description'      => 'nullable|string',

            'careers'          => 'array',
            'careers.*'        => 'integer|exists:careers,id',
        ]);

        $position->update([
            'position_name'    => $request->position_name,
            'position_name_en' => $request->position_name_en,
            'position_slug'    => Str::slug($request->position_name),
            'category'         => $request->category,
            'subcategory'      => $request->subcategory,
            'description'      => $request->description,
        ]);

        // 🔗 Sincronizar carreras
        $position->careers()->sync($request->careers ?? []);

        return response()->json([
            'message'  => '✅ Rol actualizado correctamente',
            'position' => $position->load('careers'),
        ]);
    }

    /**
     * ❌ ELIMINAR ROL
     */
    public function destroy($id)
    {
        $position = TechPosition::findOrFail($id);
        $position->delete();

        return response()->json([
            'message' => '🗑 Rol eliminado correctamente',
        ]);
    }

    /**
     * 🔄 PAGINADO CON BÚSQUEDA
     */
    public function fetchPaginated(Request $request)
    {
        $search = $request->get('search');

        $positions = TechPosition::with('careers')
            ->when($search, function ($query, $search) {
                $query->where('position_name', 'like', "%{$search}%")
                      ->orWhere('position_name_en', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'data'            => $positions->items(),
            'current_page'    => $positions->currentPage(),
            'last_page'       => $positions->lastPage(),
            'next_page_url'   => $positions->nextPageUrl(),
            'prev_page_url'   => $positions->previousPageUrl(),
        ]);
    }
}
