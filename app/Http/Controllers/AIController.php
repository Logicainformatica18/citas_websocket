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

    $apiKey = env('OPENAI_API_KEY');

    Log::info('🤖 [AIController] Mensaje recibido del usuario', [
        'message' => $request->message,
    ]);

    // 🔹 Prompt para que OpenAI devuelva siempre JSON
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
- Siempre incluye una "suggestion" que sea una pregunta de seguimiento útil.
EOT;


    // 🔹 Llamada a OpenAI
    $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $request->message],
        ],
        'temperature' => 0,
        'max_tokens' => 500,
    ]);

    if ($response->failed()) {
        Log::error('❌ [AIController] Error en petición a OpenAI', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return response()->json([
            'error' => 'Error calling OpenAI',
            'details' => $response->json(),
        ], 500);
    }

    $instructionRaw = $response->json('choices.0.message.content');
    Log::info("📝 [AIController] Raw response", ['raw' => $instructionRaw]);

    $instruction = json_decode($instructionRaw, true);

    if (!$instruction) {
        return response()->json([
            'error' => 'Respuesta inválida de OpenAI',
            'raw' => $instructionRaw,
        ], 500);
    }

    // 🔹 Lista de campos válidos
    $allowedFields = [
        'title','company','location','modality','workload',
        'salary_min','salary_max','currency','source',
        'external_id','url','published_at','created_at'
    ];

    // 🔹 Alias comunes que puede inventar la IA
    $aliases = [
        'job_title'   => 'title',
        'empresa'     => 'company',
        'pais'        => 'location',
        'modalidad'   => 'modality',
        'sueldo_min'  => 'salary_min',
        'sueldo_max'  => 'salary_max'
    ];

    // 🔹 Ejecutar query en BD
    $query = JobOffer::query();

    if (!empty($instruction['filters'])) {
        foreach ($instruction['filters'] as $field => $value) {
            $field = $aliases[$field] ?? $field;
            if (!in_array($field, $allowedFields)) continue;

            // si contiene % => LIKE
            if (is_string($value) && str_contains($value, '%')) {
                $query->where($field, 'LIKE', $value);
            } else {
                $query->where($field, $value);
            }
        }
    }

    // 🔹 Normalizar fields
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

    // 🔹 Paginación simple (máx 100 resultados)
    $results = $query->limit(100)->get($normalizedFields);

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

    // 🔹 Generar mensaje explicativo
    $message = $this->generateMessage($instruction, $results, $aggregations);

    return response()->json([
        'instruction'   => $instruction,
        'results'       => $results,
        'aggregations'  => $aggregations,
        'message'       => $message,
        'suggestion'    => $instruction['suggestion'] ?? null,
    ]);
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
