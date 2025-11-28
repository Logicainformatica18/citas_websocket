<?php

namespace App\Http\Controllers;

use App\Models\ScrapingSource;
use App\Models\PdfDocumentPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Jobs\ExtractLinksJob;

class ScrapingSourceController extends Controller
{
    /** ============================================================
     * 📌 LISTADO DE FUENTES
     * ============================================================ */
    public function index(Request $request)
    {
        $sources = ScrapingSource::when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('url', 'like', "%{$request->search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->appends($request->only('search'));

        return Inertia::render('ScrapingSources/Index', [
            'sources' => $sources,
            'filters' => [
                'search' => $request->search ?? null,
            ],
        ]);
    }


    /** ============================================================
     * 📌 FETCH (AJAX)
     * ============================================================ */
    public function fetch(Request $request)
    {
        $sources = ScrapingSource::when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('url', 'like', "%{$request->search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'sources' => $sources
        ]);
    }


    /** ============================================================
     * 📌 CREAR FUENTE
     * ============================================================ */
 public function store(Request $request)
{
    $source = ScrapingSource::create($request->all());

    // 🔥 Inicia solo la etapa de descubrimiento
    ExtractLinksJob::dispatch($source->id);

    return response()->json([
        'message' => 'Fuente registrada y extracción inicial iniciada.',
        'source'   => $source
    ]);
}


    /** ============================================================
     * 📌 ACTUALIZAR FUENTE
     * ============================================================ */
    public function update(Request $request, $id)
    {
        $source = ScrapingSource::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'url'         => 'sometimes|url|max:500',
            'frequency'   => 'sometimes|string|max:50',
            'notes'       => 'sometimes|string|max:500',

            'web_prompt'  => 'nullable|string',
            'api_url'     => 'nullable|string|max:500',
            'api_key'     => 'nullable|string|max:255',

            'pdf_file'    => 'nullable|file|mimes:pdf|max:100240',
            'excel_file'  => 'nullable|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $data = $validated;

        if ($request->hasFile('pdf_file')) {
            if ($source->pdf_path) Storage::disk('public')->delete($source->pdf_path);

            $data['pdf_path'] = $request->file('pdf_file')->store('scraping/pdf', 'public');
        }

        if ($request->hasFile('excel_file')) {
            if ($source->excel_path) Storage::disk('public')->delete($source->excel_path);

            $data['excel_path'] = $request->file('excel_file')->store('scraping/excel', 'public');
        }

        $source->update($data);

        return response()->json([
            'message' => 'Fuente actualizada correctamente',
            'source' => $source
        ]);
    }


    /** ============================================================
     * 📌 ELIMINAR FUENTE
     * ============================================================ */
    public function destroy($id)
    {
        $source = ScrapingSource::findOrFail($id);

        if ($source->pdf_path) Storage::disk('public')->delete($source->pdf_path);
        if ($source->excel_path) Storage::disk('public')->delete($source->excel_path);

        PdfDocumentPart::where('scraping_source_id', $id)->delete();

        $source->delete();

        return response()->json(['message' => 'Fuente eliminada']);
    }


    /** ============================================================
     * 🚀 INICIAR SCRAPING (YA NO ACTUALIZA scrape_status)
     * ============================================================ */
    public function process($id)
    {
        $source = ScrapingSource::findOrFail($id);

        // No hay más estados en la tabla fuente, solo dispara el Job
        \App\Jobs\ProcessScrapingSourceJob::dispatch($source->id);

        return response()->json([
            'message' => 'Scraping iniciado correctamente'
        ]);
    }


    /** ============================================================
     * 📄 REDIRECCIÓN A PARTES DE PDF
     * ============================================================ */
    public function show($id)
    {
        return redirect()->to("/scraping-sources/{$id}/parts");
    }


    /** ============================================================
     * 📄 LISTAR PARTES PDF
     * ============================================================ */
    public function parts($id)
    {
        $source = ScrapingSource::findOrFail($id);

        $parts = PdfDocumentPart::where('scraping_source_id', $id)
            ->orderBy('part_number', 'asc')
            ->get();

        return Inertia::render('ScrapingSources/Parts/Index', [
            'source' => $source,
            'parts'  => $parts,
        ]);
    }

}
