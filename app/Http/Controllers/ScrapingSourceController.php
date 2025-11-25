<?php

namespace App\Http\Controllers;

use App\Models\ScrapingSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ScrapingSourceController extends Controller
{
    /** INDEX */
    public function index(Request $request)
    {
        $sources = ScrapingSource::when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('url', 'like', "%{$request->search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return Inertia::render('ScrapingSources/Index', [
            'sources' => $sources,
            'filters' => [
                'search' => $request->search ?? null,
            ],
        ]);
    }

    /** FETCH (AJAX) */
    public function fetch(Request $request)
    {
        $sources = ScrapingSource::orderBy('id', 'desc')->paginate(10);

        return response()->json([
            'sources' => $sources
        ]);
    }

    /** STORE */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'url'         => 'nullable|url|max:500',
            'frequency'   => 'nullable|string|max:50',
            'notes'       => 'nullable|string|max:500',

            'web_prompt'  => 'nullable|string',
            'api_url'     => 'nullable|string|max:500',
            'api_key'     => 'nullable|string|max:255',

            'pdf_file'    => 'nullable|file|mimes:pdf',
            'excel_file'  => 'nullable|file|mimes:xlsx,xls,csv',
        ]);

        if ($request->hasFile('pdf_file')) {
            $validated['pdf_path'] = $request->file('pdf_file')
                                            ->store('scraping/pdf', 'public');
        }

        if ($request->hasFile('excel_file')) {
            $validated['excel_path'] = $request->file('excel_file')
                                              ->store('scraping/excel', 'public');
        }

        $source = ScrapingSource::create($validated);

        return response()->json([
            'message' => 'Fuente creada correctamente',
            'source' => $source
        ], 201);
    }

    /** UPDATE */
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

    /** DESTROY */
    public function destroy($id)
    {
        $source = ScrapingSource::findOrFail($id);

        if ($source->pdf_path) Storage::disk('public')->delete($source->pdf_path);
        if ($source->excel_path) Storage::disk('public')->delete($source->excel_path);

        $source->delete();

        return response()->json(['message' => 'Fuente eliminada']);
    }


    /** 🚀 INICIAR SCRAPING */
    public function process($id)
    {
        $source = ScrapingSource::findOrFail($id);

        if ($source->scrape_status === 'processing') {
            return response()->json(['message' => 'La fuente ya está siendo procesada.'], 409);
        }

        $source->update([
            'scrape_status'  => 'queued',
            'scrape_message' => 'Esperando worker...',
            'last_scraped_at' => now(),
        ]);

        \App\Jobs\ProcessScrapingSourceJob::dispatch($source->id);

        $source->refresh();

        return response()->json([
            'message' => 'Scraping iniciado correctamente',
            'source' => $source
        ]);
    }
public function show($id)
{
    return response()->json([
        'source' => ScrapingSource::findOrFail($id)
    ]);
}



}
