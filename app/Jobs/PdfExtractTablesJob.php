<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Models\PdfTable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfExtractTablesJob implements ShouldQueue
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

        foreach ($pdf->pages as $page) {

            if (!($page->detected_elements['contains_table'] ?? false)) {
                continue;
            }

            // Prompt estandarizado igual que en los demás jobs
            $prompt = "
Extrae TODAS las tablas presentes en esta página.

Devuelve SOLO JSON estricto:

{
  \"headers\": [],
  \"rows\": [],
  \"insights\": []
}

Si no hay tabla, devuelve {}.

Texto de la página:
{$page->text_content}
";

            $resp = OpenAI::chat()->create([
                'model'       => 'gpt-4o',
                'temperature' => 0,
                'messages'    => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            // Respuesta cruda
            $raw = trim($resp['choices'][0]['message']['content']);

            // Limpiar ```json
            $raw = preg_replace('/^```(json)?/i', '', $raw);
            $raw = preg_replace('/```$/', '', $raw);
            $raw = trim($raw);

            $json = json_decode($raw, true);

            // JSON vacío o inválido → ignorar
            if (!is_array($json) || empty($json['rows'])) {
                continue;
            }

            // Guardar tabla
            PdfTable::create([
                'pdf_page_id'   => $page->id,
                'data_json'     => $json['rows'],
                'insights_json' => $json['insights'] ?? [],
            ]);
        }

        // Encadenar siguiente job
        PdfGenerateSummaryJob::dispatch($pdf->id);
    }
}
