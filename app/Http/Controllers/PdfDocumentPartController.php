<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PdfDocument;
use App\Models\PdfDocumentPart;
use Illuminate\Support\Facades\Storage;

class PdfDocumentPartController extends Controller
{
    /*******************************************************************
     * 📌 1) SUBIR UNA PARTE (PDF de 20 páginas)
     *******************************************************************/
    public function store(Request $request, $pdfId)
    {
        $request->validate([
            'part_pdf' => 'required|mimes:pdf|max:50000',
        ]);

        $pdf = PdfDocument::findOrFail($pdfId);

        // Guardar archivo en storage/app/public/pdf_parts/{id}/
        $path = $request->file('part_pdf')->store("pdf_parts/{$pdfId}", 'public');

        // Determinar número de parte
        $nextNumber = $pdf->parts()->count() + 1;

        // Crear part
        $part = PdfDocumentPart::create([
            'pdf_id'      => $pdf->id,
            'part_number' => $nextNumber,
            'file_path'   => $path,
            'processed'   => false,
        ]);

        // Lanzar OCR con Job ADAPTADO (PdfPartProcessJob)
        \App\Jobs\PdfPartProcessJob::dispatch($part->id)
            ->onQueue('default');

        return back()->with('success', "Parte {$nextNumber} cargada y procesándose.");
    }

    /*******************************************************************
     * 👁 2) VER DETALLE DE UNA PARTE
     *******************************************************************/
    public function show($pdfId, $partId)
    {
        $pdf = PdfDocument::findOrFail($pdfId);

        $part = PdfDocumentPart::where('pdf_id', $pdfId)
            ->where('id', $partId)
            ->with([
                'pages.tables',
                'pages.graphs',
                'summary'
            ])
            ->firstOrFail();

        return inertia('pdf/Part', [
            'pdf'     => $pdf,
            'part'    => $part,
            'pages'   => $part->pages,
            'summary' => $part->summary,
        ]);
    }

    /*******************************************************************
     * 🔁 3) REPROCESAR UNA PARTE (volver a lanzar el OCR)
     *******************************************************************/
    public function reprocess($pdfId, $partId)
    {
        $part = PdfDocumentPart::where('pdf_id', $pdfId)
            ->where('id', $partId)
            ->firstOrFail();

        $part->update(['processed' => false]);

        \App\Jobs\PdfPartProcessJob::dispatch($part->id)
            ->onQueue('default');

        return back()->with('success', "Parte {$part->part_number} está siendo reprocesada.");
    }

    /*******************************************************************
     * ❌ 4) ELIMINAR UNA PARTE
     *******************************************************************/
    public function destroy($pdfId, $partId)
    {
        $part = PdfDocumentPart::where('pdf_id', $pdfId)
            ->where('id', $partId)
            ->firstOrFail();

        // borrar PDF físico
        if (Storage::disk('public')->exists($part->file_path)) {
            Storage::disk('public')->delete($part->file_path);
        }

        // borrar resultados
        $part->pages()->delete();
        $part->summary()->delete();

        // borrar BD
        $part->delete();

        return back()->with('success', "Parte eliminada correctamente.");
    }
}
