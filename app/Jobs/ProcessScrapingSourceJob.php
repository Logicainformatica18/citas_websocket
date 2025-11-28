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
        Log::info("🔍 INICIANDO DISCOVERY para SourceID {$this->sourceId}");

        $source = ScrapingSource::find($this->sourceId);

        if (!$source) {
            Log::warning("⚠️ ScrapingSource {$this->sourceId} no encontrado.");
            return;
        }

        try {

            if (!$source->url || !$source->web_prompt) {
                throw new Exception("URL o web_prompt no definido en la fuente.");
            }

            Log::info("🌐 URL objetivo: {$source->url}");

            /* ======================================================
               1) OBTENER HTML REAL CON PLAYWRIGHT
            ====================================================== */
            $scriptPath = base_path('scraping/tech_scraper.mjs');
            $command = "node \"$scriptPath\" \"{$source->url}\"";
            $html = shell_exec($command);

            if (!$html || strlen($html) < 50) {
                throw new Exception("Playwright devolvió HTML vacío o muy corto.");
            }

            Log::info("📥 Playwright entregó HTML real (" . strlen($html) . " chars)");

            /* ======================================================
               2) CONSTRUIR PROMPT USANDO TU web_prompt
            ====================================================== */

            $finalPrompt = "
Eres un scraper inteligente. Analiza el HTML real incluido abajo.

Instrucciones definidas por el usuario:
---------------------
{$source->web_prompt}
---------------------

Tu misión es devolver SOLO JSON válido.

IMPORTANTE:
- Respeta exactamente el formato solicitado por el usuario.
- Usa únicamente enlaces reales presentes en el HTML.
- NO inventes campos adicionales.
- NO devuelvas texto fuera del JSON.

================ HTML REAL ================
$html
===========================================
";

            /* ======================================================
               3) ENVIAR A OPENAI
            ====================================================== */
            Log::info("🤖 Enviando HTML a GPT para extracción…");

            $response = Http::withToken(env('OPENAI_API_KEY'))->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Devuelve SOLO JSON válido. Nada más.'],
                        ['role' => 'user', 'content' => $finalPrompt],
                    ],
                    'temperature' => 0.0,
                    'response_format' => ['type' => 'json_object'],
                ]
            );

            if ($response->failed()) {
                throw new Exception("Error HTTP desde OpenAI: " . $response->status());
            }

            $raw = $response->json()['choices'][0]['message']['content'] ?? null;
            if (!$raw) {
                throw new Exception("OpenAI devolvió contenido vacío.");
            }

            /* ======================================================
               4) DECODIFICAR JSON
            ====================================================== */
            $decoded = json_decode($raw, true);

            if (!$decoded) {
                throw new Exception("JSON inválido: " . json_last_error_msg());
            }

            if (!isset($decoded['links']) || !is_array($decoded['links'])) {
                throw new Exception("El JSON no contiene 'links'.");
            }

            /* ======================================================
               5) GUARDAR links EN scraping_web_results (PENDING)
            ====================================================== */
            $count = 0;

            foreach ($decoded['links'] as $link) {
                ScrapingWebResult::create([
                    'source_id'       => $source->id,
                    'url'             => $link['url'] ?? null,
                    'category'        => $link['category'] ?? null,
                    'raw_html'        => null,
                    'ai_raw_response' => null,
                    'ai_json'         => null,
                    'status'          => 'pending',
                    'error_message'   => null,
                ]);

                $count++;
            }

            Log::info("🏁 DISCOVERY COMPLETADO: {$count} enlaces guardados.");

        } catch (Exception $e) {

            Log::error("❌ ERROR en DISCOVERY: " . $e->getMessage());

            ScrapingWebResult::create([
                'source_id'     => $source->id,
                'url'           => $source->url,
                'status'        => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
