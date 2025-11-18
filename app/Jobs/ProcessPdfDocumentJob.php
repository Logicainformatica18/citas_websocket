<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Models\PdfPage;
use App\Models\PdfGraph;
use App\Models\PdfTable;
use App\Models\PdfChunk;
use App\Models\PdfSummary;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\OutputConfig;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;

use OpenAI\Laravel\Facades\OpenAI;

class ProcessPdfDocumentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Dispatchable;

    public $pdfId;

    public function __construct($pdfId)
    {
        $this->pdfId = $pdfId;
    }

    public function handle(): void
    {
        $pdf = PdfDocument::find($this->pdfId);

        if (!$pdf) {
            Log::error("❌ PDF con ID {$this->pdfId} no encontrado.");
            return;
        }

        Log::info("📄 Procesando PDF {$pdf->id}: {$pdf->title}");

        try {

            /**************************************************************
             * 0️⃣ ELIMINAR DATOS PREVIOS EN ORDEN CORRECTO
             **************************************************************/
            PdfGraph::whereIn('pdf_page_id',
                PdfPage::where('pdf_id', $pdf->id)->pluck('id')
            )->delete();

            PdfTable::whereIn('pdf_page_id',
                PdfPage::where('pdf_id', $pdf->id)->pluck('id')
            )->delete();

            PdfPage::where('pdf_id', $pdf->id)->delete();

            PdfChunk::where('pdf_id', $pdf->id)->delete();

            PdfSummary::where('pdf_id', $pdf->id)->delete();


            /**************************************************************
             * 1️⃣ LEER PDF DESDE DISCO
             **************************************************************/
            $localPdfPath = Storage::disk('public')->path($pdf->file_path);

            if (!file_exists($localPdfPath)) {
                throw new \Exception("El archivo físico no existe: {$localPdfPath}");
            }

            $gcsPath = "pdf_documents/{$pdf->id}.pdf";

            $storage = new StorageClient([
                'projectId'   => env('GCS_PROJECT_ID'),
                'keyFilePath' => env('GCS_KEY_FILE_PATH'),
            ]);

            $bucket = $storage->bucket(env('GCS_BUCKET'));

            // subir PDF
            $bucket->upload(
                fopen($localPdfPath, 'r'),
                ['name' => $gcsPath]
            );

            $gcsInputUri       = "gs://" . env('GCS_BUCKET') . "/{$gcsPath}";
            $gcsDestinationUri = "gs://" . env('GCS_BUCKET') . "/pdf_results/{$pdf->id}/";

            Log::info("☁️ PDF subido a GCS", ['uri' => $gcsInputUri]);


            /**************************************************************
             * 2️⃣ CONFIGURAR OCR ASYNC
             **************************************************************/
            $gcsSource = new GcsSource([
                'uri' => $gcsInputUri
            ]);

            $inputConfig = new InputConfig([
                'mimeType'  => 'application/pdf',
                'gcsSource' => $gcsSource
            ]);

            $gcsDestination = new GcsDestination([
                'uri' => $gcsDestinationUri
            ]);

            $outputConfig = new OutputConfig([
                'gcsDestination' => $gcsDestination
            ]);

            $feature = new Feature([
                'type' => Feature\Type::DOCUMENT_TEXT_DETECTION
            ]);

            $request = new AsyncAnnotateFileRequest([
                'inputConfig'  => $inputConfig,
                'features'     => [$feature],
                'outputConfig' => $outputConfig,
            ]);

            $client = new ImageAnnotatorClient([
                'credentials' => env('GCS_KEY_FILE_PATH'),
            ]);

            Log::info("🚀 Iniciando OCR async…");

            $operation = $client->asyncBatchAnnotateFiles([$request]);

            $maxAttempts = 30;
            $interval    = 6;

            for ($i = 1; $i <= $maxAttempts; $i++) {
                if ($operation->isDone()) break;

                Log::info("⌛ Esperando OCR... intento {$i}/{$maxAttempts}");
                sleep($interval);
                $operation->reload();
            }

            if (!$operation->operationSucceeded()) {
                throw new \Exception("OCR falló: " . ($operation->getError()->getMessage() ?? 'desconocido'));
            }

            Log::info("✔ OCR finalizado para PDF {$pdf->id}");


            /**************************************************************
             * 3️⃣ LEER JSON DEL OCR
             **************************************************************/
            $files = $bucket->objects([
                'prefix' => "pdf_results/{$pdf->id}/"
            ]);

            $jsonData = null;

            foreach ($files as $file) {
                if (str_ends_with($file->name(), '.json')) {
                    $jsonData = json_decode($file->downloadAsString(), true);
                    break;
                }
            }

            if (!$jsonData) {
                throw new \Exception("No se encontró JSON OCR en GCS.");
            }


            /**************************************************************
             * 4️⃣ CREAR PÁGINAS
             **************************************************************/
            $responses = $jsonData['responses'] ?? [];

            $pdf->total_pages = count($responses);
            $pdf->save();

            Log::info("📄 Total páginas detectadas: {$pdf->total_pages}");

            $chunkIndex = 1;

            foreach ($responses as $index => $pageResponse) {

                $text = $pageResponse['fullTextAnnotation']['text'] ?? '';

                $page = PdfPage::create([
                    'pdf_id'      => $pdf->id,
                    'page_number' => $index + 1,
                    'text_content'=> $text,
                    'ai_processed'=> false,
                ]);

                PdfChunk::create([
                    'pdf_id'      => $pdf->id,
                    'chunk_index' => $chunkIndex++,
                    'content'     => $text
                ]);
            }

            // recargar relación
            $pdf->load('pages');


            /**************************************************************
             * 5️⃣ GPT-MINI: CLASIFICAR PÁGINAS
             **************************************************************/
            foreach ($pdf->pages as $page) {

                $resp = OpenAI::chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' =>
"Clasifica esta página (de un PDF). Devuelve JSON:

{
  \"contains_graph\": bool,
  \"contains_table\": bool,
  \"contains_text\": bool,
  \"content_type\": \"text|graph|table|mixed|empty\"
}

Texto:
{$page->text_content}
"
                        ]
                    ]
                ]);

                $class = json_decode($resp['choices'][0]['message']['content'], true);

                $page->update([
                    'content_type'      => $class['content_type'] ?? 'text',
                    'detected_elements' => $class ?? [],
                ]);
            }


            /**************************************************************
             * 6️⃣ GPT "VISION" SIMULADA (solo con texto)
             **************************************************************/
            foreach ($pdf->pages as $page) {

                if ($page->detected_elements['contains_graph'] ?? false) {

                    Log::info("📊 Extrayendo gráfico (texto) p. {$page->page_number}");

                    $resp = OpenAI::chat()->create([
                        "model" => "gpt-4o",
                        "messages" => [
                            [
                                "role" => "user",
                                "content" =>
"Extrae gráfico desde el siguiente texto como JSON:

{
  \"title\": string|null,
  \"legend\": [],
  \"data\": [
    { \"label\": string, \"value\": number }
  ],
  \"insights\": []
}

Texto:
{$page->text_content}"
                            ]
                        ]
                    ]);

                    $graph = json_decode($resp['choices'][0]['message']['content'], true);

                    if (!empty($graph['data'])) {
                        PdfGraph::create([
                            'pdf_page_id'  => $page->id,
                            'title'        => $graph['title'] ?? null,
                            'data_json'    => $graph['data'],
                            'legend_json'  => $graph['legend'] ?? null,
                            'insights_json'=> $graph['insights'] ?? null,
                        ]);
                    }
                }


                if ($page->detected_elements['contains_table'] ?? false) {

                    Log::info("📋 Extrayendo tabla p. {$page->page_number}");

                    $resp = OpenAI::chat()->create([
                        "model" => "gpt-4o",
                        "messages" => [
                            [
                                "role" => "user",
                                "content" =>
"Extrae tabla del texto como JSON:

{
  \"headers\": [],
  \"rows\": [],
  \"insights\": []
}

Texto:
{$page->text_content}"
                            ]
                        ]
                    ]);

                    $table = json_decode($resp['choices'][0]['message']['content'], true);

                    if (!empty($table['rows'])) {
                        PdfTable::create([
                            'pdf_page_id'  => $page->id,
                            'data_json'    => $table['rows'],
                            'insights_json'=> $table['insights'] ?? null,
                        ]);
                    }
                }
            }


            /**************************************************************
             * 7️⃣ RESUMEN GLOBAL
             **************************************************************/
            Log::info("🧠 Generando resumen global…");

            $mergedText = PdfChunk::where('pdf_id', $pdf->id)
                ->orderBy('chunk_index')
                ->pluck('content')
                ->implode("\n\n");

            $resp = OpenAI::chat()->create([
                "model" => "gpt-4o",
                "messages" => [
                    [
                        "role" => "user",
                        "content" =>
"Genera un resumen en JSON con:

- summary_short (5 líneas)
- summary_medium (2 párrafos)
- summary_long (1 página)
- insights (lista JSON)
- topics (lista JSON)

Texto:
{$mergedText}"
                    ]
                ]
            ]);

            $summary = json_decode($resp['choices'][0]['message']['content'], true);

            PdfSummary::create([
                'pdf_id'         => $pdf->id,
                'summary_short'  => $summary['summary_short'] ?? null,
                'summary_medium' => $summary['summary_medium'] ?? null,
                'summary_long'   => $summary['summary_long'] ?? null,
                'insights_json'  => $summary['insights'] ?? null,
                'topics_json'    => $summary['topics'] ?? null,
            ]);


            /**************************************************************
             * 9️⃣ FINALIZAR
             **************************************************************/
            $pdf->update(['processed' => true]);

            Log::info("🎉 PDF {$pdf->id} procesado exitosamente.");

        } catch (\Exception $e) {

            Log::error("❌ Error procesando PDF {$pdf->id}: " . $e->getMessage());

            $pdf->update(['processed' => false]);
        }
    }
}
