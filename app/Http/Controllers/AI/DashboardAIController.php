<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardAIController extends Controller
{
    /**
     * 🎯 Endpoint principal del chat IA
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = trim($request->message);
        Log::info("💬 Mensaje recibido del usuario", ['message' => $userMessage]);

        // 1️⃣ Buscar entrenamiento relevante en ai_trainings
        $training = $this->resolveTraining($userMessage);

        if (!$training) {
            return response()->json([
                'message' => '🤖 No encontré un entrenamiento relacionado con tu consulta.',
                'suggestion' => 'Puedes intentar reformular la pregunta o consultar temas de métricas, tendencias o empleabilidad.',
            ], 404);
        }

        Log::info("🎯 Entrenamiento detectado", [
            'topic' => $training->topic,
            'prompt' => $training->prompt,
            'interpreter' => $training->interpreter,
        ]);

        // 2️⃣ Ejecutar el método indicado en el campo interpreter
        $result = $this->executeInterpreter($training->interpreter);

        if (!$result) {
            return response()->json([
                'message' => '⚠️ No se pudo ejecutar el controlador asociado a esta consulta.',
                'training' => $training,
            ], 500);
        }

        // 3️⃣ (Opcional) Generar explicación IA si has_ai_response = 1
        $explanation = null;
        if ($training->has_ai_response && $training->explanation_prompt) {
            $explanation = $this->generateExplanation($training->explanation_prompt, $result);
        }

        // 4️⃣ Respuesta final estructurada
        return response()->json([
            'topic' => $training->topic,
            'prompt' => $training->prompt,
            'component' => $training->component ?? null,
            'result' => $result,
            'explanation' => $explanation,
        ]);
    }

    // =====================================================
    // 🔍 LOCAL FUNCTIONS
    // =====================================================

    /**
     * 🔎 Busca el entrenamiento más relevante basado en el mensaje del usuario
     */
   private function resolveTraining(string $message)
{
    Log::info("🧠 [resolveTraining] Iniciando búsqueda de entrenamiento", [
        'input_message' => $message,
    ]);

    $messageLower = mb_strtolower($message);

    // 🧩 Extraer palabras clave (sin artículos ni palabras cortas)
    $keywords = collect(explode(' ', $messageLower))
        ->map(fn($w) => trim($w))
        ->reject(fn($w) => strlen($w) < 3)
        ->values()
        ->all();

    Log::info("🪄 [resolveTraining] Palabras clave generadas", ['keywords' => $keywords]);

    // 🔍 Consulta
    $query = DB::table('ai_trainings')
        ->where('is_active', 1)
        ->where(function ($q) use ($messageLower, $keywords) {
            $q->whereRaw('LOWER(prompt) LIKE ?', ["%{$messageLower}%"])
              ->orWhereRaw('LOWER(description) LIKE ?', ["%{$messageLower}%"]);

            foreach ($keywords as $word) {
                $q->orWhereRaw('LOWER(prompt) LIKE ?', ["%{$word}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$word}%"])
                  ->orWhereRaw("JSON_SEARCH(LOWER(tags), 'one', '%{$word}%') IS NOT NULL");
            }
        })
        ->orderByRaw('CHAR_LENGTH(prompt) ASC');

    // 🧾 Mostrar SQL generado (solo para debugging)
    Log::debug("🧩 [resolveTraining] SQL generado", [
        'sql' => $query->toSql(),
        'bindings' => $query->getBindings(),
    ]);

    // 🧠 Obtener resultado
    $training = $query->first();

    if ($training) {
        Log::info("✅ [resolveTraining] Entrenamiento encontrado", [
            'id' => $training->id ?? null,
            'topic' => $training->topic ?? null,
            'prompt' => $training->prompt ?? null,
        ]);
    } else {
        Log::warning("⚠️ [resolveTraining] No se encontró coincidencia para el mensaje", [
            'input_message' => $message,
            'keywords' => $keywords,
        ]);
    }

    return $training;
}


    /**
     * ⚙️ Ejecuta el controlador indicado en el campo interpreter
     * Ejemplo: MetricsDashboardController@globalAlignment
     */
    private function executeInterpreter(?string $interpreter)
    {
        if (!$interpreter || !str_contains($interpreter, '@')) {
            Log::warning("⚠️ Interpreter inválido", ['interpreter' => $interpreter]);
            return null;
        }

        [$controller, $method] = explode('@', $interpreter);
        $controllerClass = "App\\Http\\Controllers\\AI\\Metrics\\{$controller}";

        if (!class_exists($controllerClass)) {
            Log::error("❌ Controlador no encontrado", ['controller' => $controllerClass]);
            return null;
        }

        $instance = app($controllerClass);

        if (!method_exists($instance, $method)) {
            Log::error("❌ Método no encontrado en controlador", ['method' => $method]);
            return null;
        }

        try {
            $response = $instance->$method();
            return method_exists($response, 'getData')
                ? $response->getData(true)
                : $response;
        } catch (\Throwable $e) {
            Log::error("💥 Error ejecutando interpreter", [
                'interpreter' => $interpreter,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 💬 Genera una explicación natural del resultado usando OpenAI
     */
    private function generateExplanation(string $promptTemplate, $data): ?string
    {
        $apiKey = env('OPENAI_API_KEY');

        if (!$apiKey) {
            Log::warning("⚠️ Falta OPENAI_API_KEY, se omite explicación IA");
            return null;
        }

        $prompt = $promptTemplate . "\n\nDatos actuales:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un analista institucional de ISIL. Explica métricas en lenguaje claro, técnico y breve.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 300,
                'temperature' => 0.3,
            ]);

            if ($response->failed()) {
                Log::error("❌ Error generando explicación IA", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return trim($response->json('choices.0.message.content'));
        } catch (\Throwable $e) {
            Log::error("💥 Excepción al generar explicación IA", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
