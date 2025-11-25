<?php

namespace App\Jobs;

use App\Models\ScrapingSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class ProcessScrapingSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $sourceId;

    public function __construct($sourceId)
    {
        $this->sourceId = $sourceId;
    }

    public function handle()
    {
        Log::info("🔥 INICIANDO JOB ProcessScrapingSourceJob para ID {$this->sourceId}");

        $source = ScrapingSource::find($this->sourceId);

        if (!$source) {
            Log::warning("⚠️ ScrapingSource {$this->sourceId} no encontrado.");
            return;
        }

        Log::info("🚀 Procesando scraping para ID {$source->id}");

        $source->update([
            'scrape_status'  => 'Procesando...',
            'scrape_message' => 'Procesando…',
            'scrape_result'  => null,
        ]);

        try {

            /* ============================================================
               VALIDACIONES
            ============================================================ */
            if (!$source->url || !$source->web_prompt) {
                Log::error("❌ URL o prompt no definidos", [
                    'url' => $source->url,
                    'prompt' => $source->web_prompt
                ]);
                throw new Exception("URL o prompt no definido.");
            }

            Log::info("🌐 URL objetivo: {$source->url}");
            Log::info("🧠 Prompt del usuario: {$source->web_prompt}");

            /* ============================================================
               1) INTENTAR LEER HTML
            ============================================================ */
            $html = null;

            try {
                $html = Http::timeout(20)->get($source->url)->body();
                Log::info("📥 HTML obtenido correctamente (".strlen($html)." caracteres)");
            } catch (\Throwable $e) {
                Log::warning("⚠️ No se pudo obtener HTML. GPT usará solo la URL.");
            }

            /* ============================================================
               2) CONSTRUIR EL PROMPT FINAL
            ============================================================ */
            $fullPrompt = "
Realiza scraping inteligente de la siguiente URL:

{$source->url}

Usa exactamente estas instrucciones del usuario:

{$source->web_prompt}

Si está disponible, se incluye el HTML extraído:

" . ($html ?: '[NO HTML DISPONIBLE]') . "

Devuelve SOLO JSON estricto.
";

            Log::info("📝 Prompt final preparado para GPT.");

            /* ============================================================
               3) LLAMAR AL MODELO OPENAI
            ============================================================ */

            Log::info("🤖 Enviando solicitud a OpenAI…");

            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un scraper inteligente. Devuelve SOLO JSON válido.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $fullPrompt
                        ],
                    ],
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]);

            Log::info("📡 Respuesta recibida de OpenAI", [
                'status' => $response->status(),
            ]);

            // ⚠️ 1) ERROR HTTP
            if ($response->failed()) {
                Log::error("❌ Error HTTP en respuesta OpenAI", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new Exception("OpenAI devolvió un error HTTP: {$response->status()}");
            }

            // ⚠️ 2) RESPUESTA SIN CHOICES (CASO MÁS COMÚN)
            $body = $response->json();

            if (!isset($body['choices'][0]['message']['content'])) {
                Log::error("❌ OpenAI no devolvió 'choices'", [
                    'response' => $body
                ]);
                throw new Exception("OpenAI devolvió una respuesta inválida (sin choices)");
            }

            $raw = $body['choices'][0]['message']['content'];

            Log::info("🔥 RAW GPT RESPONSE:", ['raw' => $raw]);

            /* ============================================================
               4) LIMPIEZA DEL JSON
            ============================================================ */
            $clean = trim($raw);
            $clean = preg_replace('/^```(json)?/i', '', $clean);
            $clean = preg_replace('/```$/', '', $clean);
            $clean = trim($clean);

            Log::info("🧽 JSON LIMPIO:", ['clean' => $clean]);

            $decoded = json_decode($clean, true);

            if (!$decoded) {
                Log::error("❌ JSON inválido: ".json_last_error_msg());
                throw new Exception("GPT no devolvió JSON válido: ".$clean);
            }

            Log::info("✅ JSON decodeado correctamente", ['keys' => array_keys($decoded)]);

            /* ============================================================
               5) GUARDAR RESULTADOS
            ============================================================ */

            $source->update([
                'scrape_status'   => 'Procesado',
                'scrape_message'  => 'OK',
                'scrape_result'   => json_encode($decoded, JSON_PRETTY_PRINT),
         'last_scraped_at' => now(),

            ]);

            Log::info("🏁 Scraping completado exitosamente para ID {$source->id}");

        } catch (Exception $e) {

            Log::error("❌ ERROR en scraping {$source->id}: ".$e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $source->update([
                'scrape_status'  => 'error',
                'scrape_message' => $e->getMessage(),
                'scrape_error'   => $e->getTraceAsString(),
            ]);
        }
    }
}
