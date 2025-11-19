<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Models\PdfSummary;
use App\Models\PdfPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use OpenAI;

class GeneratePdfSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    protected $pdfId;

    public function __construct($pdfId)
    {
        $this->pdfId = $pdfId;
    }

    public function handle()
    {
        $pdf = PdfDocument::findOrFail($this->pdfId);

        Log::info("🧠 Generando resumen global del PDF {$pdf->id}");

        // 1️⃣ Obtener TODO el texto del PDF ya procesado
        $allText = PdfPage::where('pdf_id', $pdf->id)
            ->orderBy('page_number', 'asc')
            ->pluck('text_content')
            ->implode("\n\n");

        if (trim($allText) === '') {
            Log::error("PDF {$pdf->id} no tiene texto para resumir.");
            return;
        }

        // 2️⃣ Mandarlo a OpenAI para análisis completo
        $summary = $this->generateSummaryFromAI($allText);

        // 3️⃣ Guardar resumen
        PdfSummary::updateOrCreate(
            ['pdf_id' => $pdf->id],
            [
                'summary_short'  => $summary['summary_short'] ?? null,
                'summary_medium' => $summary['summary_medium'] ?? null,
                'summary_long'   => $summary['summary_long'] ?? null,
                'insights_json'  => json_encode($summary['insights'] ?? []),
                'topics_json'    => json_encode($summary['topics'] ?? []),
            ]
        );

        // 4️⃣ Marcar documento como procesado
        $pdf->update(['processed' => true]);

        Log::info("✔ Resumen global generado para PDF {$pdf->id}");
    }

    /* =======================================================================
     * GPT-4o Mini: Generar Resumen Global
     * ======================================================================= */
    private function generateSummaryFromAI(string $text): array
    {
        try {
            $client = OpenAI::client(env('OPENAI_API_KEY'));

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'response_format' => [
                    "type" => "json_schema",
                    "json_schema" => [
                        "name" => "pdf_summary_schema",
                        "schema" => [
                            "type" => "object",
                            "properties" => [
                                "summary_short" => ["type" => "string"],
                                "summary_medium" => ["type" => "string"],
                                "summary_long" => ["type" => "string"],
                                "insights" => [
                                    "type" => "array",
                                    "items" => ["type" => "string"]
                                ],
                                "topics" => [
                                    "type" => "array",
                                    "items" => ["type" => "string"]
                                ]
                            ],
                            "required" => [
                                "summary_short",
                                "summary_medium",
                                "summary_long"
                            ]
                        ]
                    ]
                ],
                "messages" => [
                    [
                        "role" => "system",
                        "content" =>
                            "Eres un analista especializado en resumir documentos PDF largos, "
                            . "incluyendo reportes, estudios, PDFs institucionales o técnicos. "
                            . "Debes devolver:
                            - summary_short: 2-3 líneas
                            - summary_medium: 1 párrafo detallado
                            - summary_long: análisis profundo de 5–8 párrafos
                            - insights: hallazgos clave en bullets
                            - topics: temas principales detectados

                            Mantén el estilo profesional."
                    ],
                    [
                        "role" => "user",
                        "content" => "Resume el siguiente documento completo y genera insights y temas.\n\n" . $text
                    ]
                ],
            ]);

            return json_decode($response->choices[0]->message->content, true);

        } catch (\Exception $e) {
            Log::error("Error generando resumen IA: " . $e->getMessage());
            return [
                "summary_short" => "",
                "summary_medium" => "",
                "summary_long" => "",
                "insights" => [],
                "topics" => []
            ];
        }
    }
}
