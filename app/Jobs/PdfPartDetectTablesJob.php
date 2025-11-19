<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use App\Models\PdfPage;
use App\Models\PdfPageTable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfPartDetectTablesJob implements ShouldQueue
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

        Log::info("📊 [DetectTables] Analizando tablas para parte {$part->id}");

        // Obtener páginas
        $pages = $part->pages()->orderBy('page_number')->get();

        foreach ($pages as $page) {

            try {

                if (!$page->text_content || strlen(trim($page->text_content)) < 20) {
                    Log::info("⚪ [DetectTables] Página {$page->id} sin texto suficiente, se omite.");
                    continue;
                }

                // 🔥 PROMPT para detectar tablas
                $prompt = "
Eres un detector de tablas.

Toma el siguiente texto OCR y responde SOLO EN JSON.

Extrae todas las tablas presentes.
Si no hay tablas, devuelve un array vacío.

Formato JSON estricto:
{
  \"tables\": [
    {
      \"rows\": [
         [\"col1\", \"col2\", \"col3\"],
         [\"val1\", \"val2\", \"val3\"]
      ],
      \"insights\": [\"texto libre sobre lo que significa la tabla\"]
    }
  ]
}

Texto OCR:
{$page->text_content}
                ";

                // 🔥 Llamada a OpenAI
                $response = Http::withToken(env('OPENAI_API_KEY'))
                    ->post("https://api.openai.com/v1/chat/completions", [
                        "model" => "gpt-4o-mini",
                        "temperature" => 0.1,
                        "response_format" => ["type" => "json_object"],
                        "messages" => [
                            ["role" => "system", "content" => "Eres un extractor experto de tablas en texto OCR."],
                            ["role" => "user", "content" => $prompt]
                        ]
                    ]);

                $json = $response->json("choices.0.message.content");

                if (!$json) {
                    Log::warning("⚠️ [DetectTables] Respuesta vacía para página {$page->id}");
                    continue;
                }

                $decoded = json_decode($json, true);

                if (!isset($decoded['tables']) || empty($decoded['tables'])) {
                    Log::info("▫️ [DetectTables] Página {$page->id} → sin tablas.");
                    continue;
                }

                // 🔥 Guardar tablas
                foreach ($decoded['tables'] as $t) {
                    PdfPageTable::create([
                        "page_id" => $page->id,
                        "data_json" => $t['rows'] ?? [],
                        "insights_json" => $t['insights'] ?? [],
                    ]);
                }

                Log::info("🟩 [DetectTables] Página {$page->id} → " . count($decoded['tables']) . " tablas detectadas.");

            } catch (\Exception $e) {

                Log::error("❌ [DetectTables] Error página {$page->id}: " . $e->getMessage());
                continue; // No detener toda la cadena
            }
        }

        Log::info("✅ [DetectTables] Completado para parte {$part->id}");
    }
}
