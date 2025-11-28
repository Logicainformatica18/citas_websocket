<?php

namespace App\Jobs;

use App\Models\ScrapingSource;
use App\Models\ScrapingWebResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ExtractLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $sourceId;

    public function __construct(int $sourceId)
    {
        $this->sourceId = $sourceId;
    }

    /**
     * Normaliza URLs relativas → absolutas
     */
    private function normalizeUrl(string $base, string $url): string
    {
        if (!$url) return $base;

        // ya es absoluta
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // URLs tipo //cdn...
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        // unimos con la base
        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    public function handle()
    {
        $source = ScrapingSource::find($this->sourceId);
        if (!$source) return;

        Log::info("🔍 Discovery → Extrayendo enlaces desde {$source->url}");

        try {

            // ======================================================
            // 1) Obtener HTML real usando Playwright
            // ======================================================
            $script = base_path('scraping/tech_scraper.mjs');
            $cmd = "node \"$script\" \"{$source->url}\"";

            $html = shell_exec($cmd);

            if (!$html || strlen($html) < 50) {
                throw new Exception("HTML vacío o insuficiente.");
            }

            // ======================================================
            // 2) Prompt simple para extracción de enlaces
            // ======================================================
            $prompt = <<<EOT
Eres un extractor de enlaces relevantes.

Analiza el HTML y devuelve SOLO JSON válido con este formato:

{
  "source_url": "{$source->url}",
  "links": [
    {
      "url": "https://...",
      "title": "Título corto",
      "category": "AI | Cloud | Security | Networking | Data | Software | DevOps"
    }
  ]
}

Reglas:
- No inventes enlaces.
- No incluyas login, footer, políticas, redes sociales.
- No enlaces repetidos.
- Extrae solo artículos, guías, páginas de contenido.
- URLs relativas deben mantenerse como están (las normalizo yo).
- Devuelve SOLO JSON.

HTML:
===================
$html
===================
EOT;

            // ======================================================
            // 3) OpenAI
            // ======================================================
            $response = Http::withToken(env('OPENAI_API_KEY'))->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Extraes enlaces y devuelves JSON limpio.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.0,
                    'response_format' => ['type' => 'json_object'],
                ]
            );

            $raw = $response->json()['choices'][0]['message']['content'] ?? null;
            if (!$raw) throw new Exception("La IA no devolvió contenido.");

            // ======================================================
            // 4) Decodificar JSON
            // ======================================================
            $decoded = json_decode($raw, true);
            if (!$decoded || !isset($decoded['links'])) {
                throw new Exception("JSON inválido (no contiene links).");
            }

            // ======================================================
            // 5) Insertar enlaces normalizados
            // ======================================================
            $links = $decoded['links'];

            foreach ($links as $link) {

                $normalizedUrl = $this->normalizeUrl($source->url, $link['url']);

                ScrapingWebResult::create([
                    'source_id'       => $source->id,
                    'url'             => $normalizedUrl,
                    'category'        => $link['category'] ?? null,
                    'ai_json'         => null,
                    'ai_raw_response' => null,
                    'raw_html'        => null,
                    'status'          => 'pending',
                ]);
            }

            Log::info("✔ Discovery completado con " . count($links) . " enlaces agregados.");

        } catch (Exception $e) {

            Log::error("❌ ERROR Discovery: " . $e->getMessage());

            ScrapingWebResult::create([
                'source_id'     => $source->id,
                'url'           => $source->url,
                'status'        => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
