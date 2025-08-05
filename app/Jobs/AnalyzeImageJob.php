<?php

namespace App\Jobs;

use App\Models\ImageAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\ImageAnalyzed;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;

class AnalyzeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $path;
    protected string $filename;

    public function __construct(string $path, string $filename)
    {
        $this->path = $path;
        $this->filename = $filename;
    }

    public function handle(): void
    {
        try {
            // 1. Cargar credenciales
            $credPath = storage_path('app/eco-splicer-468114-t0-54c2adb26581.json');
            putenv("GOOGLE_APPLICATION_CREDENTIALS={$credPath}");
            Log::info('🔐 Credenciales cargadas desde: ' . $credPath);

            // 2. Obtener imagen
            $imagePath = Storage::path($this->path);
            $imageData = file_get_contents($imagePath);
            Log::info("📷 Imagen cargada correctamente: {$imagePath}");

            // 3. Inicializar cliente
            $client = new ImageAnnotatorClient();
            Log::info('✅ Cliente de Vision API inicializado');

            // 4. Preparar solicitud
            $image = (new Image())->setContent($imageData);
            $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);
            $request = (new AnnotateImageRequest())->setImage($image)->setFeatures([$feature]);
            $batchRequest = new BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$request]);

            // 5. Ejecutar OCR
            $batchResponse = $client->batchAnnotateImages($batchRequest);
            Log::info('📨 Solicitud enviada a la API de Vision');

            $annotation = $batchResponse->getResponses()[0]?->getFullTextAnnotation();
            $text = $annotation?->getText() ?? 'Sin texto detectado';
            Log::info('📄 Texto detectado: ' . mb_substr($text, 0, 150) . '...');

            // 6. Extraer campos
            $fields = $this->extractFields($text);

            // 7. Guardar en DB
          // Dentro de handle(), justo antes de guardar en DB:
$analysis = ImageAnalysis::create(array_merge([
    'filename'       => $this->filename,
    'path'           => $this->path,
    'response'       => $text, // ⬅️ Guarda todo el texto completo detectado
], $fields));


            broadcast(new ImageAnalyzed($analysis));
            Log::info('📡 Evento ImageAnalyzed emitido', [
                'filename' => $analysis->filename,
            ]);

            Storage::delete($this->path);
            $client->close();

        } catch (\Throwable $e) {
            Log::error("❌ Error al analizar la imagen '{$this->filename}': " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    private function extractFields(string $text): array
    {
        return [
            'company_name'      => preg_match('/([A-Z][\w\s]+S\.?A\.?C?)/i', $text, $m) ? trim($m[1]) : null,
            'operation_number'  => preg_match('/operaci[oó]n\s*(?:n[ºo]\.?|Nº)?\s*[:\-]?\s*(\d+)/i', $text, $m) ? $m[1] : null,
            'amount'            => preg_match('/S\/\.?\s*([\d,.]+)/i', $text, $m) ? str_replace(',', '', $m[1]) : null,
            'date'              => preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $text, $m) ? date('Y-m-d', strtotime(str_replace('/', '-', $m[1]))) : null,
            'time'              => preg_match('/(\d{1,2}:\d{2})\s*(?:a\.?m\.?|p\.?m\.?)/i', $text, $m) ? $m[1] : null,
            'phone'             => preg_match('/\b9\d{8}\b/', $text, $m) ? $m[0] : null,
            'status'            => str_contains(strtolower($text), 'no admitido') ? 'Not Admitted' : (str_contains(strtolower($text), 'admitido') ? 'Admitted' : null),
            'concept'           => preg_match('/concepto\s*[:\-]?\s*(.*)/i', $text, $m) ? trim($m[1]) : null,
        ];
    }
}
