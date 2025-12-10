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

        // ================================================================
        // 1️⃣ UNIR TEXTO OCR
        // ================================================================
        $pages = $part->pages()->orderBy('page_number')->get();

        if ($pages->count() === 0) {
            Log::warning("⚠️ [Summary] Parte {$part->id} no tiene páginas OCR.");
            return;
        }

        $fullText = $pages->pluck('text_content')->join("\n\n");

        if (strlen(trim($fullText)) < 50) {
            Log::warning("⚠️ [Summary] Texto insuficiente. OCR débil.");
            $this->saveEmptySummary($part, "Texto demasiado corto para generar un resumen confiable.");
            return;
        }

        // ================================================================
        // 2️⃣ PROMPT SEGURO SIN INVENTOS
        // ================================================================
$prompt = "
Responde siempre en español.
Eres un analista especializado en resumir documentos de manera rigurosa y SIN INVENTAR información.

Reglas estrictas:
- SOLO usa contenido explícitamente presente en el texto OCR.
- NO inventes datos, NO completes con información externa.
- NO hagas suposiciones, NO generes ejemplos ficticios.
- Si el texto está incompleto, borroso o presenta lagunas, indícalo únicamente en \"notes\".
- Mantén un tono profesional, claro y formal.
- La respuesta debe ser SIEMPRE un JSON 100% válido.

Formato EXACTO que debes devolver:
{
  \"summary_short\": \"1–2 frases máximo basadas únicamente en el texto\",
  \"summary_medium\": \"Un párrafo que explique lo esencial del contenido\",
  \"summary_long\": \"Mínimo 180 palabras. Basado solo en el texto. Sin inventar nada.\",
  \"insights\": [\"lista de conclusiones extraídas SOLO del contenido real del texto\"],
  \"topics\": [\"lista de temas detectados explícitamente\"],
  \"notes\": [
     \"advertencias sobre OCR débil, texto faltante, posibles errores o secciones ilegibles\"
  ]
}

A continuación, tienes el TEXTO OCR que debes analizar sin modificar ni reinterpretar:
$fullText
";

        try {

            $response = Http::withToken(env("OPENAI_API_KEY"))
                ->post("https://api.openai.com/v1/chat/completions", [
                    "model" => "gpt-4o-mini",
                    "temperature" => 0,
                    "response_format" => ["type" => "json_object"],
                    "messages" => [
                        [
                            "role" => "system",
                            "content" => "Responde SIEMPRE en español. Nunca inventes datos."
                        ],
                        [
                            "role" => "user",
                            "content" => $prompt
                        ]
                    ]
                ]);

            $json = $response->json("choices.0.message.content");

            if (!$json) {
                Log::warning("⚠️ [Summary] La IA devolvió vacío.");
                $this->saveEmptySummary($part, "La IA devolvió una respuesta vacía.");
                return;
            }

            $decoded = json_decode($json, true);

            if (!$decoded) {
                Log::error("❌ [Summary] JSON inválido: ".json_last_error_msg());
                $this->saveEmptySummary($part, "JSON inválido generado por IA.");
                return;
            }

            // ================================================================
            // 3️⃣ VALIDACIONES PROFESIONALES
            // ================================================================
            $notes = $decoded["notes"] ?? [];

            if (strlen($decoded["summary_long"] ?? "") < 100) {
                $notes[] = "El resumen largo parece demasiado breve. Posible OCR incompleto.";
            }

            if (str_contains($decoded["summary_short"] ?? "", "no información")) {
                $notes[] = "La IA detectó posible falta de contenido suficiente.";
            }

            // ================================================================
            // 4️⃣ GUARDAR
            // ================================================================
            PdfPartSummary::updateOrCreate(
                ['part_id' => $part->id],
                [
                    "summary_short"  => $decoded["summary_short"]  ?? null,
                    "summary_medium" => $decoded["summary_medium"] ?? null,
                    "summary_long"   => $decoded["summary_long"]   ?? null,
                    "insights_json"  => $decoded["insights"]       ?? [],
                    "topics_json"    => $decoded["topics"]         ?? [],
                    "notes_json"     => $notes,
                ]
            );
$part->update([
    'summary_done' => 1,
    'step'         => 'summarized',
    'processed'    => 0.85,  // o el porcentaje que tú definas
]);

Log::info("📌 [Summary] Estado actualizado", [
    'part_id'   => $part->id,
    'step'      => 'summarized',
    'processed' => 0.85
]);

            Log::info("🟩 [Summary] Resumen guardado para parte {$part->id}");

        } catch (\Exception $e) {

            Log::error("❌ [Summary] Error en parte {$part->id}: " . $e->getMessage());
            $this->saveEmptySummary($part, "Error en comunicación con IA: ".$e->getMessage());
        }
    }

    private function saveEmptySummary($part, $note)
    {
        PdfPartSummary::updateOrCreate(
            ['part_id' => $part->id],
            [
                "summary_short"  => null,
                "summary_medium" => null,
                "summary_long"   => null,
                "insights_json"  => [],
                "topics_json"    => [],
                "notes_json"     => [$note],
            ]
        );
    }
}
