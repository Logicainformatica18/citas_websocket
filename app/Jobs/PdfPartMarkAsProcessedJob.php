<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
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

        $part->update([
            'processed' => 1
        ]);

        Log::info("✅ [MarkAsProcessed] Parte marcada como processada", [
            'part_id' => $part->id
        ]);
    }
}
