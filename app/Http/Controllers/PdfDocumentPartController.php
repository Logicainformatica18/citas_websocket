<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PdfDocumentPart;
use App\Models\ScrapingSource;
use Illuminate\Support\Facades\Storage;

class PdfDocumentPartController extends Controller
{
    /*******************************************************************
     * 📌 1) SUBIR UNA PARTE (PDF de 20 páginas)
     *******************************************************************/
    public function store(Request $request, $sourceId)
    {
        $source = ScrapingSource::findOrFail($sourceId);

        $request->validate([
            'part_pdf' => 'required|file|mimes:pdf|max:500000',
        ]);

        // Guardar archivo
        $path = $request->file('part_pdf')
            ->store("scraping_parts/{$sourceId}", 'public');

        // Número siguiente de parte
        $nextNumber = PdfDocumentPart::where('scraping_source_id', $sourceId)->count() + 1;

        // Crear registro
        $part = PdfDocumentPart::create([
            'scraping_source_id' => $sourceId,
            'part_number'        => $nextNumber,
            'file_path'          => $path,
            'original_name'      => $request->file('part_pdf')->getClientOriginalName(),
            'processed'          => false,
        ]);

        // Lanzar job de procesamiento
        \App\Jobs\PdfPartProcessJob::dispatch($part->id);

        return response()->json([
            'message' => "Parte {$nextNumber} cargada.",
            'part_id' => $part->id,
            'file'    => $path,
        ], 201);
    }


    /*******************************************************************
     * 👁 2) VER DETALLE DE UNA PARTE
     *******************************************************************/
    public function show($partId)
    {
        $part = PdfDocumentPart::with([
            'pages.tables',
            'pages.graphs',
            'summary',
            'source', // relación hacia ScrapingSource
        ])->findOrFail($partId);

        // URL pública
        $part->file_url = Storage::disk('public')->url($part->file_path);

        return inertia('ScrapingSources/Parts/PartDetail', [
            'source'  => $part->source,
            'part'    => $part,
            'pages'   => $part->pages,
            'summary' => $part->summary,
            'file_url'=> $part->file_url,
        ]);
    }


    /*******************************************************************
     * 🔁 3) REPROCESAR UNA PARTE
     *******************************************************************/
    public function reprocess($partId)
    {
        $part = PdfDocumentPart::findOrFail($partId);

        $part->update(['processed' => false]);

        \App\Jobs\PdfPartProcessJob::dispatch($part->id);

        return back()->with('success', "Parte {$part->part_number} reprocesada.");
    }


    /*******************************************************************
     * ❌ 4) ELIMINAR UNA PARTE
     *******************************************************************/
    public function destroy($partId)
    {
        $part = PdfDocumentPart::with('pages', 'summary')->findOrFail($partId);

        // Borrar archivo físico
        if (Storage::disk('public')->exists($part->file_path)) {
            Storage::disk('public')->delete($part->file_path);
        }

        // Borrar páginas OCR
        $part->pages()->delete();

        // Borrar resumen
        $part->summary()->delete();

        // Borrar registro
        $part->delete();

        return response()->json([
            'status' => 'ok',
            'message' => 'Parte eliminada correctamente.',
            'deleted_part_id' => $partId,
        ]);
    }
}
