<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetencyRecommendationService
{
    /* =========================================================
     * Public API
     * ========================================================= */
    public function analyze(array $context): array
    {
        $prompt = $this->buildPrompt($context);

        Log::info('🧠 AI Competency Analysis - START', [
            'competency' => $context['competency'] ?? null,
        ]);

        try {

            $response = retry(2, function () use ($prompt) {

                return Http::withToken(config('services.openai.key'))
                    ->timeout(60)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'       => 'gpt-4o-mini',
                        'temperature' => 0.3,
                        'messages'    => [
                            [
                                'role'    => 'system',
                                'content' => 'Return ONLY valid JSON. No markdown. No explanations.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $prompt,
                            ],
                        ],
                    ]);

            }, 5);

            if ($response->failed()) {
                Log::error('❌ OpenAI HTTP Error (Competency)', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return $this->errorResponse();
            }

            $content = $response->json('choices.0.message.content');

            if (!$content) {
                Log::error('❌ Empty OpenAI response (Competency)');
                return $this->errorResponse();
            }

            $json = $this->extractJson($content);

            if (!$json) {
                Log::error('❌ JSON extraction failed (Competency)', [
                    'raw' => $content,
                ]);
                return $this->errorResponse();
            }

            $decoded = json_decode($json, true);

            if (!is_array($decoded)) {
                Log::error('❌ Invalid JSON decoded (Competency)', [
                    'json' => $json,
                ]);
                return $this->errorResponse();
            }

            Log::info('✅ AI Competency Analysis - SUCCESS', [
                'competency' => $context['competency'] ?? null,
            ]);

            return $decoded;

        } catch (\Throwable $e) {

            Log::error('🔥 AI Competency Exception', [
                'error'   => $e->getMessage(),
                'context' => $context,
            ]);

            return $this->errorResponse();
        }
    }

    /* =========================================================
     * PROMPT
     * ========================================================= */
private function buildPrompt(array $ctx): string
{
    return <<<PROMPT
You are a senior academic and labor-market analyst specialized in technology curricula.
Respond ALWAYS in Spanish, using a neutral and academic tone.

Analiza la siguiente competencia considerando el contexto ACTUAL del mercado tecnológico (2024–2026).
Puedes basarte conceptualmente en tendencias observadas por organismos como WEF, McKinsey, OECD o Gartner,
sin citar fuentes textuales ni enlaces.

CONTEXTO:

Competencia:
{$ctx['competency']}

Lenguajes asociados:
{$this->list($ctx['languages'] ?? [])}

Tecnologías asociadas:
{$this->list($ctx['technologies'] ?? [])}

Presente en mercado laboral:
{$this->bool($ctx['market_match'] ?? false)}

Presente en reportes estratégicos:
{$this->bool($ctx['trend_match'] ?? false)}

TAREAS:
1. Describe brevemente el estado actual de esta competencia (máx. 2 líneas).
2. Indica si debe mantenerse, reforzarse, actualizarse o replantearse.
3. Da UNA recomendación académica concreta y accionable.

FORMATO DE RESPUESTA (JSON ESTRICTO):

{
  "diagnosis": "...",
  "recommendation": "..."
}
PROMPT;
}


    /* =========================================================
     * Helpers
     * ========================================================= */
    private function list(array $items): string
    {
        return empty($items)
            ? 'None'
            : implode(', ', $items);
    }

    private function bool(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    private function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
    }

    private function errorResponse(): array
    {
        return [
            'diagnosis'      => 'Error al generar análisis',
            'recommendation' => 'Intentar nuevamente',
        ];
    }
}
