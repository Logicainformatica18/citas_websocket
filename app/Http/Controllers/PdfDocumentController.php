<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PdfDocument;
use App\Jobs\ProcessPdfDocumentJob;
use Illuminate\Support\Facades\Storage;
use App\Jobs\PdfOcrJob;

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
     * 📤 SUBIR PDF (CORREGIDO)
     *******************************************************************/
    public function store(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:50000'
        ]);

        // Guardar PDF en disco "public" (IMPORTANTE)
        $path = $request->file('pdf')->store('pdf_uploads', 'public');

        $pdf = PdfDocument::create([
            'title' => $request->get('title', $request->file('pdf')->getClientOriginalName()),
            'description' => $request->get('description'),
            'source' => $request->get('source'),
            'year' => $request->get('year'),
            'file_path' => $path,
            'processed' => false
        ]);

        // Ejecutar el Job
   PdfOcrJob::dispatch($pdf->id);

        return back()->with('success', 'PDF subido y en cola para procesarse.');
    }

    /*******************************************************************
     * 👁 VER DETALLE DEL PDF
     *******************************************************************/
    public function show($id)
    {
        $pdf = PdfDocument::with([
            'pages.graphs',
            'pages.tables',
            'summary'
        ])->findOrFail($id);

        return inertia('pdf/Show', [
            'pdf' => $pdf
        ]);
    }

    /*******************************************************************
     * 🔄 REP_PROCESSAR (VOLVER A ANALIZAR)
     *******************************************************************/
    public function reprocess($id)
    {
        $pdf = PdfDocument::findOrFail($id);

        // resetear estado
        $pdf->update(['processed' => false]);

        // volver a ejecutar el job
        ProcessPdfDocumentJob::dispatch($pdf->id);

        return back()->with('success', 'El PDF está siendo reprocesado.');
    }

    /*******************************************************************
     * 📥 DESCARGAR PDF
     *******************************************************************/
    public function download($id)
    {
        $pdf = PdfDocument::findOrFail($id);

        if (!Storage::disk('public')->exists($pdf->file_path)) {
            abort(404, 'El archivo no existe.');
        }

        return Storage::disk('public')->download($pdf->file_path, $pdf->title);
    }

    /*******************************************************************
     * ❌ ELIMINAR PDF (Y TODO LO RELACIONADO)
     *******************************************************************/
    public function destroy($id)
    {
        $pdf = PdfDocument::findOrFail($id);

        // eliminar archivo físico
        if (Storage::disk('public')->exists($pdf->file_path)) {
            Storage::disk('public')->delete($pdf->file_path);
        }

        // eliminar directorio de imágenes si lo usas
        Storage::disk('public')->deleteDirectory("pdf_pages/{$pdf->id}");

        // eliminar resultados OCR (si están en public)
        Storage::disk('public')->deleteDirectory("pdf_results/{$pdf->id}");

        // eliminar de BD (relaciones con cascade)
        $pdf->delete();

        return back()->with('success', 'PDF eliminado correctamente.');
    }

    /*******************************************************************
     * 🔄 PAGINACIÓN AJAX (PARA LA TABLA)
     *******************************************************************/
    public function fetch()
    {
        $documents = PdfDocument::latest()->paginate(10);

        return response()->json([
            'documents' => $documents
        ]);
    }
}
