<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use App\Models\PdfPage;
use App\Models\PdfPageGraph;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfPartDetectGraphsJob implements ShouldQueue
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

        Log::info("📈 [DetectGraphs] Analizando gráficos para parte {$part->id}");

        $pages = $part->pages()->orderBy('page_number')->get();

        foreach ($pages as $page) {

            try {

                if (!$page->text_content || strlen(trim($page->text_content)) < 20) {
                    Log::info("⚪ [DetectGraphs] Página {$page->id} sin texto suficiente, se omite.");
                    continue;
                }

                /* ----------------------------------------------------
                   PROMPT SEGURO · SIN INVENTOS · ESTRICTO
                ---------------------------------------------------- */
                $prompt = "
Analiza el siguiente texto OCR e identifica ÚNICAMENTE gráficos reales.
No inventes valores, no completes series faltantes, no interpolar.

REGLAS:
1. Usa solo números visibles en el OCR.
2. Si no hay valores numéricos suficientes → NO generes gráfico.
3. Si existe gráfico pero datos incompletos → devuélvelo con
   \"notes\": [\"datos insuficientes\", \"OCR parcial\", etc.]
4. Si NO hay gráficos → devuelve { \"graphs\": [] }
5. El JSON siempre debe ser válido.

FORMATO EXACTO:
{
  \"graphs\": [
    {
      \"title\": \"texto o null\",
      \"data\": {
         \"labels\": [\"A\", \"B\", \"C\"],
         \"values\": [10, 20, 30]
      },
      \"notes\": [
         \"observaciones del OCR o problemas detectados\"
      ]
    }
  ]
}

Texto OCR:
{$page->text_content}
                ";

                $response = Http::withToken(env("OPENAI_API_KEY"))
                    ->post("https://api.openai.com/v1/chat/completions", [
                        "model" => "gpt-4o-mini",
                        "temperature" => 0,
                        "response_format" => ["type" => "json_object"],
                        "messages" => [
                            [
                                "role" => "system",
                                "content" => "Eres un analista que detecta gráficos sin completar datos ni inventar valores."
                            ],
                            [
                                "role" => "user",
                                "content" => $prompt
                            ]
                        ]
                    ]);

                $json = $response->json("choices.0.message.content");

                if (!$json) {
                    Log::warning("⚠️ [DetectGraphs] Respuesta vacía página {$page->id}");
                    continue;
                }

                $decoded = json_decode($json, true);

                if (!isset($decoded['graphs']) || empty($decoded['graphs'])) {
                    Log::info("▫️ [DetectGraphs] Página {$page->id} → sin gráficos.");
                    continue;
                }

                foreach ($decoded['graphs'] as $g) {

                    $data = $g['data'] ?? [];
                    $labels = $data['labels'] ?? [];
                    $values = $data['values'] ?? [];
                    $notes = $g['notes'] ?? [];

                    /* ----------------------------------------------------
                       VALIDACIÓN · NO GUARDAR GRÁFICOS CORRUPTOS
                    ---------------------------------------------------- */

                    if (!is_array($labels) || !is_array($values)) {
                        Log::warning("❌ [DetectGraphs] Datos corruptos en página {$page->id}, se descarta.");
                        continue;
                    }

                    if (count($labels) < 2 || count($values) < 2) {
                        Log::warning("⚠️ [DetectGraphs] Serie demasiado corta en página {$page->id}");
                        $notes[] = "Datos insuficientes para graficar.";
                    }

                    if (count($labels) !== count($values)) {
                        Log::warning("❌ [DetectGraphs] labels y values NO coinciden en página {$page->id}");
                        $notes[] = "Labels y values no coinciden. OCR incompleto.";
                    }

                    PdfPageGraph::create([
                        "page_id"      => $page->id,
                        "title"        => $g["title"] ?? null,
                        "data_json"    => $data,
                        "insights_json"=> $notes,
                    ]);
                }

                Log::info(
                    "🟩 [DetectGraphs] Página {$page->id} → "
                    . count($decoded['graphs'])
                    . " gráficos detectados."
                );

            } catch (\Exception $e) {
                Log::error("❌ [DetectGraphs] Error página {$page->id}: " . $e->getMessage());
                continue;
            }
        }
$part->update([
    'graphs_done' => 1,
    'step'        => 'graphs_detected',
    'processed'   => 0.50,
]);

Log::info("📌 [DetectGraphs] Estado actualizado", [
    'part_id'   => $part->id,
    'step'      => 'graphs_detected',
    'processed' => 0.50
]);

        Log::info("✅ [DetectGraphs] Completado para parte {$part->id}");
    }
}
