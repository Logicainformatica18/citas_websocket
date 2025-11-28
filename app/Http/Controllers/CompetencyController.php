<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\Career;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompetencyController extends Controller
{
    /**
     * 📄 Listado general (Inertia)
     */
    public function index(Request $request)
    {
        $search    = $request->get('search');
        $careerId  = $request->get('career_id');
        $category  = $request->get('category');

        // Lista de carreras para filtros
        $careers = Career::select('id', 'name')->orderBy('name')->get();

        $competencies = Competency::query()
            ->when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description_es', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
            )
            ->when($careerId, fn($q) => $q->where('career_id', $careerId))
            ->when($category, fn($q) => $q->where('category', $category))
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

       return Inertia::render('competencies/Index', [
    'competencies' => $competencies->through(fn ($c) => [
        'id'            => $c->id,
        'career_id'     => $c->career_id,
        'career_name'   => optional($c->career)->name,
        'name'          => $c->name,
        'category'      => $c->category,
        'weight'        => $c->weight,
        'description_es'=> $c->description_es,
        'description_en'=> $c->description_en,
        'created_at'    => optional($c->created_at)->format('Y-m-d'),
    ]),
    'filters' => [
        'search'    => $search,
        'career_id' => $careerId,
        'category'  => $category,
    ],
    'careers' => $careers
]);


    }

    /**
     * 📄 API JSON paginada
     */
    public function fetchPaginated(Request $request)
    {
        $search    = $request->get('search');
        $careerId  = $request->get('career_id');
        $category  = $request->get('category');

        $competencies = Competency::query()
            ->when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description_es', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
            )
            ->when($careerId, fn($q) => $q->where('career_id', $careerId))
            ->when($category, fn($q) => $q->where('category', $category))
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

       return response()->json(
    $competencies->through(fn ($c) => [
        'id'            => $c->id,
        'career_id'     => $c->career_id,
        'career_name'   => optional($c->career)->name,
        'name'          => $c->name,
        'category'      => $c->category,
        'weight'        => $c->weight,
        'description_es'=> $c->description_es,
        'description_en'=> $c->description_en,
    ])
);

    }

    /**
     * 🆕 Crear competencia
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'career_id'     => 'nullable|integer|exists:careers,id',
            'name'          => 'required|string|max:255',
            'description_es'=> 'nullable|string',
            'description_en'=> 'nullable|string',
            'category'      => 'nullable|string|max:255',
            'weight'        => 'nullable|numeric|min:0|max:1',
        ]);

        return DB::transaction(function () use ($validated) {
            $competency = Competency::create($validated);

            return response()->json([
                'message'    => '✅ Competencia creada correctamente.',
                'competency' => $competency,
            ], 201);
        });
    }

    /**
     * ✏️ Actualizar competencia
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'career_id'     => 'nullable|integer|exists:careers,id',
            'name'          => 'required|string|max:255',
            'description_es'=> 'nullable|string',
            'description_en'=> 'nullable|string',
            'category'      => 'nullable|string|max:255',
            'weight'        => 'nullable|numeric|min:0|max:1',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $competency = Competency::findOrFail($id);
            $competency->update($validated);

            return response()->json([
                'message'    => '✅ Competencia actualizada correctamente.',
                'competency' => $competency,
            ]);
        });
    }

    /**
     * 🗑️ Eliminar competencia
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $competency = Competency::findOrFail($id);
            $competency->delete();

            return response()->json(['message' => '🗑️ Competencia eliminada correctamente.']);
        });
    }

    /**
     * 🔄 Activar / desactivar si deseas añadir un switch futuro
     */
    public function toggle($id, Request $request)
    {
        $competency = Competency::findOrFail($id);
        $competency->enabled = $request->enabled;
        $competency->save();

        return response()->json(['success' => true]);
    }
}
