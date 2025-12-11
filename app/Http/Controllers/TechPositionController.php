<?php

namespace App\Http\Controllers;

use App\Models\TechPosition;
use App\Models\Language;
use App\Models\Technology;
use App\Models\Competency;
use App\Models\Methodology;
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
        $positions = TechPosition::with(['languages','technologies','competencies','methodologies'])
            ->orderBy('id','desc')
            ->paginate(10);

        $languages = Language::all();
        $technologies = Technology::all();
        $competencies = Competency::all();
        $methodologies = Methodology::all();

        if ($request->wantsJson()) {
            return response()->json([
                'positions'      => $positions,
                'languages'      => $languages,
                'technologies'   => $technologies,
                'competencies'   => $competencies,
                'methodologies'  => $methodologies,
            ]);
        }

        return Inertia::render('techpositions/index', [
            'positions'     => $positions,
            'languages'     => $languages,
            'technologies'  => $technologies,
            'competencies'  => $competencies,
            'methodologies' => $methodologies,
        ]);
    }

    /**
     * 📌 LISTA SIMPLE PARA SELECTS
     */
    public function listAll()
    {
        $positions = TechPosition::select('id','position_name')
            ->orderBy('position_name','asc')
            ->get();

        return response()->json(['positions' => $positions]);
    }


    /**
     * 🆕 CREAR ROL TECNOLÓGICO
     */
    public function store(Request $request)
    {
        $request->validate([
            'position_name'   => 'required|string|max:255',
            'position_name_en'=> 'nullable|string|max:255',
            'category'        => 'nullable|string|max:255',
            'subcategory'     => 'nullable|string|max:255',

            'languages'       => 'array',
            'languages.*'     => 'integer|exists:languages,id',

            'technologies'    => 'array',
            'technologies.*'  => 'integer|exists:technologies,id',

            'competencies'    => 'array',
            'competencies.*'  => 'integer|exists:competencies,id',

            'methodologies'   => 'array',
            'methodologies.*' => 'integer|exists:methodologies,id',
        ]);

        $position = TechPosition::create([
            'position_name'    => $request->position_name,
            'position_name_en' => $request->position_name_en,
            'position_slug'    => Str::slug($request->position_name),
            'category'         => $request->category,
            'subcategory'      => $request->subcategory,
        ]);

        // 🔗 Sincronizar relaciones
        $position->languages()->sync($request->languages ?? []);
        $position->technologies()->sync($request->technologies ?? []);
        $position->competencies()->sync($request->competencies ?? []);
        $position->methodologies()->sync($request->methodologies ?? []);

        return response()->json([
            'message' => '✅ Rol tecnológico creado',
            'position'=> $position->load(['languages','technologies','competencies','methodologies']),
        ]);
    }


    /**
     * 📌 Traer un rol + elementos asociados y NO asociados (ordenados)
     */
    public function show($id)
    {
        $position = TechPosition::with(['languages','technologies','competencies','methodologies'])
            ->findOrFail($id);

        // IDs vinculados
        $selectedLangs = $position->languages->pluck('id')->toArray();
        $selectedTechs = $position->technologies->pluck('id')->toArray();
        $selectedComps = $position->competencies->pluck('id')->toArray();
        $selectedMeths = $position->methodologies->pluck('id')->toArray();

        // Ordenar: primero los seleccionados
        $languages = Language::all()
            ->sortByDesc(fn($x) => in_array($x->id, $selectedLangs))
            ->values();

        $technologies = Technology::all()
            ->sortByDesc(fn($x) => in_array($x->id, $selectedTechs))
            ->values();

        $competencies = Competency::all()
            ->sortByDesc(fn($x) => in_array($x->id, $selectedComps))
            ->values();

        $methodologies = Methodology::all()
            ->sortByDesc(fn($x) => in_array($x->id, $selectedMeths))
            ->values();

        return response()->json([
            'position'      => $position,
            'languages'     => $languages,
            'technologies'  => $technologies,
            'competencies'  => $competencies,
            'methodologies' => $methodologies,
        ]);
    }


    /**
     * ✏ ACTUALIZAR ROL + RELACIONES
     */
    public function update(Request $request, $id)
    {
        $position = TechPosition::findOrFail($id);

        $request->validate([
            'position_name'   => 'required|string|max:255',
            'position_name_en'=> 'nullable|string|max:255',
            'category'        => 'nullable|string|max:255',
            'subcategory'     => 'nullable|string|max:255',

            'languages'       => 'array',
            'languages.*'     => 'integer|exists:languages,id',

            'technologies'    => 'array',
            'technologies.*'  => 'integer|exists:technologies,id',

            'competencies'    => 'array',
            'competencies.*'  => 'integer|exists:competencies,id',

            'methodologies'   => 'array',
            'methodologies.*' => 'integer|exists:methodologies,id',
        ]);

        $position->update([
            'position_name'    => $request->position_name,
            'position_name_en' => $request->position_name_en,
            'position_slug'    => Str::slug($request->position_name),
            'category'         => $request->category,
            'subcategory'      => $request->subcategory,
        ]);

        // 🔗 Actualizar pivots
        $position->languages()->sync($request->languages ?? []);
        $position->technologies()->sync($request->technologies ?? []);
        $position->competencies()->sync($request->competencies ?? []);
        $position->methodologies()->sync($request->methodologies ?? []);

        return response()->json([
            'message' => '✅ Rol tecnológico actualizado',
            'position'=> $position->load(['languages','technologies','competencies','methodologies']),
        ]);
    }


    /**
     * ❌ ELIMINAR
     */
    public function destroy($id)
    {
        $position = TechPosition::findOrFail($id);
        $position->delete();

        return response()->json(['message' => '🗑 Rol tecnológico eliminado']);
    }
    public function fetchPaginated(Request $request)
{
    $search = $request->get('search');

    $positions = TechPosition::with(['languages', 'technologies', 'methodologies', 'competencies'])
        ->when($search, function ($query, $search) {
            $query->where('position_name', 'like', "%{$search}%")
                  ->orWhere('position_name_en', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
        })
        ->orderBy('id', 'desc')
        ->paginate(10);

    return response()->json([
        'data' => $positions->items(),
        'current_page' => $positions->currentPage(),
        'last_page' => $positions->lastPage(),
        'next_page_url' => $positions->nextPageUrl(),
        'prev_page_url' => $positions->previousPageUrl(),
    ]);
}

}
