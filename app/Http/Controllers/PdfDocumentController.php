<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PdfDocument;
use App\Models\PdfDocumentPart;
use Illuminate\Support\Facades\Storage;

class PdfDocumentController extends Controller
{
    /*******************************************************************
     * 📄 LISTAR DOCUMENTOS
     *******************************************************************/
    public function index()
    {
        $documents = PdfDocument::latest()->paginate(10);

        return inertia('pdf/Index', [
            'documents' => $documents
        ]);
    }

    /*******************************************************************
     * 🆕 1) CREAR DOCUMENTO (solo título y metadata)
     *******************************************************************/
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|max:255',
            'year'        => 'nullable',
            'source'      => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $pdf = PdfDocument::create([
            'title'       => $request->title,
            'description' => $request->description,
            'source'      => $request->source,
            'year'        => $request->year,
            'processed'   => false,
            'file_path'   => '',        // 🔥 YA NO SE GUARDA PDF COMPLETO, PERO NO DEBE SER NULL
            'total_pages' => 0
        ]);

        return redirect()
            ->route('pdf.show', $pdf->id)
            ->with('success', 'Documento creado. Ahora cargue las partes (20 páginas).');
    }

    /*******************************************************************
     * 🆕 2) SUBIR UNA PARTE (PDF de 20 páginas)
     *******************************************************************/
    public function uploadPart(Request $request, $pdfId)
    {
        $request->validate([
            'part_pdf' => 'required|mimes:pdf|max:50000',
        ]);

        $pdf = PdfDocument::findOrFail($pdfId);

        // 🔥 1) Guardar archivo físico
        $path = $request->file('part_pdf')->store("pdf_parts/{$pdfId}", 'public');

        // 🔥 2) Siguiente número
        $nextNumber = $pdf->parts()->count() + 1;

        // 🔥 3) Crear registro de la parte
        $part = PdfDocumentPart::create([
            'pdf_id'      => $pdf->id,
            'part_number' => $nextNumber,
            'file_path'   => $path,
            'start_page'  => null, // lo sabremos si deseas
            'end_page'    => null,
            'processed'   => false,
        ]);

 \App\Jobs\PdfPartProcessJob::dispatch($part->id);


        return back()->with('success', "Parte {$nextNumber} cargada y procesándose.");
    }

    /*******************************************************************
     * 🧩 3) DETALLE DEL DOCUMENTO = LISTA DE PARTES
     *******************************************************************/
    public function show($id)
    {
        $pdf = PdfDocument::with('parts')->findOrFail($id);

        return inertia('pdf/Show', [
            'pdf' => $pdf
        ]);
    }

    /*******************************************************************
     * 🔎 4) DETALLE DE UNA PARTE (tablas/gráficos/resumen/páginas)
     *******************************************************************/
    public function showPart($pdfId, $partId)
    {
        $pdf = PdfDocument::findOrFail($pdfId);

        $part = PdfDocumentPart::where('pdf_id', $pdfId)
            ->where('id', $partId)
            ->with(['pages.tables', 'pages.graphs', 'summary'])
            ->firstOrFail();

        return inertia('pdf/Part', [
            'pdf'     => $pdf,
            'part'    => $part,
            'pages'   => $part->pages,
            'summary' => $part->summary,
        ]);
    }

    /*******************************************************************
     * 🔁 REPROCESAR UNA PARTE
     *******************************************************************/
    public function reprocessPart($pdfId, $partId)
    {
        $part = PdfDocumentPart::where('pdf_id', $pdfId)
            ->where('id', $partId)
            ->firstOrFail();

        $part->update(['processed' => false]);

     \App\Jobs\PdfPartProcessJob::dispatch($part->id);


        return back()->with('success', "Parte {$part->part_number} reprocesándose.");
    }

    /*******************************************************************
     * ❌ ELIMINAR PDF COMPLETO
     *******************************************************************/
    public function destroy($id)
    {
        $pdf = PdfDocument::findOrFail($id);

        // borrar partes físicas
        foreach ($pdf->parts as $part) {
            if (Storage::disk('public')->exists($part->file_path)) {
                Storage::disk('public')->delete($part->file_path);
            }
        }

        // borrar carpeta completa
        Storage::disk('public')->deleteDirectory("pdf_parts/{$pdf->id}");

        // borrar BD
        $pdf->delete();

        return back()->with('success', 'Documento eliminado correctamente.');
    }

    /*******************************************************************
     * 🔄 PAGINACIÓN AJAX
     *******************************************************************/
    public function fetch()
    {
        $documents = PdfDocument::latest()->paginate(10);

        return response()->json([
            'documents' => $documents
        ]);
    }
}
