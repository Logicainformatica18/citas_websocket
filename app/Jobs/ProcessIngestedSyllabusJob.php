<?php

namespace App\Jobs;

use App\Models\Syllabus;
use App\Services\Syllabus\SyllabusExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Procesa un sílabo recibido desde silabos_v2. Ya trae el texto, así que no hay OCR:
 * pasa directo por la misma extracción que los PDF.
 */
class ProcessIngestedSyllabusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $syllabusId)
    {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SyllabusExtractionService $extraction): void
    {
        $syllabus = Syllabus::find($this->syllabusId);

        if (!$syllabus) {
            Log::error("❌ Syllabus con ID {$this->syllabusId} no encontrado.");
            return;
        }

        // Reemplazado mientras esperaba en la cola: no gastar la llamada a OpenAI.
        if ($syllabus->superseded_at) {
            $syllabus->update(['status' => 'superseded']);
            return;
        }

        $syllabus->update(['status' => 'processing']);
        $extraction->process($syllabus);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("❌ Error en ProcessIngestedSyllabusJob: " . $e->getMessage(), [
            'syllabus_id' => $this->syllabusId,
        ]);

        Syllabus::whereKey($this->syllabusId)->update(['status' => 'failed']);
    }
}
