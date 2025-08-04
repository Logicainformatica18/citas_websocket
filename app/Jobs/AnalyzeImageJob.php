<?php

namespace App\Jobs;

use App\Models\ImageAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\ImageAnalyzed;

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
            $imagePath = Storage::path($this->path);
            $base64 = base64_encode(file_get_contents($imagePath));

            $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Describe brevemente el contenido del voucher.'],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$this->mime};base64,{$base64}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.2,
            ]);

            $content = $response->json('choices.0.message.content');

            $analysis = ImageAnalysis::create([
                'filename' => $this->filename,
                'response' => $content ?? 'Sin respuesta del modelo',
            ]);
            Log::info('📡 Evento ImageAnalyzed emitido por websocket', [
                'filename' => $analysis->filename,
                'response' => $analysis->response,
            ]);
            broadcast(new ImageAnalyzed($analysis));

            Storage::delete($this->path); // Limpieza opcional

        } catch (\Throwable $e) {
            Log::error("Error al procesar la imagen {$this->filename}: " . $e->getMessage());
        }
    }
}
