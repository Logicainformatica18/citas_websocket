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

    $sessionId = $request->header('X-Session-ID') ?? session()->getId();
    $userId = auth()->id();
    $userMessage = trim($request->get('message', ''));

    if (!$userMessage && !$request->has('training_id')) {
        return response()->json(['message' => '⚠️ Mensaje vacío.'], 400);
    }

    // ============================
    // 1️⃣ Recuperar solo los últimos 5 mensajes
    // ============================
    $previous = DB::table('chat_histories')
        ->where('session_id', $sessionId)
        ->orderByDesc('id')
        ->limit(5)
        ->get(['user_message', 'ai_response'])
        ->reverse()
        ->map(fn($r) => [
            ['role' => 'user', 'content' => $r->user_message],
            ['role' => 'assistant', 'content' => $r->ai_response],
        ])
        ->flatten(1)
        ->values()
        ->toArray();

    // ============================
    // 2️⃣ Buscar entrenamiento
    // ============================
    $training = null;

    if ($request->has('training_id')) {
        $training = DB::table('aitrainings')
            ->where('id', $request->training_id)
            ->where('is_active', 1)
            ->first();
    } elseif ($userMessage) {
        $normalized = mb_strtolower($userMessage);
        $training = DB::table('aitrainings')
            ->where('is_active', 1)
            ->whereRaw('LOWER(prompt) LIKE ?', ['%' . $normalized . '%'])
            ->first();
    }

    // ============================
    // 3️⃣ Si no hay entrenamiento → GPT contextual
    // ============================
    if (!$training) {
        Log::info("🔎 [IA Chat] No se encontró entrenamiento, modo contextual GPT.");

        $messages = array_merge(
            [[
                'role' => 'system',
                'content' => 'Eres VERA, el analista IA institucional del Observatorio ISIL.
                Responde de forma ejecutiva, técnica y breve sobre temas de empleabilidad, educación superior y mercado laboral latinoamericano.'
            ]],
            $previous,
            [['role' => 'user', 'content' => $userMessage]]
        );

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'temperature' => 0.4,
                    'max_tokens' => 400,
                ]);

            $aiText = trim($response->json('choices.0.message.content') ?? 'No se pudo generar respuesta.');

            // 🧾 Guardar conversación
            DB::table('chat_histories')->insert([
                'session_id'   => $sessionId,
                'user_id'      => $userId,
                'user_message' => $userMessage,
                'ai_response'  => $aiText,
                'context'      => json_encode($previous, JSON_UNESCAPED_UNICODE),
                'source'       => 'dashboard',
                'language'     => 'es',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // 🧹 Limpiar historial si hay más de 5 mensajes
            DB::table('chat_histories')
                ->where('session_id', $sessionId)
                ->orderBy('id', 'asc')
                ->skip(5)
                ->delete();

            return response()->json([
                'message'    => '💬 Respuesta generada por VERA (modo contextual)',
                'suggestion' => $aiText,
            ]);
        } catch (\Throwable $e) {
            Log::error("💥 Error en GPT contextual", ['error' => $e->getMessage()]);
            return response()->json(['message' => '⚠️ Error en modo contextual.'], 500);
        }
    }

    // ============================
    // 4️⃣ Ejecutar el “interpreter” del training
    // ============================
    Log::info("⚙️ [IA Chat] Ejecutando interpreter", ['interpreter' => $training->interpreter]);

    $result = $this->executeInterpreter($training->interpreter);

    if (!$result) {
        return response()->json(['message' => '⚠️ No se pudo ejecutar el controlador asociado.'], 500);
    }

    // ============================
    // 5️⃣ Generar explicación enriquecida (IA)
    // ============================
    $explanation = null;

    if ($training->has_ai_response && !empty($training->explanation_prompt)) {
        $prompt = $training->explanation_prompt . "\n\nDatos actuales:\n" .
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Log::info("🧠 [IA Chat] Prompt enviado a OpenAI", ['prompt' => $prompt]);

        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' =>
                            'Eres VERA, el analista institucional del Observatorio ISIL.
                            Explica los datos de forma breve, técnica y ejecutiva, con conclusiones claras.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 400,
                    'temperature' => 0.4,
                ]);

            $explanation = trim($response->json('choices.0.message.content'));
        } catch (\Throwable $e) {
            Log::error("💥 Error generando explicación IA", ['error' => $e->getMessage()]);
        }
    }

    // ============================
    // 6️⃣ Guardar conversación
    // ============================
    DB::table('chat_histories')->insert([
        'session_id'      => $sessionId,
        'user_id'         => $userId,
        'report_query_id' => $training->id,
        'user_message'    => $userMessage ?: $training->prompt,
        'ai_response'     => $explanation ?? '✅ Consulta procesada correctamente.',
        'context'         => json_encode($result, JSON_UNESCAPED_UNICODE),
        'source'          => 'dashboard',
        'language'        => 'es',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    // 🧹 Mantener solo los últimos 5 mensajes
    DB::table('chat_histories')
        ->where('session_id', $sessionId)
        ->orderBy('id', 'asc')
        ->skip(5)
        ->delete();

    // ============================
    // 7️⃣ Respuesta final al frontend
    // ============================
    Log::info("🧠 [IA Chat] Respuesta IA generada", [
        'prompt' => $training->explanation_prompt ?? null,
        'has_ai_response' => $training->has_ai_response,
        'explanation' => $explanation
    ]);

    return response()->json([
        'topic'       => $training->topic,
        'prompt'      => $training->prompt,
        'component'   => $training->component ?? null,
        'result'      => $result,
        'explanation' => $explanation,
    ]);
}

/**
 * 💬 Devuelve los últimos 5 mensajes del chat de una sesión
 */
public function history(Request $request)
{
    $sessionId = $request->header('X-Session-ID') ?? session()->getId();

    try {
        $messages = DB::table('chat_histories')
            ->where('session_id', $sessionId)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['user_message', 'ai_response', 'created_at'])
            ->reverse() // orden cronológico correcto
            ->values();

        // 🔁 Formatear para el frontend
        $formatted = $messages->flatMap(function ($msg) {
            return [
                ['from' => 'user', 'text' => $msg->user_message],
                ['from' => 'ai', 'text' => $msg->ai_response],
            ];
        })->values();

        return response()->json([
            'session_id' => $sessionId,
            'messages' => $formatted,
        ]);
    } catch (\Throwable $e) {
        \Log::error("💥 [ChatHistory] Error cargando historial", ['error' => $e->getMessage()]);
        return response()->json([
            'error' => 'No se pudo recuperar el historial del chat.',
            'details' => $e->getMessage(),
        ], 500);
    }
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

    /**
     * 💡 Autocompletado de sugerencias
     */
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
