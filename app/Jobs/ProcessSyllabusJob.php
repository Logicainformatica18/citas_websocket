<?php

namespace App\Jobs;

use App\Models\Syllabus;
use App\Services\Syllabus\SyllabusExtractionService;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\OutputConfig;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessSyllabusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $syllabusId;

    public function __construct($syllabusId)
    {
        $this->syllabusId = $syllabusId;
    }

    public function handle(SyllabusExtractionService $extraction): void
    {
        $syllabus = Syllabus::find($this->syllabusId);

        if (!$syllabus) {
            Log::error("❌ Syllabus con ID {$this->syllabusId} no encontrado.");
            return;
        }

        $syllabus->update(['status' => 'processing']);

        try {
            // 📂 Subir PDF a GCS
            $localPath = Storage::disk('public')->path($syllabus->path);
            $gcsPath = "syllabus/{$syllabus->id}.pdf";

            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => env('GCS_PROJECT_ID'),
                'keyFilePath' => env('GCS_KEY_FILE_PATH'),
            ]);

            $bucket = $storage->bucket(env('GCS_BUCKET'));
            $bucket->upload(
                fopen($localPath, 'r'),
                ['name' => $gcsPath]
            );

            $gcsInputUri = "gs://" . env('GCS_BUCKET') . "/" . $gcsPath;
            Log::info("☁️ PDF subido a GCS", ['uri' => $gcsInputUri]);

            // 📤 Configuración de entrada
            $gcsSource = (new GcsSource())->setUri($gcsInputUri);
            $inputConfig = (new InputConfig())
                ->setMimeType('application/pdf')
                ->setGcsSource($gcsSource);

            // 📥 Configuración de salida
            $gcsDestinationUri = "gs://" . env('GCS_BUCKET') . "/syllabus_results/{$syllabus->id}/";
            $gcsDestination = (new GcsDestination())->setUri($gcsDestinationUri);
            $outputConfig = (new OutputConfig())->setGcsDestination($gcsDestination);

            $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

            // ✅ Crear AsyncAnnotateFileRequest
            $request = new AsyncAnnotateFileRequest();
            $request->setInputConfig($inputConfig);
            $request->setFeatures([$feature]);
            $request->setOutputConfig($outputConfig);

            $client = new ImageAnnotatorClient([
                'credentials' => env('GCS_KEY_FILE_PATH'),
            ]);

       // 📡 Llamar a Vision API con polling extendido
$operation = $client->asyncBatchAnnotateFiles([$request]);

$maxAttempts = 20;      // hasta 20 intentos (~2 minutos)
$interval = 6;          // segundos entre intentos

for ($i = 1; $i <= $maxAttempts; $i++) {
    if ($operation->isDone()) break;

    Log::info("⌛ Esperando OCR (intento {$i}/{$maxAttempts}) para syllabus {$this->syllabusId}...");
    sleep($interval);
    $operation->reload(); // vuelve a consultar el estado
}

if (!$operation->operationSucceeded()) {
    $errorMessage = $operation->getError() ? $operation->getError()->getMessage() : 'desconocido';
    throw new \Exception("Error en OCR PDF: {$errorMessage}");
}


            if (!$operation->operationSucceeded()) {
                throw new \Exception("Error en OCR PDF: " . $operation->getError()->getMessage());
            }

            Log::info("✅ OCR terminado, resultados en GCS", ['output' => $gcsDestinationUri]);

            // 📥 Leer JSON de resultados
            $files = $bucket->objects([
                'prefix' => "syllabus_results/{$syllabus->id}/",
            ]);

            $jsonData = null;
            foreach ($files as $file) {
                if (str_ends_with($file->name(), '.json')) {
                    $jsonContent = $file->downloadAsString();
                    $jsonData = json_decode($jsonContent, true);
                    break;
                }
            }

            if (!$jsonData) {
                throw new \Exception("No se encontraron resultados OCR en GCS.");
            }

            $text = '';
            foreach ($jsonData['responses'] as $page) {
                if (!empty($page['fullTextAnnotation']['text'])) {
                    $text .= $page['fullTextAnnotation']['text'] . "\n";
                }
            }

            if (!$text) {
                throw new \Exception("El OCR no devolvió texto.");
            }

            $syllabus->update(['raw_text' => $text]);
            Log::info("📝 OCR completado correctamente", [
                'syllabus_id' => $this->syllabusId,
                'chars' => strlen($text),
            ]);

            // 🤖 Extracción (OpenAI), curso y pivotes: compartida con la ingesta desde silabos_v2.
            // El estado 'processed' se marca recién después de sincronizar los pivotes.
            $extraction->process($syllabus);

        } catch (\Throwable $e) {
            Log::error("❌ Error en ProcessSyllabusJob: " . $e->getMessage(), [
                'syllabus_id' => $this->syllabusId,
            ]);

            $syllabus->update(['status' => 'failed']);
        }
    }
}
