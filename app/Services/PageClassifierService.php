<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class PageClassifierService
{
    public function classify(string $imagePath): array
    {
        $imgData = base64_encode(file_get_contents($imagePath));

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            "type" => "input_image",
                            "image_url" => "data:image/png;base64,{$imgData}"
                        ],
                        [
                            "type" => "text",
                            "text" => "Analiza esta página y responde en JSON:
                            {
                                'contains_graph': bool,
                                'contains_table': bool,
                                'contains_text': bool,
                                'content_type': 'text|graph|table|mixed|empty'
                            }"
                        ]
                    ]
                ],
            ]
        ]);

        return json_decode($response['choices'][0]['message']['content'], true);
    }
}
