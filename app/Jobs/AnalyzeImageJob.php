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
use Illuminate\Support\Facades\Http;

class AnalyzeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $path;
    protected string $filename;
   protected string $mime;

    public function __construct(string $path, string $filename, string $mime)
    {
        $this->path = $path;
        $this->filename = $filename;
        $this->mime = $mime;
    }

    public function handle(): void
    {
        try {
            // 1. Ruta física del archivo en storage
            $imagePath = Storage::path($this->path);
            Log::info("📷 Imagen lista para OCR: {$imagePath}");

            // 2. Enviar a FastAPI (OCR GPU)
            $response = Http::attach('file', file_get_contents($imagePath), $this->filename)
                ->post('http://127.0.0.1:8000/ocr/image_dl', [
                    'lang'       => 'spa',
                    'force_gpu'  => true,   // ⬅️ nuevo flag en FastAPI
                    'preprocess' => true,   // ⬅️ mejora calidad
                ]);

            if (!$response->successful()) {
                throw new \Exception("OCR Service error: " . $response->body());
            }

            $data = $response->json();
            $text = $data['text'] ?? '';
            $engine = $data['engine'] ?? 'unknown';
            $gpu = $data['gpu'] ?? false;
            $tokens = $data['tokens'] ?? null;

            Log::info("📄 Texto OCR ({$engine}, GPU=" . ($gpu ? "yes" : "no") . "): " . mb_substr($text, 0, 200) . "...");

            // 3. Guardar análisis en DB
            $analysis = ImageAnalysis::create([
                'filename'  => $this->filename,
                'path'      => $this->path,
                'engine'    => $engine,
                'gpu'       => $gpu,
                'tokens'    => $tokens,
                'response'  => $text,
            ]);

            // 4. Emitir evento
            broadcast(new ImageAnalyzed($analysis));
            Log::info("📡 Evento ImageAnalyzed emitido", [
                'filename' => $analysis->filename,
                'engine'   => $engine,
            ]);

            // 5. Eliminar archivo temporal
            Storage::delete($this->path);

        } catch (\Throwable $e) {
            Log::error("❌ Error al analizar '{$this->filename}': " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
