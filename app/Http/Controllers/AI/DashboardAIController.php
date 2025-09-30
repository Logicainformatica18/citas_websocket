<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\AI\WorkModeAIController;
use App\Http\Controllers\AI\CityDemandAIController;
use App\Http\Controllers\AI\TechnologiesAIController;
use App\Http\Controllers\AI\RolesAIController;

class DashboardAIController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        // 1. Preguntar a OpenAI
        $instruction = $this->getInstructionFromAI($request->message);

        if (!$instruction) {
            return response()->json([
                'error' => '❌ No se pudo interpretar la instrucción de la IA',
            ], 500);
        }

        // 2. Si aún está en estado "pending_confirmation", devolver tal cual
        if (($instruction['status'] ?? null) === 'pending_confirmation') {
            return response()->json($instruction);
        }

        // 3. Si está confirmado, enrutar a los hijos correspondientes
        $results = [];
        foreach ($instruction['targets'] ?? [] as $target) {
            switch ($target) {
                case 'WorkModeChart':
                    $controller = app(WorkModeAIController::class);
                    $results[$target] = $controller->getData($instruction);
                    break;

                case 'CityDemandMap':
                    $controller = app(CityDemandAIController::class);
                    $results[$target] = $controller->getData($instruction);
                    break;

                case 'TechnologiesChart':
                    $controller = app(TechnologiesAIController::class);
                    $results[$target] = $controller->getData($instruction);
                    break;

                case 'RolesChart':
                    $controller = app(RolesAIController::class);
                    $results[$target] = $controller->getData($instruction);
                    break;

                default:
                    Log::warning("⚠️ Target desconocido: {$target}");
            }
        }

        return response()->json([
            'instruction' => $instruction,
            'results' => $results,
        ]);
    }

    /**
     * 🔹 Paso 1: Obtener la intención global en JSON Mode
     */
    private function getInstructionFromAI(string $userMessage): ?array
    {
        $apiKey = env('OPENAI_API_KEY');

        $systemPrompt = <<<EOT
Eres un asistente para un Dashboard de Ofertas Laborales.
Debes responder **EXCLUSIVAMENTE** en JSON válido.

Tu rol:
1. Conversa con el usuario y ayúdalo a construir su consulta paso a paso.
2. Haz preguntas aclaratorias si falta contexto (ejemplo: rol, modalidad, país, ciudad, sector).
3. Cuando detectes suficiente información, responde en JSON con `"status":"pending_confirmation"`
   y propone una `"suggestion"` clara para confirmar la consulta.
4. Solo cuando el usuario confirme → responde con `"status":"confirmed"`.

Formato JSON esperado:
{
  "targets": ["WorkModeChart","CityDemandMap"],
  "filters": { "campo": "valor" },
  "fields": ["columna1"],
  "aggregations": ["percent","count","avg"],
  "status": "pending_confirmation" | "confirmed",
  "suggestion": "Texto para confirmar con el usuario"
}

Reglas:
- Modalidad de trabajo → `WorkModeChart`
- País o ciudad → `CityDemandMap`
- Salarios → `SalaryChart`
- Tecnologías/lenguajes → `TechnologiesChart`
- Roles o perfiles → `RolesChart`

Reglas adicionales:
- Si el usuario dice "sin filtros", "general" o "todo", interpreta que desea un resumen general.
- En ese caso responde directamente con `"status": "confirmed"` y define `targets` de forma automática según el tema:
   - Si no menciona nada → usa ["WorkModeChart","CityDemandMap"].
   - Si menciona modalidad → usa ["WorkModeChart"].
   - Si menciona países/ciudades → usa ["CityDemandMap"].
EOT;

        Log::info("🤖 Enviando mensaje a OpenAI", ['message' => $userMessage]);

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => 0,
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object'], // 🚀 JSON Mode activado
        ]);

        if ($response->failed()) {
            Log::error("❌ Error en petición OpenAI (Dashboard)", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $raw = $response->json('choices.0.message.content'); // string JSON
        Log::info("📝 Instrucción IA Dashboard RAW", ['raw' => $raw]);

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("❌ Error parseando JSON IA", [
                'error' => json_last_error_msg(),
                'raw' => $raw,
            ]);
            return null;
        }

        return $decoded;
    }
}
