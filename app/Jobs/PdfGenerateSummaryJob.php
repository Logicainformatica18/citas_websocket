<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Models\PdfSummary;
use App\Models\PdfChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfGenerateSummaryJob implements ShouldQueue
{
    use Queueable, Dispatchable;

    public $pdfId;

    public function __construct($pdfId)
    {
        $this->pdfId = $pdfId;
    }

    public function handle()
    {
        $pdf = PdfDocument::find($this->pdfId);

        if (!$pdf) {
            return;
        }

        /** ------------------------------------------------------------------
         * 1️⃣ UNIR TODO EL TEXTO OCR (chunk por chunk)
         * ------------------------------------------------------------------*/
        $merged = PdfChunk::where('pdf_id', $pdf->id)
            ->orderBy('chunk_index')
            ->pluck('content')
            ->implode("\n\n");

        if (!$merged) {
            return;
        }

        /** ------------------------------------------------------------------
         * 2️⃣ PROMPT DE RESUMENES → JSON ESTRUCTURADO
         * ------------------------------------------------------------------*/
        $prompt = "
Genera un resumen del documento en formato JSON estricto con esta estructura:

{
  \"summary_short\": \"5 líneas\",
  \"summary_medium\": \"2 párrafos\",
  \"summary_long\": \"1 página completa\",
  \"insights\": [],
  \"topics\": []
}

NO agregues explicación ni texto fuera del JSON.

Texto:
{$merged}
";

        $resp = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'temperature' => 0,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ]);

        /** ------------------------------------------------------------------
         * 3️⃣ LIMPIEZA DE LA RESPUESTA
         * ------------------------------------------------------------------*/
        $raw = trim($resp['choices'][0]['message']['content']);

        // Eliminar ```json
        $raw = preg_replace('/^```(json)?/i', '', $raw);
        $raw = preg_replace('/```$/', '', $raw);
        $raw = trim($raw);

        $json = json_decode($raw, true);

        if (!is_array($json)) {
            // fallback para evitar crasheo
            $json = [
                "summary_short" => "",
                "summary_medium" => "",
                "summary_long" => "",
                "insights" => [],
                "topics" => []
            ];
        }

        /** ------------------------------------------------------------------
         * 4️⃣ GUARDAR RESUMEN
         * ------------------------------------------------------------------*/
        PdfSummary::create([
            'pdf_id'          => $pdf->id,
            'summary_short'   => $json['summary_short'] ?? '',
            'summary_medium'  => $json['summary_medium'] ?? '',
            'summary_long'    => $json['summary_long'] ?? '',
            'insights_json'   => $json['insights'] ?? [],
            'topics_json'     => $json['topics'] ?? []
        ]);

        /** ------------------------------------------------------------------
         * 5️⃣ LANZAR EL JOB FINAL
         * ------------------------------------------------------------------*/
        PdfFinishProcessingJob::dispatch($pdf->id);
    }
}
