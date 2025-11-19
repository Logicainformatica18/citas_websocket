<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;

class PdfPartProcessJob implements ShouldQueue
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

        // 🚀 ENCADENA TODOS LOS JOBS EN LA COLA DEFAULT
        Bus::chain([
            new PdfPartUploadToGCSJob($part->id),
            new PdfPartRunOcrJob($part->id),
            new PdfPartExtractPagesJob($part->id),
            new PdfPartDetectTablesJob($part->id),
            new PdfPartDetectGraphsJob($part->id),
            new PdfPartGenerateSummaryJob($part->id),
          new PdfPartMarkAsProcessedJob($part->id),  // 👈 EL FINAL
        ])->dispatch();
    }
}
