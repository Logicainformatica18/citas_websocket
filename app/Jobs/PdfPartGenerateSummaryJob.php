<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use App\Models\PdfPartSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfPartGenerateSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $partId;

    public function __construct(int $partId)
    {
        $this->partId = $partId;
    }

    public function handle()
    {
        $part = PdfDocumentPart::findOrFail($this->partId);

        Log::info("🧠 [Summary] Generando resumen para parte {$part->id}");

        // =====================================================================
        // 1️⃣ Unir todo el texto de las páginas de la parte
        // =====================================================================
        $pages = $part->pages()->orderBy('page_number')->get();

        if ($pages->count() === 0) {
            Log::warning("⚠️ [Summary] Parte {$part->id} no tiene páginas OCR.");
            return;
        }

        $fullText = $pages->pluck('text_content')->join("\n\n");

        if (strlen(trim($fullText)) < 30) {
            Log::warning("⚠️ [Summary] Texto insuficiente para resumen.");
            return;
        }

        // =====================================================================
        // 2️⃣ Prompt inteligente para extraer resumen corto, mediano, largo,
        //    insights clave y topics estructurados
        // =====================================================================

        $prompt = "
Eres un analista experto en documentos. A partir del texto OCR suministrado,
crea un resumen estructurado en este formato JSON:

{
  \"summary_short\": \"1–2 frases sobre los puntos más importantes\",
  \"summary_medium\": \"Un párrafo claro con ideas principales\",
  \"summary_long\": \"Resumen amplio de mínimo 180 palabras con contexto, explicaciones y conclusiones\",
  \"insights\": [
      \"Conclusión clave 1\",
      \"Conclusión clave 2\",
      \"Patrones relevantes 3\"
  ],
  \"topics\": [
      \"tema 1\",
      \"tema 2\",
      \"tema 3\"
  ]
}

Reglas:
- SOLO JSON válido.
- No inventes nada que no aparezca.
- Usa lenguaje formal y claro.
- insights = conclusiones interpretables.
- topics = temas principales detectados.

Texto OCR:
$fullText
        ";

        try {

            $response = Http::withToken(env("OPENAI_API_KEY"))
                ->post("https://api.openai.com/v1/chat/completions", [
                    "model" => "gpt-4o-mini",
                    "temperature" => 0.2,
                    "response_format" => ["type" => "json_object"],
                    "messages" => [
                        [
                            "role" => "system",
                            "content" => "Eres un analista que resume documentos en múltiples niveles."
                        ],
                        [
                            "role" => "user",
                            "content" => $prompt
                        ]
                    ]
                ]);

            $json = $response->json("choices.0.message.content");

            if (!$json) {
                Log::warning("⚠️ [Summary] Respuesta vacía.");
                return;
            }

            $decoded = json_decode($json, true);

            if (!$decoded) {
                Log::error("❌ [Summary] JSON inválido: " . json_last_error_msg());
                return;
            }

            // =====================================================================
            // 3️⃣ Guardar resumen en pdf_part_summaries
            // =====================================================================

            PdfPartSummary::updateOrCreate(
                ['part_id' => $part->id],
                [
                    "summary_short"  => $decoded["summary_short"]  ?? null,
                    "summary_medium" => $decoded["summary_medium"] ?? null,
                    "summary_long"   => $decoded["summary_long"]   ?? null,
                    "insights_json"  => $decoded["insights"]       ?? [],
                    "topics_json"    => $decoded["topics"]         ?? [],
                ]
            );

            Log::info("🟩 [Summary] Resumen guardado para parte {$part->id}");

        } catch (\Exception $e) {

            Log::error("❌ [Summary] Error en parte {$part->id}: " . $e->getMessage());
        }
    }
}
