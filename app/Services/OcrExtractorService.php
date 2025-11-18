<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class OcrExtractorService
{
    public function extractText(string $imagePath): string
    {
        $image = base64_encode(file_get_contents($imagePath));

        $response = OpenAI::chat()->create([
            "model" => "gpt-4o-mini",
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "input_image", "image_url" => "data:image/png;base64,{$image}"],
                        ["type" => "text", "text" => "Transcribe todo el texto visible con formato limpio."]
                    ],
                ]
            ]
        ]);

        return $response['choices'][0]['message']['content'] ?? "";
    }
}
