<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\PdfChunk;

class SummaryBuilderService
{
    public function buildSummary(int $pdfId): array
    {
        $chunks = PdfChunk::where('pdf_id', $pdfId)
            ->orderBy('chunk_index')
            ->pluck('content')
            ->toArray();

        // Unimos el texto en un solo super-resumen
        $merged = implode("\n\n", $chunks);

        $response = OpenAI::chat()->create([
            "model" => "gpt-4o",
            "messages" => [
                [
                    "role" => "user",
                    "content" => "Genera 3 cosas:
                    1. Resumen corto (5 líneas)
                    2. Resumen medio (2 párrafos)
                    3. Resumen largo (1 página)
                    4. Insights y temas clave en JSON.

                    CONTENIDO:
                    {$merged}"
                ]
            ]
        ]);

        return json_decode($response['choices'][0]['message']['content'], true);
    }
}
