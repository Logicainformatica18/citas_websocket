<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\OutputConfig;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;

class PdfPartRunOcrJob implements ShouldQueue
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

        if (!$part->gcs_path) {
            Log::error("❌ [RunOCR] No existe gcs_path para la parte {$part->id}");
            return;
        }

        Log::info("🚀 [RunOCR] Iniciando OCR para parte {$part->id}", [
            'gcs_input' => $part->gcs_path
        ]);

        // 1️⃣ Configurar rutas
        $outputPrefix = "pdf_ocr_output/{$part->id}/" . Str::uuid();
        $gcsOutputUri = "gs://" . env('GCS_BUCKET') . "/" . $outputPrefix;

        // 2️⃣ Configurar cliente de Vision API usando TUS CREDENCIALES
        $client = new ImageAnnotatorClient([
            'credentials' => env('GCS_KEY_FILE_PATH'),
        ]);

        // 3️⃣ Crear Feature
        $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

        // 4️⃣ Configurar entrada PDF
        $gcsSource = (new GcsSource())->setUri($part->gcs_path);
        $inputConfig = (new InputConfig())
            ->setMimeType('application/pdf')
            ->setGcsSource($gcsSource);

        // 5️⃣ Configurar salida
        $gcsDestination = (new GcsDestination())->setUri($gcsOutputUri);
        $outputConfig = (new OutputConfig())->setGcsDestination($gcsDestination);

        $request = (new AsyncAnnotateFileRequest())
            ->setInputConfig($inputConfig)
            ->setFeatures([$feature])
            ->setOutputConfig($outputConfig);

        // 6️⃣ Ejecutar OCR asincrónico
        $operation = $client->asyncBatchAnnotateFiles([$request]);

        $maxAttempts = 20;
        $interval = 6;

        Log::info("⏳ [RunOCR] Esperando OCR (polling)...");

        for ($i = 1; $i <= $maxAttempts; $i++) {

            if ($operation->isDone()) {
                break;
            }

            Log::info("⌛ [RunOCR] Intento {$i}/{$maxAttempts} para parte {$part->id}");

            sleep($interval);
            $operation->reload();
        }

        if (!$operation->operationSucceeded()) {
            $msg = $operation->getError()?->getMessage() ?? 'Error desconocido';
            Log::error("❌ [RunOCR] Falló OCR para parte {$part->id}: $msg");
            throw new \Exception("OCR falló: " . $msg);
        }

        Log::info("✅ [RunOCR] OCR completado", [
            'part_id' => $part->id,
            'output_prefix' => $outputPrefix
        ]);

        // 🔥 Actualizar estado del pipeline
$part->update([
    'ocr_output_prefix' => $outputPrefix,
    'ocr_done'          => 1,
    'processed'         => 0.60,              // porcentaje de avance
    'step'              => 'extracted',       // estado lógico del proceso
]);

Log::info("📌 [RunOCR] Estado actualizado", [
    'part_id'   => $part->id,
    'processed' => 0.60,
    'step'      => 'extracted'
]);

        $client->close();
    }
}
