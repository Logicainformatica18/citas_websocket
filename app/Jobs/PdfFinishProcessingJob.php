<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class PdfFinishProcessingJob implements ShouldQueue
{
    use Queueable, Dispatchable;

    public $pdfId;

    public function __construct($pdfId)
    {
        $this->pdfId = $pdfId;
    }

    public function handle()
    {
        $pdf = PdfDocument::find($this->pdfId);

        if (!$pdf) {
            Log::error("❌ PdfFinishProcessingJob: PDF {$this->pdfId} no encontrado.");
            return;
        }

        // Si ya está procesado, no hacemos nada
        if ($pdf->processed) {
            Log::info("ℹ PdfFinishProcessingJob: PDF {$pdf->id} ya estaba marcado como procesado.");
            return;
        }

        $pdf->update([
            'processed' => true,
            'processed_at' => now(),   // opcional, si tienes esta columna
        ]);

        Log::info("🎉 PdfFinishProcessingJob: PDF {$pdf->id} marcado como procesado.");
    }
}
