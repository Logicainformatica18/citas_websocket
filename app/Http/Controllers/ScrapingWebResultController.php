<?php

namespace App\Http\Controllers;

use App\Models\ScrapingWebResult;
use App\Models\ScrapingSource;
use Illuminate\Http\Request;
use App\Jobs\ProcessWebResultJob;

class ScrapingWebResultController extends Controller
{
    /**
     * LISTAR
     */
    public function index($sourceId)
    {
        $source = ScrapingSource::findOrFail($sourceId);

        $results = ScrapingWebResult::where('source_id', $sourceId)
            ->orderBy('id', 'asc')
            ->paginate(25);

        return inertia('Scraping/WebResults/Index', [
            'source'  => $source,
            'results' => $results,
        ]);
    }

    /**
     * AJAX FETCH
     */
    public function fetch(Request $request, $sourceId)
    {
        $query = ScrapingWebResult::where('source_id', $sourceId);

        if ($request->search) {
            $query->where('url', 'like', "%{$request->search}%");
        }

        $results = $query->orderBy('id', 'asc')->paginate(25);

        return response()->json([
            'results' => $results
        ]);
    }

    /**
     * PROCESAR TODOS
     */
    public function processAll($sourceId)
    {
        $pendings = ScrapingWebResult::where('source_id', $sourceId)
            ->where('status', 'pending')
            ->get();

        foreach ($pendings as $row) {
            ProcessWebResultJob::dispatch($row->id);
        }

        return response()->json([
            'message' => "Procesamiento enviado para {$pendings->count()} enlaces."
        ]);
    }

    /**
     * ACTUALIZAR
     */
    public function update(Request $request, $id)
    {
        $result = ScrapingWebResult::findOrFail($id);

        $request->validate([
            'url'      => 'required',
            'category' => 'nullable|string|max:255',
            'status'   => 'required|in:pending,completed,error',
        ]);

        $result->update($request->all());

        return response()->json(['message' => 'Actualizado']);
    }

    /**
     * ELIMINAR
     */
    public function destroy($id)
    {
        ScrapingWebResult::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Eliminado'
        ]);
    }
    public function processOne($id)
{
    $result = ScrapingWebResult::findOrFail($id);

    \App\Jobs\ProcessWebResultJob::dispatch($result->id);

    return response()->json([
        'message' => 'Procesamiento iniciado'
    ]);
}

}
