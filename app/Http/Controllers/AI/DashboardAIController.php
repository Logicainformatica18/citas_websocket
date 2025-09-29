<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardAIController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        // 1. Obtener instrucción de OpenAI
        $instruction = $this->getInstructionFromAI($request->message);

        if (!$instruction || empty($instruction['action'])) {
            return response()->json([
                'error' => '❌ No se pudo interpretar la instrucción de la IA',
            ], 500);
        }

        // 2. Enrutar al controlador hijo según la acción
        switch ($instruction['action']) {
            case 'city_demand':
                $controller = app(CityDemandController::class);
                return $controller->getData($instruction);

            case 'obsolescence':
                $controller = app(ObsolescenceController::class);
                return $controller->getData($instruction);

            case 'technologies':
                $controller = app(TechnologiesController::class);
                return $controller->getData($instruction);

            default:
                return response()->json([
                    'error' => "⚠️ Acción desconocida: {$instruction['action']}"
                ], 400);
        }
    }

    /**
     * 🔹 Paso 1: Preguntar a OpenAI por la intención en formato JSON
     */
    private function getInstructionFromAI(string $userMessage): ?array
    {
        $apiKey = env('OPENAI_API_KEY');

        $systemPrompt = <<<EOT
Eres un asistente para un Dashboard de empleabilidad.
Debes devolver SOLO un JSON válido con la acción a ejecutar.

Acciones permitidas:
- city_demand → cuando el usuario pregunte por mapa de calor, ciudades o países.
- obsolescence → cuando pregunte por tecnologías obsoletas.
- technologies → cuando pregunte por tendencias de lenguajes o tecnologías.

Formato:
{
  "action": "city_demand" | "obsolescence" | "technologies",
  "filters": { "campo": "valor" }  // opcional
}

⚠️ IMPORTANTE:
- Responde SOLO con JSON válido.
- Nada de explicaciones.
EOT;

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => 0,
            'max_tokens' => 300,
        ]);

        if ($response->failed()) {
            Log::error("❌ Error en petición OpenAI (Dashboard)", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $raw = $response->json('choices.0.message.content');
        Log::info("📝 Instrucción IA Dashboard", ['raw' => $raw]);

        return json_decode($raw, true);
    }
}
