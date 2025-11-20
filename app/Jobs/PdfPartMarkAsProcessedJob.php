<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use App\Models\PdfDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class PdfPartMarkAsProcessedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $partId;

    public function __construct(int $partId)
    {
        $this->partId = $partId;
    }
public function handle()
{
    $part = PdfDocumentPart::findOrFail($this->partId);

    $completed =
        $part->ocr_done &&
        $part->tables_done &&
        $part->graphs_done &&
        $part->summary_done;

    if ($completed) {

        // 🔥 Marcar parte como completada
        $part->update([
            'processed' => 1,
            'step' => 'completed'
        ]);

        Log::info("🟩 Parte {$part->id} marcada como COMPLETED");

        // 🔥 Ahora SÍ verificar si el documento completo está listo
        $this->finalizeDocumentIfComplete($part->pdf_id);
    }
}



    public function finalizeDocumentIfComplete($pdfId)
    {
        $pdf = PdfDocument::with('parts')->find($pdfId);
        if (!$pdf) return;

        $total = $pdf->parts->count();

        // ✔ todas las partes con summary_done = 1
        $finished = $pdf->parts->where('summary_done', 1)->count();

        if ($total > 0 && $total === $finished) {

            $pdf->update([
                'processed' => true
            ]);

            Log::info("📘 Documento {$pdf->id} marcado como procesado COMPLETO");
        }
    }
}
