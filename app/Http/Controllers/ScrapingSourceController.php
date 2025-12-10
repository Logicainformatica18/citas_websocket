<?php

namespace App\Http\Controllers;

use App\Models\ScrapingSource;
use App\Models\PdfDocumentPart;
use App\Models\ScrapingWebResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

// Jobs
use App\Jobs\ExtractLinksJob;
use App\Jobs\ProcessWebResultJob;

class ScrapingSourceController extends Controller
{
    /** ============================================================
     * 📌 LISTADO
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
  // 👇 FORZAR web_only A BOOLEANO
    $sources->getCollection()->transform(function ($s) {
        $s->web_only = (bool) $s->web_only;
        return $s;
    });
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
              // 👇 FORZAR web_only A BOOLEANO
    $sources->getCollection()->transform(function ($s) {
        $s->web_only = (bool) $s->web_only;
        return $s;
    });

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

        // 🔥 Nuevo flujo: solo inicia descubrimiento
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

        'api_url'     => 'nullable',
        'api_key'     => 'nullable',

        'pdf_file'    => 'nullable|file|mimes:pdf|max:100240',
        'excel_file'  => 'nullable|file|mimes:xlsx,xls,csv|max:10240',
    ]);

    $data = $validated;

    // 🔥 LIMPIAR CAMPOS SI FUERON BORRADOS EN EL FORMULARIO
    if ($request->has('web_prompt') && $request->input('web_prompt') === '') {
        $data['web_prompt'] = null;
    }

    if ($request->has('api_url') && $request->input('api_url') === '') {
        $data['api_url'] = null;
    }

    if ($request->has('api_key') && $request->input('api_key') === '') {
        $data['api_key'] = null;
    }

    // Archivos
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
     * ▶️ BOTÓN 1 — Obtener Enlaces Iniciales
     * ============================================================ */
    public function extractLinks($id)
    {
        $source = ScrapingSource::findOrFail($id);

 ExtractLinksJob::dispatch(sourceId: $source->id);


        return response()->json([
            'message' => 'Extracción inicial de enlaces iniciadwwwwwass.'
        ]);
    }


    /** ============================================================
     * ▶️ BOTÓN 2 — Procesar Enlaces (Job hijo)
     * ============================================================ */
    public function processData($id)
    {
        $source = ScrapingSource::findOrFail($id);

        $pendings = ScrapingWebResult::where('source_id', $id)
                    ->where('status', 'pending')
                    ->get();

        foreach ($pendings as $row) {
            ProcessWebResultJob::dispatch($row->id);
        }

        return response()->json([
            'message' => 'Procesamiento de enlaces iniciado.'
        ]);
    }


    /** ============================================================
     * 🟦 BOTÓN 3 — Verificar Enlaces Pendientes (para el UI)
     * ============================================================ */
    public function pendingCount($id)
    {
        $count = ScrapingWebResult::where('source_id', $id)
                    ->where('status', 'pending')
                    ->count();

        return response()->json([
            'pending' => $count
        ]);
    }


    /** ============================================================
     * 📄 PARTES PDF
     * ============================================================ */
    public function show($id)
    {
        return redirect()->to("/scraping-sources/{$id}/parts");
    }

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
