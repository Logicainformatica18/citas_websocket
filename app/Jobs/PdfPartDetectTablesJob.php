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

        $pages = $part->pages()->orderBy('page_number')->get();

        foreach ($pages as $page) {

            try {

                if (!$page->text_content || strlen(trim($page->text_content)) < 20) {
                    Log::info("⚪ [DetectTables] Página {$page->id} sin texto suficiente, se omite.");
                    continue;
                }

                /* ----------------------------------------------------
                   PROMPT SEGURO (NO INVENTA, NO COMPLETA)
                ---------------------------------------------------- */
                $prompt = "
Analiza el siguiente texto OCR y extrae únicamente tablas REALES.

REGLAS:
1. NO inventes, NO completes valores faltantes.
2. Si la tabla está incompleta, devuelve solo lo que esté visible.
3. Si los datos están demasiado corruptos, devuelve una nota explicando por qué.
4. NO reestructures la tabla, NO infieras columnas.
5. Si NO hay tablas, devuelve: { \"tables\": [] }
6. Usa SIEMPRE JSON válido.

FORMATO:
{
  \"tables\": [
    {
      \"rows\": [
         [\"col1\", \"col2\", \"col3\"],
         [\"val1\", \"val2\", \"val3\"]
      ],
      \"notes\": [\"indica si faltan datos, valores ilegibles, OCR muy ruidoso\"]
    }
  ]
}

Texto OCR:
{$page->text_content}
                ";

                $response = Http::withToken(env('OPENAI_API_KEY'))
                    ->post("https://api.openai.com/v1/chat/completions", [
                        "model" => "gpt-4o-mini",
                        "temperature" => 0,
                        "response_format" => ["type" => "json_object"],
                        "messages" => [
                            ["role" => "system", "content" => "Eres un extractor experto de tablas basado únicamente en lo que realmente aparece en el OCR."],
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
                    Log::info("▫️ [DetectTables] Página {$page->id} → sin tablas detectadas.");
                    continue;
                }

                // VALIDACIÓN DE CADA TABLA
                foreach ($decoded['tables'] as $t) {

                    $rows = $t['rows'] ?? [];
                    $notes = $t['notes'] ?? [];

                    if (!is_array($rows) || empty($rows)) {
                        Log::warning("⚠️ [DetectTables] Tabla inválida en página {$page->id}. Se omite.");
                        continue;
                    }

                    // Validar que cada fila sea un array plano
                    $valid = true;
                    foreach ($rows as $r) {
                        if (!is_array($r)) {
                            $valid = false;
                            break;
                        }
                    }

                    if (!$valid) {
                        Log::warning("❌ [DetectTables] Tabla corrupta en página {$page->id}, no se guarda.");
                        continue;
                    }

                    // Guardar tabla
                    PdfPageTable::create([
                        "page_id"  => $page->id,
                        "data_json" => $rows,
                        "insights_json" => $notes,
                    ]);
                }

                Log::info("🟩 [DetectTables] Página {$page->id} → " . count($decoded['tables']) . " tabla(s) guardadas.");

            } catch (\Exception $e) {
                Log::error("❌ [DetectTables] Error en página {$page->id}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("✅ [DetectTables] Completado para parte {$part->id}");
    }
}
