<?php

namespace App\Jobs;

use App\Models\ScrapingWebResult;
use App\Models\ScrapingSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessWebResultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $resultId;

    public function __construct(int $resultId)
    {
        $this->resultId = $resultId;
    }

    public function handle()
    {
        $result = ScrapingWebResult::find($this->resultId);
        if (!$result) {
            Log::error("❌ No existe ScrapingWebResult con id {$this->resultId}");
            return;
        }

        $source = ScrapingSource::find($result->source_id);
        if (!$source || !$source->web_prompt) {
            Log::error("❌ No existe web_prompt en ScrapingSource {$result->source_id}");
            return;
        }

        try {
            Log::info("🌐 Procesando enlace hijo (ID={$result->id}): {$result->url}");

            // 1) Ejecutar Playwright
            $script = base_path('scraping/tech_scraper.mjs');
            $cmd = "node \"$script\" \"{$result->url}\" 2>&1";

            Log::info("🟦 Ejecutando comando Node", ['cmd' => $cmd]);

            $html = shell_exec($cmd);

            Log::info("🟧 Tamaño HTML: " . strlen($html ?? ''));

            if (!$html || strlen($html) < 50) {
                throw new Exception("HTML vacío o insuficiente.");
            }

            // 2) Prompt
            $prompt = <<<EOT
Usa el siguiente HTML real para extraer información estructurada.

PROMPT DEFINIDO POR EL USUARIO (PADRE):
{$source->web_prompt}

HTML:
===========================
$html
===========================

Devuelve únicamente JSON válido.
EOT;

            Log::info("🟦 Prompt enviado a GPT (200 chars): " . substr($prompt, 0, 200));

            // 3) Llamada OpenAI Responses API (formato correcto)
            $response = Http::withToken(env('OPENAI_API_KEY'))->post(
                'https://api.openai.com/v1/responses',
                [
                    'model' => 'gpt-4o-mini',
                    'input' => $prompt,
                    'temperature' => 0.1,
                    'text' => [
                        'format' => [
                            'type' => 'json_object'
                        ]
                    ],
                ]
            );

            Log::info("🟦 Respuesta completa de OpenAI: " . json_encode($response->json()));

            $raw = $response->json()['output_text'] ?? null;

            if (!$raw) {
                throw new Exception("La IA devolvió vacío. Respuesta completa: " . json_encode($response->json()));
            }

            Log::info("🟦 RAW IA (200 chars): " . substr($raw, 0, 200));

            // 4) Parsear JSON
            $json = json_decode($raw, true);

            if (!$json) {
                throw new Exception("JSON inválido: " . $raw);
            }

            Log::info("🟩 JSON parseado correctamente.");

            // 5) Actualizar registro
            $result->update([
                'raw_html'        => $html,
                'ai_raw_response' => $raw,
                'ai_json'         => $json,
                'status'          => 'completed',
                'error_message'   => null,
            ]);

            Log::info("✔ Procesado correctamente (ID={$result->id})");

        } catch (Exception $e) {

            Log::error("❌ ERROR procesando result {$result->id}: " . $e->getMessage());

            $result->update([
                'status'        => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
