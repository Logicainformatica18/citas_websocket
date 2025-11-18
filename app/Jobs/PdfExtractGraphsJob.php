<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Models\PdfGraph;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfExtractGraphsJob implements ShouldQueue
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

            if (!($page->detected_elements['contains_graph'] ?? false)) {
                continue;
            }

            // Prompt estandarizado
            $prompt = "
Extrae el gráfico presente en esta página.

Devuelve SOLO JSON ESTRICTO:

{
  \"title\": string|null,
  \"legend\": [],
  \"data\": [
    { \"label\": string, \"value\": number }
  ],
  \"insights\": []
}

Si no encuentras datos suficientes para un gráfico, devuelve:

{}

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

            // contenido crudo
            $raw = trim($resp['choices'][0]['message']['content']);

            // limpiar ```json
            $raw = preg_replace('/^```(json)?/i', '', $raw);
            $raw = preg_replace('/```$/', '', $raw);
            $raw = trim($raw);

            $json = json_decode($raw, true);

            // Si GPT falló, ignoramos página
            if (!is_array($json) || empty($json['data'])) {
                continue;
            }

            // Insertar gráfico
            PdfGraph::create([
                'pdf_page_id'   => $page->id,
                'title'         => $json['title'] ?? null,
                'data_json'     => $json['data'] ?? [],
                'legend_json'   => $json['legend'] ?? [],
                'insights_json' => $json['insights'] ?? []
            ]);
        }

        // Siguiente etapa del pipeline
        PdfExtractTablesJob::dispatch($pdf->id);
    }
}
