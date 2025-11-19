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

                // ======== 🔥 PROMPT ESPECIALIZADO EN GRÁFICOS ==========
                $prompt = "
Eres un experto en detección de GRÁFICOS (charts) a partir de texto OCR.

Analiza el siguiente texto y detecta si contiene información de un gráfico
(barras, líneas, pastel, comparativos, series numéricas, etc).

Si NO hay gráficos → devuelve:
{
  \"graphs\": []
}

Si SÍ hay gráficos, devuelve formato JSON estricto:

{
  \"graphs\": [
    {
      \"title\": \"Nombre del gráfico si existe\",
      \"data\": {
         \"labels\": [\"2020\", \"2021\", \"2022\"],
         \"values\": [100, 120, 140]
      },
      \"insights\": [
         \"Descripción de qué representa el gráfico\",
         \"Conclusiones relevantes\"
      ]
    }
  ]
}

Evita inventar datos. Usa solo lo que esté en el texto.
Puede haber más de un gráfico.

Texto OCR:
{$page->text_content}
                ";
                // =========================================================

                $response = Http::withToken(env("OPENAI_API_KEY"))
                    ->post("https://api.openai.com/v1/chat/completions", [
                        "model" => "gpt-4o-mini",
                        "temperature" => 0.2,
                        "response_format" => ["type" => "json_object"],
                        "messages" => [
                            [
                                "role" => "system",
                                "content" => "Eres un analista que detecta gráficos y series de datos en OCR."
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
                    PdfPageGraph::create([
                        "page_id"      => $page->id,
                        "title"        => $g["title"] ?? null,
                        "data_json"    => $g["data"] ?? [],
                        "insights_json"=> $g["insights"] ?? [],
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

        Log::info("✅ [DetectGraphs] Completado para parte {$part->id}");
    }
}
