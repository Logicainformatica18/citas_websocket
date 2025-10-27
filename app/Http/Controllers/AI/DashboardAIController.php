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
     * 🎯 Endpoint principal del chat IA (por training_id o texto)
     */
    public function chat(Request $request)
    {
        Log::info("💬 [IA Chat] Nueva solicitud", ['payload' => $request->all()]);

        // =====================================================
        // 1️⃣ Buscar entrenamiento
        // =====================================================
        if ($request->has('training_id')) {
            // 🔹 Caso directo: usuario seleccionó una sugerencia
            $training = DB::table('aitrainings')
                ->where('id', $request->training_id)
                ->where('is_active', 1)
                ->first();

            Log::info("🎯 [IA Chat] Búsqueda por ID", [
                'training_id' => $request->training_id,
                'encontrado' => (bool) $training,
            ]);
        } else {
            // 🔹 Caso fallback: usuario escribió texto libre
            $userMessage = trim($request->get('message', ''));
            if (!$userMessage) {
                return response()->json(['message' => '⚠️ Mensaje vacío.'], 400);
            }

            $normalized = mb_strtolower($userMessage);

            $training = DB::table('aitrainings')
                ->where('is_active', 1)
                ->whereRaw('LOWER(prompt) LIKE ?', ['%' . $normalized . '%'])
                ->first();

            Log::info("🎯 [IA Chat] Búsqueda por texto", [
                'input' => $normalized,
                'encontrado' => (bool) $training,
            ]);
        }

        if (!$training) {
            Log::warning("⚠️ [IA Chat] No se encontró entrenamiento", [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => '🤖 No encontré una pregunta registrada que coincida con tu consulta.',
                'suggestion' => 'Selecciona una pregunta de la lista o revisa tus métricas disponibles.',
            ], 404);
        }

        // =====================================================
        // 2️⃣ Ejecutar el método indicado en el campo interpreter
        // =====================================================
        Log::info("⚙️ [IA Chat] Ejecutando interpreter", [
            'interpreter' => $training->interpreter,
        ]);

        $result = $this->executeInterpreter($training->interpreter);

        if (!$result) {
            Log::error("💥 [IA Chat] Error ejecutando interpreter", [
                'training' => $training,
            ]);
            return response()->json([
                'message' => '⚠️ No se pudo ejecutar el controlador asociado a esta consulta.',
                'training' => $training,
            ], 500);
        }

        // =====================================================
        // 3️⃣ (Opcional) Generar explicación con IA
        // =====================================================
        $explanation = null;
        if ($training->has_ai_response && $training->explanation_prompt) {
            $explanation = $this->generateExplanation($training->explanation_prompt, $result);
        }

        // =====================================================
        // 4️⃣ Respuesta final
        // =====================================================
        return response()->json([
            'topic' => $training->topic,
            'prompt' => $training->prompt,
            'component' => $training->component ?? null,
            'result' => $result,
            'explanation' => $explanation,
        ]);
    }

    // =====================================================
    // ⚙️ Ejecuta el método indicado en el campo interpreter
    // =====================================================
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

    // =====================================================
    // 🧠 Explicación natural con OpenAI (opcional)
    // =====================================================
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
    public function suggestions(Request $request)
    {
        $query = mb_strtolower($request->get('q', ''));

        $results = DB::table('aitrainings')
            ->where('is_active', 1)
            ->where('topic', 'Métricas y monitoreo')
            ->when($query, function ($q) use ($query) {
                $q->whereRaw('LOWER(prompt) LIKE ?', ['%' . $query . '%']);
            })
            ->select('id', 'prompt', 'description', 'component', 'interpreter')
            ->limit(6)
            ->get();

        return response()->json(['suggestions' => $results]);
    }

}
