<?php

namespace App\Jobs;

use App\Models\PdfPage;
use App\Models\PdfDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfClassifyPageJob implements ShouldQueue
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

            // Prompt consistente con el pipeline
            $prompt = "
Clasifica esta página y devuelve SOLO JSON estricto.

Formato:

{
  \"contains_graph\": bool,
  \"contains_table\": bool,
  \"contains_text\": bool,
  \"content_type\": \"text|graph|table|mixed|empty\"
}

Texto de la página:
{$page->text_content}
";

            $resp = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0,
            ]);

            $raw = trim($resp['choices'][0]['message']['content']);

            // limpia ```json ... ```
            $raw = preg_replace('/^```(json)?/i', '', $raw);
            $raw = preg_replace('/```$/', '', $raw);
            $raw = trim($raw);

            $json = json_decode($raw, true);

            if (!is_array($json)) {
                // fallback seguro
                $json = [
                    "contains_graph" => false,
                    "contains_table" => false,
                    "contains_text" => strlen(trim($page->text_content)) > 0,
                    "content_type"   => "text"
                ];
            }

            $page->update([
                'content_type'      => $json['content_type'] ?? 'text',
                'detected_elements' => $json
            ]);
        }

        // Lanzar siguiente job
        PdfExtractGraphsJob::dispatch($pdf->id);
    }
}
