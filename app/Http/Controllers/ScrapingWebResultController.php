<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScrapingWebResult;
use App\Models\ScrapingSource;

class ScrapingWebResultController extends Controller
{
    /**
     * 📌 Listar resultados web por fuente
     */
    public function index($sourceId)
    {
        $source = ScrapingSource::findOrFail($sourceId);

        $results = ScrapingWebResult::where('source_id', $sourceId)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return inertia('Scraping/WebResults/Index', [
            'source'  => $source,
            'results' => $results,
        ]);
    }


    /**
     * 📌 Mostrar un resultado individual
     */
    public function show($resultId)
    {
        $result = ScrapingWebResult::findOrFail($resultId);

        return inertia('Scraping/WebResults/Show', [
            'result' => $result,
        ]);
    }


    /**
     * ✏️ Actualizar (URL, categoría, estado…)
     */
    public function update(Request $request, $id)
    {
        $result = ScrapingWebResult::findOrFail($id);

        $validated = $request->validate([
            'url'      => 'required|string|max:1000',
            'category' => 'nullable|string|max:255',
            'status'   => 'nullable|string|in:pending,completed,error',
        ]);

        $result->update($validated);

        return back()->with('success', 'Resultado actualizado correctamente.');
    }


    /**
     * 🗑 Eliminar resultado web
     */
    public function destroy($id)
    {
        $result = ScrapingWebResult::findOrFail($id);
        $result->delete();

        return back()->with('success', 'Resultado eliminado.');
    }
}
