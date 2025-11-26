<?php

namespace App\Jobs;

use App\Models\ScrapingSource;
use App\Models\ScrapingWebResult;
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
        Log::info("🔥 INICIANDO JOB WebScraping para SourceID {$this->sourceId}");

        $source = ScrapingSource::find($this->sourceId);

        if (!$source) {
            Log::warning("⚠️ ScrapingSource {$this->sourceId} no encontrado.");
            return;
        }

        try {

            /* ============================================================
               VALIDACIONES
            ============================================================ */
            if (!$source->url || !$source->web_prompt) {
                throw new Exception("URL o prompt no definido en la fuente.");
            }

            Log::info("🌐 URL objetivo: {$source->url}");

            /* ============================================================
               1) OBTENER HTML (opcional)
            ============================================================ */
            $html = null;

            try {
                $html = Http::timeout(20)->get($source->url)->body();
                Log::info("📥 HTML obtenido correctamente (" . strlen($html) . " chars)");
            } catch (\Throwable $e) {
                Log::warning("⚠️ No se pudo obtener HTML, GPT trabajará solo con URL.");
            }

            /* ============================================================
               2) CONSTRUIR PROMPT FINAL
            ============================================================ */
            $fullPrompt = "
Realiza scraping inteligente según estas instrucciones del usuario:

{$source->web_prompt}

URL principal:
{$source->url}

Si el HTML está disponible, úsalo:
" . ($html ?: "[NO HTML DISPONIBLE]") . "

Recuerda: devuelve SOLO JSON válido.
";

            /* ============================================================
               3) LLAMAR A OPENAI
            ============================================================ */

            Log::info("🤖 Enviando solicitud a GPT…");

            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un scraper inteligente que devuelve SOLO JSON válido.'],
                        ['role' => 'user', 'content' => $fullPrompt]
                    ],
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                throw new Exception("Error HTTP desde OpenAI: " . $response->status());
            }

            $body = $response->json();

            if (!isset($body['choices'][0]['message']['content'])) {
                throw new Exception("Respuesta inválida (sin choices)");
            }

            $raw = $body['choices'][0]['message']['content'];

            Log::info("🧠 RAW RESPONSE:", ['raw' => $raw]);


            /* ============================================================
               4) LIMPIEZA JSON
            ============================================================ */
            $clean = trim($raw);
            $clean = preg_replace('/^```(json)?/i', '', $clean);
            $clean = preg_replace('/```$/', '', $clean);
            $clean = trim($clean);

            $decoded = json_decode($clean, true);

            if (!$decoded) {
                throw new Exception("JSON inválido: " . json_last_error_msg());
            }

            Log::info("🧩 JSON procesado correctamente.");


            /* ============================================================
               5) GUARDAR RESULTADOS EN scraping_web_results
            ============================================================ */

            if (!isset($decoded['sub_results']) || !is_array($decoded['sub_results'])) {
                throw new Exception("El JSON no contiene 'sub_results'.");
            }

            foreach ($decoded['sub_results'] as $item) {

                ScrapingWebResult::create([
                    'source_id'        => $source->id,
                    'url'              => $item['url'] ?? $source->url,
                    'raw_html'         => $html,
                    'ai_raw_response'  => $raw,
                    'ai_json'          => $item, // cast automático
                    'status'           => 'completed',
                    'error_message'    => null,
                ]);
            }

            Log::info("🏁 Scraping completado correctamente — se guardaron "
                . count($decoded['sub_results']) . " subresultados.");

        } catch (Exception $e) {

            Log::error("❌ ERROR en scraping Web: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // NO guardamos en la tabla padre ya, solo log
            ScrapingWebResult::create([
                'source_id'       => $source->id,
                'url'             => $source->url,
                'raw_html'        => $html ?? null,
                'ai_raw_response' => null,
                'ai_json'         => null,
                'status'          => 'error',
                'error_message'   => $e->getMessage(),
            ]);
        }
    }
}
