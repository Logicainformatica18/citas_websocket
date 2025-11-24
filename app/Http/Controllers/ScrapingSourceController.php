<?php

namespace App\Http\Controllers;

use App\Models\ScrapingSource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScrapingSourceController extends Controller
{
    /***************************************************************
     * 📄 INDEX — Vista principal con búsqueda y paginación
     ***************************************************************/
    public function index(Request $request)
    {
        $search = $request->input('search');

        $sources = ScrapingSource::when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ScrapingSources/Index', [
            'sources' => $sources,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /***************************************************************
     * 🔄 FETCH — Devuelve JSON (útil para actualizaciones rápidas)
     ***************************************************************/
    public function fetch(Request $request)
    {
        $search = $request->input('search');

        $sources = ScrapingSource::when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15);

        return response()->json([
            'sources' => $sources
        ]);
    }

    /***************************************************************
     * 🆕 STORE — Crear un nuevo recurso
     ***************************************************************/
    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'url'       => 'nullable|url|max:500',
            'frequency' => 'nullable|string|max:50',

            'has_pdf'   => 'required|boolean',
            'web_only'  => 'required|boolean',
            'has_api'   => 'required|boolean',
            'scrapable' => 'required|boolean',

            'notes'     => 'nullable|string|max:500',
        ]);

        ScrapingSource::create($request->all());

        return back()->with('success', 'Fuente creada correctamente.');
    }

    /***************************************************************
     * ✏️ UPDATE — Actualizar recurso existente
     ***************************************************************/
    public function update(Request $request, $id)
    {
        $source = ScrapingSource::findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:255',
            'url'       => 'nullable|url|max:500',
            'frequency' => 'nullable|string|max:50',

            'has_pdf'   => 'required|boolean',
            'web_only'  => 'required|boolean',
            'has_api'   => 'required|boolean',
            'scrapable' => 'required|boolean',

            'notes'     => 'nullable|string|max:500',
        ]);

        $source->update($request->all());

        return back()->with('success', 'Fuente actualizada correctamente.');
    }

    /***************************************************************
     * ❌ DESTROY — Eliminar recurso
     ***************************************************************/
    public function destroy($id)
    {
        $source = ScrapingSource::findOrFail($id);
        $source->delete();

        return back()->with('success', 'Fuente eliminada correctamente.');
    }
}
