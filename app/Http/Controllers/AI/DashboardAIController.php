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

        $userMessage = $request->message;

        // 1️⃣ Interpretar la intención con OpenAI
        $instruction = $this->getInstructionFromAI($userMessage);

        if (!$instruction) {
            return response()->json([
                'error' => '❌ No se pudo interpretar la instrucción de la IA',
                'message' => '⚠️ Hubo un error procesando tu consulta, intenta de nuevo.',
            ], 500);
        }

        // 2️⃣ Si la IA pide confirmación → sugerencia
        if (($instruction['status'] ?? null) === 'pending_confirmation') {
            return response()->json([
                'message' => '💡 ' . ($instruction['suggestion'] ?? '¿Quieres confirmar esta consulta?'),
                'instruction' => $instruction,
            ]);
        }

        // 3️⃣ Si está confirmado → enrutar a los controladores hijos
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
            'message' => '✅ Consulta procesada. Revisa el dashboard.',
            'instruction' => $instruction,
            'results' => $results,
        ]);
    }

    /**
     * 🔹 Llama a OpenAI para interpretar el mensaje
     */
    private function getInstructionFromAI(string $userMessage): ?array
    {
        $apiKey = env('OPENAI_API_KEY');

        $systemPrompt = <<<EOT
Eres un asistente para un Dashboard de Ofertas Laborales.
Debes responder **SOLO en JSON válido**.

🎯 Tu rol:
- Traducir lenguaje natural del usuario a filtros estructurados.
- NO devuelvas texto libre, solo JSON.
- Haz preguntas aclaratorias si falta contexto (status="pending_confirmation").
- Si está claro → responde con status="confirmed".

📊 Targets disponibles:
- Modalidad de trabajo → "WorkModeChart"
- País o ciudad → "CityDemandMap"
- Tecnologías/lenguajes → "TechnologiesChart"
- Roles o perfiles → "RolesChart"

📌 Formato esperado:
{
  "targets": ["TechnologiesChart"],
  "filters": { "year": 2024, "quarter": "all" },
  "fields": [],
  "aggregations": ["percent"],
  "status": "pending_confirmation" | "confirmed",
  "suggestion": "Texto para confirmar con el usuario"
}

📌 Reglas:
- Si dice "todo", "general" → status="confirmed" con filtros vacíos.
- Si menciona solo año → usa {year: XXXX, quarter:"all"}.
- Si menciona trimestre pero no año → pide el año (pending_confirmation).
- Si menciona ambos → confirmed.
- Nunca inventes valores.
EOT;

        Log::info("🤖 Enviando mensaje a OpenAI (Dashboard)", ['message' => $userMessage]);

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => 0,
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object'], // 🚀 JSON Mode
        ]);

        if ($response->failed()) {
            Log::error("❌ Error en petición OpenAI (Dashboard)", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $raw = $response->json('choices.0.message.content');
        Log::info("📝 Instrucción IA Dashboard RAW", ['raw' => $raw]);

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("❌ Error parseando JSON IA Dashboard", [
                'error' => json_last_error_msg(),
                'raw' => $raw,
            ]);
            return null;
        }

        return $decoded;
    }
}
