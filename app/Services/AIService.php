<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        $this->model  = config('services.openai.model', 'gpt-4o-mini');
    }

    public function analyze(string $prompt): array
    {
        try {

            $response = Http::timeout(60)
                ->withToken($this->apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [

                    "model" => $this->model,

                    "messages" => [
                        [
                            "role" => "system",
                            "content" =>
                            "Eres un experto en diseño curricular universitario, análisis de mercado laboral y tendencias tecnológicas."
                        ],
                        [
                            "role" => "user",
                            "content" => $prompt
                        ]
                    ],

                    "temperature" => 0.4
                ]);

            if (!$response->successful()) {

                Log::error("OpenAI error", [
                    "status" => $response->status(),
                    "body" => $response->body()
                ]);

                return [
                    "diagnosis" => "No se pudo analizar la competencia.",
                    "recommendation" => "Intente nuevamente más tarde."
                ];
            }

            $content = $response->json(
                'choices.0.message.content'
            );

            return [
                "diagnosis" => $content,
                "recommendation" => $content
            ];

        } catch (\Throwable $e) {

            Log::error("AIService exception", [
                "error" => $e->getMessage()
            ]);

            return [
                "diagnosis" => "Error en el análisis IA.",
                "recommendation" => "No se pudo generar recomendación."
            ];
        }
    }
}
