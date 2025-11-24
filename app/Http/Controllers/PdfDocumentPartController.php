<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PdfDocument;
use App\Models\PdfDocumentPart;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;



class PdfDocumentPartController extends Controller
{
    /*******************************************************************
     * 📌 1) SUBIR UNA PARTE (PDF de 20 páginas)
     *******************************************************************/
public function store(Request $request, $pdfId)
{

    $request->validate([
        'part_pdf' => 'required|mimes:pdf|max:500000',
    ]);

    $pdf = PdfDocument::findOrFail($pdfId);

    // Guardar archivo
    $path = $request->file('part_pdf')->store("pdf_parts/{$pdfId}", 'public');

    // Siguiente número
    $nextNumber = $pdf->parts()->count() + 1;

    $part = PdfDocumentPart::create([
        'pdf_id'      => $pdf->id,
        'part_number' => $nextNumber,
        'file_path'   => $path,
        'processed'   => false,
    ]);

    \App\Jobs\PdfPartProcessJob::dispatch($part->id);

    // 🔥 RESPONDER JSON (esto evita el 422)
    return response()->json([
        'message' => "Parte {$nextNumber} cargada.",
        'part_id' => $part->id,
        'file'    => $path,
    ], 201);
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

    // 🔥 Asegurar que el frontend reciba la URL pública del PDF (para "Ver PDF")
    $part->file_url = \Storage::disk('public')->url($part->file_path);

    return inertia('pdf/Part', [
        'pdf'     => $pdf,
        'part'    => $part,
        'pages'   => $part->pages,
        'summary' => $part->summary,
        'file_url' => $part->file_url, // opcional, por si quieres usarlo directamente
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

    // borrar OCR, páginas, resumen
    $part->pages()->delete();
    $part->summary()->delete();

    // borrar BD
    $part->delete();

    return response()->json([
        'status' => 'ok',
        'message' => 'Parte eliminada correctamente.',
        'deleted_part_id' => $partId
    ]);
}



}
