<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\JobOffer;

class AIController extends Controller
{
    
     public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        // 1. Obtener instrucción de la IA
        $instruction = $this->getInstructionFromAI($request->message);

        if (!$instruction) {
            return response()->json([
                'error' => 'Respuesta inválida de OpenAI',
            ], 500);
        }

        // 2. Ejecutar instrucción en BD
        [$results, $aggregations] = $this->executeInstruction($instruction);

        // 3. Generar mensaje explicativo
        $message = $this->generateMessage($instruction, $results, $aggregations);

        return response()->json([
            'instruction'   => $instruction,
            'results'       => $results,
            'aggregations'  => $aggregations,
            'message'       => $message,
            'suggestion'    => $instruction['suggestion'] ?? null,
        ]);
    }

 /**
     * 🔹 Paso 1: Pedirle a OpenAI la instrucción JSON
     */
    private function getInstructionFromAI(string $userMessage): ?array
    {
        $apiKey = env('OPENAI_API_KEY');

        $systemPrompt = <<<EOT
Eres un asistente para un dashboard de ofertas laborales.
Debes devolver instrucciones en JSON para filtrar o agregar datos desde la tabla "job_offers".

Formato de respuesta:
{
  "action": "query" | "aggregate",
  "filters": { "campo": "valor" },
  "fields": ["columna1", "columna2"],
  "aggregations": ["count","percent","avg"],
  "suggestion": "Pregunta sugerida para continuar"
}

⚠️ IMPORTANTE:
- Devuelve SOLO JSON válido, sin explicación adicional.
- Si el usuario pregunta por **modalidad de trabajo** → responde siempre con:
  { "action":"aggregate", "fields":["modality"], "aggregations":["percent"] }
- Si pregunta por **ubicación / país / ciudad** → usa "location" + "percent".
- Si pregunta por **salarios** → usa "salary_min" o "salary_max" + "avg".
- Si pregunta por **número total** → usa "count".
- Si pregunta por **fechas**:
   - "23 de septiembre 2025" → { "filters": { "published_at": "2025-09-23" } }
   - "septiembre 2025" → { "filters": { "published_at": { "from":"2025-09-01","to":"2025-09-30"} } }
   - "2025" → { "filters": { "published_at": { "from":"2025-01-01","to":"2025-12-31"} } }
EOT;

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => 0,
            'max_tokens' => 500,
        ]);

        if ($response->failed()) {
            Log::error('❌ Error en petición a OpenAI', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $instructionRaw = $response->json('choices.0.message.content');
        Log::info("📝 Instrucción cruda de la IA", ['raw' => $instructionRaw]);

        return json_decode($instructionRaw, true);
    }
    private function executeInstruction(array $instruction): array
    {
        $allowedFields = [
            'title','company','location','modality','workload',
            'salary_min','salary_max','currency','source',
            'external_id','url','published_at','created_at'
        ];

        $aliases = [
            'job_title'   => 'title',
            'empresa'     => 'company',
            'pais'        => 'location',
            'modalidad'   => 'modality',
            'sueldo_min'  => 'salary_min',
            'sueldo_max'  => 'salary_max'
        ];

        $query = JobOffer::query();

        // 🔹 Procesar filtros
        if (!empty($instruction['filters'])) {
            foreach ($instruction['filters'] as $field => $value) {
                $field = $aliases[$field] ?? $field;
                if (!in_array($field, $allowedFields)) continue;

                if ($field === 'published_at' && is_array($value)) {
                    $query->whereBetween($field, [$value['from'], $value['to']]);
                } elseif ($field === 'published_at') {
                    $query->whereDate($field, $value);
                } elseif (is_string($value) && str_contains($value, '%')) {
                    $query->where($field, 'LIKE', $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        // 🔹 Normalizar campos
        $requestedFields = $instruction['fields'] ?? ['*'];
        $normalizedFields = [];
        foreach ($requestedFields as $f) {
            $f = $aliases[$f] ?? $f;
            if (in_array($f, $allowedFields)) {
                $normalizedFields[] = $f;
            }
        }
        if (empty($normalizedFields)) {
            $normalizedFields = ['*'];
        }

        $results = $query->limit(100)->get($normalizedFields);

        // 🔹 Agregaciones
        $aggregations = [];
        if (!empty($instruction['aggregations'])) {
            foreach ($instruction['aggregations'] as $agg) {
                if ($agg === 'count') {
                    $aggregations['count'] = $results->count();
                }
                if ($agg === 'percent' && !empty($normalizedFields[0]) && $normalizedFields[0] !== '*') {
                    $field = $normalizedFields[0];
                    $aggregations['percent'] = $results->groupBy($field)->map(function ($group) use ($results) {
                        return round(($group->count() / max($results->count(), 1)) * 100, 2);
                    });
                }
                if ($agg === 'avg' && !empty($normalizedFields[0]) && $normalizedFields[0] !== '*') {
                    $field = $normalizedFields[0];
                    $aggregations['avg'] = $results->avg($field);
                }
            }
        }

        return [$results, $aggregations];
    }

  private function generateMessage(array $instruction, $results, array $aggregations): string
    {
        if (($instruction['action'] ?? null) === 'aggregate' && isset($aggregations['percent'])) {
            $field = $instruction['fields'][0] ?? 'campo';
            $parts = [];
            foreach ($aggregations['percent'] as $key => $percent) {
                $parts[] = "$percent% son $key";
            }
            return "📊 Según tu consulta, el desglose de *{$field}* es: " . implode(", ", $parts) . ".";
        }

        if (($instruction['action'] ?? null) === 'query') {
            return "📋 Encontré {$results->count()} resultados según tu búsqueda.";
        }

        return "✅ Se ejecutó tu consulta correctamente. ¿Quieres que te muestre otro análisis?";
    }
}
 
