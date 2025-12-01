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
     * Limpia HTML y extrae solo contenido útil
     */
    private function cleanHtml($html)
    {
        // 1️⃣ Remover scripts y styles
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // 2️⃣ Remover comentarios
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // 3️⃣ Quedarse solo con la parte central del contenido
        if (preg_match('/<(main|article|section)[^>]*>(.*?)<\/(main|article|section)>/is', $html, $match)) {
            $html = $match[0];
        }

        // 4️⃣ Reducir espacios
        $html = preg_replace('/\s+/', ' ', $html);

        // 5️⃣ Limitar tamaño a 20k chars
        return substr($html, 0, 20000);
    }

    public function handle()
    {
        $source = ScrapingSource::find($this->sourceId);
        if (!$source) return;

        $url = rtrim($source->url, '/');

        Log::info("🔍 ExtractLinksJob → $url");

        try {
            // 1️⃣ Obtener HTML
            $rawHtml = Http::timeout(20)->get($url)->body();
            $html = $this->cleanHtml($rawHtml);

            Log::info("📦 HTML limpio (chars=" . strlen($html) . ")");

            // 2️⃣ Extraer SOLO los enlaces visibles
            preg_match_all('/<a[^>]+href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/is', $html, $matches);

            $allLinks = [];

            for ($i = 0; $i < count($matches[1]); $i++) {
                $href = trim($matches[1][$i]);
                $text = strip_tags(trim($matches[2][$i] ?? ''));

                if ($text === "") continue;

                // Normalizamos URL absoluta
                $absoluteUrl = str_starts_with($href, "http")
                    ? $href
                    : $url . "/" . ltrim($href, "/");

                $allLinks[] = [
                    "url" => $absoluteUrl,
                    "text" => $text
                ];
            }

            // Limitar a 50 antes de mandar a GPT
            $allLinks = array_slice($allLinks, 0, 50);

            // 3️⃣ Prompt FIJO sin web_prompt
            $prompt = "
Analiza esta lista de enlaces extraídos desde una página web:

" . json_encode($allLinks, JSON_PRETTY_PRINT) . "

Tu tarea:

1. Selecciona SOLO los 10 enlaces más útiles para un Observatorio Tecnológico.
   Prioriza enlaces que traten sobre:
   - Inteligencia Artificial
   - Computación en la Nube
   - Ciberseguridad
   - Desarrollo de Software
   - Ciencia de Datos
   - Transformación Digital
   - Tendencias tecnológicas
   - Software empresarial
   - Herramientas de colaboración
   - Infraestructura
   - DevOps / InfraOps

2. Elimina enlaces triviales como:
   - login, register, home
   - publicidad
   - política de privacidad
   - navegación
   - enlaces repetidos

3. Devuelve SOLO JSON válido con el siguiente formato exacto:

{
  \"links\": [
    {
      \"url\": \"...\",
      \"title\": \"...\",
      \"category\": \"...\",
      \"reason\": \"por qué este enlace es útil para el Observatorio\"
    }
  ]
}

NO incluyas nada fuera del JSON.
";

            Log::info("📤 Enviando prompt a GPT (chars=" . strlen($prompt) . ")");

            // 4️⃣ Llamar a GPT
            $response = Http::timeout(60)
                ->withToken(env('OPENAI_API_KEY'))
                ->post("https://api.openai.com/v1/chat/completions", [
                    "model" => "gpt-4o-mini",
                    "messages" => [
                        ["role" => "system", "content" => "Eres un analista experto en extracción de enlaces útiles para observatorios tecnológicos."],
                        ["role" => "user", "content" => $prompt]
                    ],
                    "temperature" => 0,
                ])->json();

            $raw = $response["choices"][0]["message"]["content"] ?? null;

            if (!$raw) {
                throw new Exception("La IA devolvió vacío.");
            }

            // Limpiar codeblocks
            $raw = trim(preg_replace('/```json|```/i', '', $raw));

            $json = json_decode($raw, true);

            if (!$json || !isset($json["links"])) {
                throw new Exception("JSON inválido: " . substr($raw, 0, 200));
            }

            // 5️⃣ Guardar en BD evitando duplicados
            $inserted = 0;

            foreach ($json["links"] as $link) {

                if (!isset($link["url"])) continue;

                // Evitar duplicados
                $exists = ScrapingWebResult::where("source_id", $source->id)
                    ->where("url", $link["url"])
                    ->exists();

                if ($exists) continue;

                ScrapingWebResult::create([
                    "source_id" => $source->id,
                    "url" => $link["url"],
                    "title" => $link["title"] ?? null,
                    "category" => $link["category"] ?? null,
                    "reason" => $link["reason"] ?? null,
                    "ai_json" => $link,
                    "status" => "pending"
                ]);

                $inserted++;
            }

            Log::info("✔ Enlaces guardados: $inserted");

        } catch (Exception $e) {
            Log::error("❌ ERROR ExtractLinksJob: " . $e->getMessage());
        }
    }
}
