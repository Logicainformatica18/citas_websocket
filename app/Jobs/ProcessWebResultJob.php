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
Eres un analista especializado en extraer información estructurada desde páginas web.
Recibirás el HTML de la página en 'documents'. Tu misión es transformar el contenido
en datos estructurados útiles para un Observatorio Tecnológico y Académico.

EXTRAE SOLO LO QUE REALMENTE APARECE. NO INVENTES NADA.

Devuelve EXCLUSIVAMENTE JSON válido con la siguiente estructura:

{
  \"source_url\": \"URL procesada\",
  \"title\": \"título detectado o null\",
  \"summary\": \"una frase corta que resuma el artículo (extraída del html)\",

  \"technologies\": [
    {
      \"name\": \"nombre de la tecnología\",
      \"category\": \"lenguaje | framework | cloud | devops | ai | data | security | methodology | other\",
      \"description\": \"frase breve obtenida del contenido\",
      \"relevance\": \"alta | media | baja\"
    }
  ],

  \"certifications\": [
    {
      \"name\": \"nombre de certificación\",
      \"provider\": \"organización o vendor real mencionado en el html\",
      \"description\": \"para qué sirve (extraído del contenido)\",
      \"level\": \"básico | intermedio | avanzado | experto | no indicado\"
    }
  ],

  \"trends\": [
    {
      \"trend\": \"nombre de una tendencia mencionada en el contenido\",
      \"type\": \"technology | skill | role | market | methodology | other\",
      \"signal_strength\": \"explícita si el texto dice 'creciente', 'alta demanda', 'tendencia', etc.\",
      \"evidence\": \"párrafo o frase exacta extraída del html (obligatorio)\"
    }
  ],

  \"skills\": [
    {
      \"name\": \"nombre de habilidad o competencia mencionada\",
      \"type\": \"technical | soft | methodological | other\",
      \"context\": \"explicación breve basada en el texto\"
    }
  ],

  \"roles\": [
    {
      \"name\": \"rol profesional mencionado en el contenido\",
      \"demand_signal\": \"alta | media | baja | no indicada\",
      \"context\": \"por qué aparece según el artículo\"
    }
  ],

  \"metadata\": {
    \"language_detected\": \"idioma detectado\",
    \"year_detected\": \"año mencionado explícitamente o null\",
    \"html_tokens\": \"no completar\"
  }
}

REGLAS IMPORTANTES:
- NO resumas el contenido en texto libre: solo datos estructurados.
- NO inventes tecnologías, certificaciones ni tendencias.
- Si un campo no existe, devuélvelo como null o lista vacía.
- El JSON debe ser 100% válido y parseable.
- Debes basarte solo en el documento HTML entregado.
";


     $response = Http::withToken(env('OPENAI_API_KEY'))->post(
    'https://api.openai.com/v1/chat/completions',
    [
        "model" => "gpt-4o-mini",
        "temperature" => 0,
        "messages" => [
            [
                "role" => "system",
                "content" => "Eres un analista experto en extraer datos estructurados desde HTML. Devuelve SOLO JSON válido y no inventes nada."
            ],
            [
                "role" => "user",
                "content" => $prompt . "\n\nContenidoHTML:\n" . $cleanHtml
            ]
        ]
    ]
)->json();

// Obtener respuesta
$raw = $response["choices"][0]["message"]["content"] ?? null;

if (!$raw) {
    throw new Exception("La IA devolvió vacío o no procesó el HTML.");
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
