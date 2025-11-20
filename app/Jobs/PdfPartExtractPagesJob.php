<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use App\Models\PdfPage;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfPartExtractPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $partId;

    public function __construct(int $partId)
    {
        $this->partId = $partId;
    }

    public function handle()
    {
        try {

            $part = PdfDocumentPart::findOrFail($this->partId);

            if (!$part->ocr_output_prefix) {
                Log::error("❌ [ExtractPages] ocr_output_prefix vacío en parte {$part->id}");
                return;
            }

            Log::info("📥 [ExtractPages] Leyendo JSON OCR de GCS", [
                'part_id' => $part->id,
                'prefix' => $part->ocr_output_prefix
            ]);

            // 1) Conectar a GCS
            $storage = new StorageClient([
                'projectId'   => env('GCS_PROJECT_ID'),
                'keyFilePath' => env('GCS_KEY_FILE_PATH'),
            ]);

            $bucket = $storage->bucket(env('GCS_BUCKET'));

            // 2) Listar JSON del OCR
            $objects = $bucket->objects(['prefix' => $part->ocr_output_prefix]);

            $pages = [];
            foreach ($objects as $obj) {

                if (!str_ends_with($obj->name(), '.json')) {
                    continue;
                }

                $content = $obj->downloadAsString();
                $json = json_decode($content, true);

                if (!$json) {
                    Log::warning("⚠️ [ExtractPages] JSON inválido: " . $obj->name());
                    continue;
                }

                foreach ($json['responses'] ?? [] as $pageResponse) {
                    $text = $pageResponse['fullTextAnnotation']['text'] ?? '';
                    $pages[] = $text;
                }
            }

            if (empty($pages)) {
                Log::warning("⚠️ [ExtractPages] No se encontraron páginas OCR en GCS para parte {$part->id}");
                return;
            }

            Log::info("📄 [ExtractPages] Total páginas OCR detectadas: " . count($pages));

            // 3) Borrar páginas previas (si se reprocesa)
            $part->pages()->delete();

            // 4) Guardar cada página en DB
            $pageNumber = 1;
            foreach ($pages as $text) {
                PdfPage::create([
                    'pdf_id'        => $part->pdf_id,
                    'part_id'       => $part->id,
                    'page_number'   => $pageNumber,
                    'text_content'  => $text,
                    'metadata_json' => json_encode([]),
                    'elements_json' => json_encode([]),
                ]);
                $pageNumber++;
            }

            // 5) Actualizar rango real del part
            $part->update([
                'start_page' => 1,
                'end_page'   => count($pages),
            ]);
 

            Log::info("✅ [ExtractPages] Páginas guardadas correctamente para parte {$part->id}");

        } catch (\Throwable $e) {

            Log::error("❌ [ExtractPages] ERROR FATAL en parte {$this->partId}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);

            // ❗ MUY IMPORTANTE: no relanzar el error para NO matar el worker
            // simplemente retornamos para que la chain continúe
            return;
        }
    }
}
