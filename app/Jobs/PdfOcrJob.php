<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Models\PdfPage;
use App\Models\PdfChunk;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\OutputConfig;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;


class PdfOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $pdfId;

    public function __construct($pdfId)
    {
        $this->pdfId = $pdfId;
    }

    public function handle()
    {
        $pdf = PdfDocument::find($this->pdfId);

        if (!$pdf) {
            Log::error("PDF no encontrado ID {$this->pdfId}");
            return;
        }

        // Limpiar datos previos
        PdfPage::where('pdf_id', $pdf->id)->delete();
        PdfChunk::where('pdf_id', $pdf->id)->delete();

        // Path local
        $localPdfPath = Storage::disk('public')->path($pdf->file_path);

        $storage = new StorageClient([
            'projectId' => env('GCS_PROJECT_ID'),
            'keyFilePath' => env('GCS_KEY_FILE_PATH'),
        ]);

        $bucket = $storage->bucket(env('GCS_BUCKET'));

        // Subir PDF
        $gcsPath = "pdf_documents/{$pdf->id}.pdf";
        $bucket->upload(fopen($localPdfPath, 'r'), ['name' => $gcsPath]);

        $gcsInputUri = "gs://" . env('GCS_BUCKET') . "/{$gcsPath}";
        $gcsOutputUri = "gs://" . env('GCS_BUCKET') . "/pdf_results/{$pdf->id}/";

        // GCS Source
        $gcsSource = new GcsSource();
        $gcsSource->setUri($gcsInputUri);

        // GCS Destination
        $gcsDestination = new GcsDestination();
        $gcsDestination->setUri($gcsOutputUri);

        // Input Config
        $inputConfig = new InputConfig();
        $inputConfig->setMimeType('application/pdf');
        $inputConfig->setGcsSource($gcsSource);

        // Output Config
        $outputConfig = new OutputConfig();
        $outputConfig->setGcsDestination($gcsDestination);

        // Feature
        $feature = new Feature();
        $feature->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

        // Async Request
        $request = new AsyncAnnotateFileRequest();
        $request->setInputConfig($inputConfig);
        $request->setOutputConfig($outputConfig);
        $request->setFeatures([$feature]);

        $client = new ImageAnnotatorClient([
            'credentials' => env('GCS_KEY_FILE_PATH'),
        ]);

        // Lanzar operación
        $operation = $client->asyncBatchAnnotateFiles([$request]);

        for ($i = 0; $i < 30; $i++) {
            if ($operation->isDone()) break;
            sleep(6);
            $operation->reload();
        }

        if (!$operation->operationSucceeded()) {
            throw new \Exception("OCR falló");
        }

        // Descargar JSON resultante
        $files = $bucket->objects(['prefix' => "pdf_results/{$pdf->id}/"]);

        foreach ($files as $file) {
            if (!str_ends_with($file->name(), '.json')) continue;

            $data = json_decode($file->downloadAsString(), true);
            $responses = $data['responses'] ?? [];

            $pdf->total_pages = count($responses);
            $pdf->save();

            $index = 1;

            foreach ($responses as $page) {
                $text = $page['fullTextAnnotation']['text'] ?? '';

                PdfPage::create([
                    'pdf_id'      => $pdf->id,
                    'page_number' => $index,
                    'text_content'=> $text,
                ]);

                PdfChunk::create([
                    'pdf_id'      => $pdf->id,
                    'chunk_index' => $index,
                    'content'     => $text
                ]);

                $index++;
            }

            break;
        }

        // Lanzar siguiente job
        PdfClassifyPageJob::dispatch($pdf->id);
    }
}
