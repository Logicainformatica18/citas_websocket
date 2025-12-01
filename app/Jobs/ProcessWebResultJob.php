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

            // 1️⃣ Descargar HTML sin Playwright (rápido, seguro, barato)
            $html = Http::timeout(20)->get($result->url)->body();

            if (!$html || strlen($html) < 50) {
                throw new Exception("HTML vacío o muy corto.");
            }

            // 2️⃣ Recortar HTML para no explotar tokens (sección útil)
            $cleanHtml = substr($html, 0, 15000);  // evita truncados

            // 3️⃣ Prompt limpio (sin incrustar HTML)
            $prompt = "
Eres un analista experto. Extrae información estructurada de la página enviada como documento.

PROMPT BASE DEFINIDO POR EL PADRE:
--------------------------------
{$source->web_prompt}
--------------------------------

Reglas:
- Usa solo el HTML entregado en documents.
- No inventes datos.
- Devuelve únicamente JSON válido.
";

            // 4️⃣ Llamada OpenAI usando /responses + documents (estándar ChatGPT)
            $response = Http::withToken(env('OPENAI_API_KEY'))->post(
                "https://api.openai.com/v1/responses",
                [
                    "model" => "gpt-4o-mini",
                    "input" => $prompt,
                    "documents" => [
                        [
                            "type" => "text",
                            "source" => "upload",
                            "content" => $cleanHtml
                        ]
                    ],
                    "temperature" => 0,
                ]
            )->json();

            $raw = $response["output_text"] ?? null;

            if (!$raw) {
                throw new Exception("La IA devolvió vacío.");
            }

            // 5️⃣ Parseo seguro
            $json = json_decode($raw, true);

            if (!$json) {
                throw new Exception("JSON inválido recibido.");
            }

            // 6️⃣ Guardar resultados
            $result->update([
                "raw_html"        => $cleanHtml,
                "ai_raw_response" => $raw,
                "ai_json"         => $json,
                "status"          => "completed",
                "error_message"   => null,
            ]);

            Log::info("✔ Procesado correctamente (ID={$result->id})");

        } catch (Exception $e) {

            Log::error("❌ ERROR procesando result {$result->id}: " . $e->getMessage());

            $result->update([
                "status"        => "error",
                "error_message" => $e->getMessage(),
            ]);
        }
    }
}
