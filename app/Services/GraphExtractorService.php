<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class GraphExtractorService
{
    public function extract(string $imagePath): array
    {
        $img = base64_encode(file_get_contents($imagePath));

        $response = OpenAI::chat()->create([
            "model" => "gpt-4o",
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "input_image", "image_url" => "data:image/png;base64,{$img}"],
                        [
                            "type" => "text",
                            "text" =>
                            "Extrae cada gráfico de la imagen y devuelve SOLO JSON:
                            {
                                'title': string,
                                'legend': [],
                                'data': [
                                    { 'label': string, 'value': number }
                                ],
                                'insights': []
                            }"
                        ]
                    ]
                ]
            ]
        ]);

        return json_decode($response['choices'][0]['message']['content'], true);
    }
}
