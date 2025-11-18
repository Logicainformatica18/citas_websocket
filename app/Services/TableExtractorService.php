<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class TableExtractorService
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
                            "text" => "Extrae todas las tablas en formato JSON estructurado:
                            {
                                'headers': [],
                                'rows': [
                                    [...],
                                    [...]
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
