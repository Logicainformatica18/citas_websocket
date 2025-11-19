<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use App\Models\PdfPage;
use App\Models\PdfDocument;
use Illuminate\Bus\Queueable;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;
use Google\Cloud\Vision\V1\OutputConfig;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PdfOcrChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // por chunk es suficiente
    protected $partId;

    public function __construct($partId)
    {
        $this->partId = $partId;
    }

    public function handle()
    {
        $part = PdfDocumentPart::with('pdf')->findOrFail($this->partId);
        $pdf = $part->pdf;

        Log::info("🔹 Procesando OCR chunk #{$part->part_number} del PDF {$pdf->id}");

        $localPath = storage_path("app/public/{$part->file_path}");

        if (!file_exists($localPath)) {
            throw new \Exception("El archivo chunk no existe: {$localPath}");
        }

        // 1️⃣ Subir chunk a Google Cloud Storage
        $gcsChunkUri = $this->uploadChunkToGcs($part, $localPath);

        // 2️⃣ Ejecutar OCR con Vision
        [$outPrefix, $pagesText] = $this->runOcrAndCollect($gcsChunkUri);

        // 3️⃣ Guardar resultados por página
        $this->savePages($part, $pagesText);

        // 4️⃣ Marcar chunk como procesado
        $part->update(['processed' => true]);

        // 5️⃣ Verificar si TODOS los chunks del documento están listos
        $this->checkIfDocumentIsFullyProcessed($pdf);

        Log::info("✔ OCR chunk #{$part->part_number} procesado correctamente.");
    }

    /* =======================================================================
     * GCS: Subir chunk
     * ======================================================================= */
    private function uploadChunkToGcs(PdfDocumentPart $part, string $localPath): string
    {
        $client = new StorageClient([
            'projectId' => env('GCP_PROJECT_ID'),
            'keyFilePath' => storage_path('app/eco-splicer-468114-t0-54c2adb26581.json'),
        ]);

        $bucketName = env('GCS_BUCKET');
        $bucket = $client->bucket($bucketName);

        $destPath = "pdf_chunks/{$part->pdf_id}/part_{$part->part_number}.pdf";

        $bucket->upload(fopen($localPath, 'r'), [
            'name' => $destPath
        ]);

        return "gs://{$bucketName}/{$destPath}";
    }

    /* =======================================================================
     * OCR: Vision Async
     * ======================================================================= */
    private function runOcrAndCollect(string $gcsUri): array
    {
        $uuid = Str::uuid();
        $outPrefix = "vision/pdf_parts/{$uuid}/";
        $gcsOutUri = "gs://" . env('GCS_BUCKET') . "/{$outPrefix}";

        $client = new ImageAnnotatorClient([
            'credentials' => storage_path('app/eco-splicer-468114-t0-54c2adb26581.json'),
        ]);

        $feature = new Feature();
        $feature->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

        // Configuración de entrada
        $inputConfig = new InputConfig();
        $inputConfig->setMimeType('application/pdf');
        $inputConfig->setGcsSource((new GcsSource())->setUri($gcsUri));

        // Configuración de salida
        $outputConfig = new OutputConfig();
        $outputConfig->setGcsDestination((new GcsDestination())->setUri($gcsOutUri));

        $request = new AsyncAnnotateFileRequest();
        $request->setInputConfig($inputConfig);
        $request->setFeatures([$feature]);
        $request->setOutputConfig($outputConfig);

        // Ejecutar
        $operation = $client->asyncBatchAnnotateFiles([$request]);
        $operation->pollUntilComplete();
        $client->close();

        if (!$operation->operationSucceeded()) {
            throw new \Exception("Error Vision OCR: " . $operation->getError()->getMessage());
        }

        // 📥 Leer resultados
        return [$outPrefix, $this->collectOcrOutput($outPrefix)];
    }

    /* =======================================================================
     * Leer JSON del OCR
     * ======================================================================= */
    private function collectOcrOutput(string $outPrefix): array
    {
        $client = new StorageClient([
            'projectId' => env('GCP_PROJECT_ID'),
            'keyFilePath' => storage_path('app/eco-splicer-468114-t0-54c2adb26581.json'),
        ]);

        $bucket = $client->bucket(env('GCS_BUCKET'));

        $pages = [];

        foreach ($bucket->objects(['prefix' => $outPrefix]) as $object) {
            if (!str_ends_with($object->name(), '.json')) continue;

            $data = json_decode($object->downloadAsString(), true);

            foreach ($data['responses'] ?? [] as $response) {
                $pageText = $response['fullTextAnnotation']['text'] ?? '';
                if ($pageText !== '') {
                    $pages[] = $pageText;
                }
            }
        }

        return $pages;
    }

    /* =======================================================================
     * Guardar páginas en BD
     * ======================================================================= */
    private function savePages(PdfDocumentPart $part, array $pagesText)
    {
        $pageNumber = $part->start_page;

        foreach ($pagesText as $text) {
            PdfPage::create([
                'pdf_id' => $part->pdf_id,
                'page_number' => $pageNumber,
                'text_content' => $text,
                'content_type' => 'text',
            ]);

            $pageNumber++;
        }
    }

    /* =======================================================================
     * Verificar si el documento entero terminó de procesarse
     * ======================================================================= */
    private function checkIfDocumentIsFullyProcessed(PdfDocument $pdf)
    {
        $pending = $pdf->parts()->where('processed', false)->count();

        if ($pending === 0) {
            Log::info("🎉 PDF {$pdf->id} COMPLETAMENTE procesado. Generando resumen…");

            // Encolar job para generar resumen global
            dispatch(new \App\Jobs\GeneratePdfSummaryJob($pdf->id));

            $pdf->update(['processed' => true]);
        }
    }
}
