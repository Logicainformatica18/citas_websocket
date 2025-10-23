<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ChatHistory;
use App\Models\ReportQuery;

class AIController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $sessionId = $request->header('X-Session-ID') ?? (string) Str::uuid();
        $userId = auth()->id();

        // 🔹 Paso 1: identificar la pregunta o categoría
        $report = $this->findMatchingReportQuery($request->message);

        if (!$report) {
            return response()->json([
                'message' => "⚠️ No encontré una pregunta registrada que coincida con tu consulta.",
            ]);
        }

        // 🔹 Paso 2: obtener su prompt y configuración
        $prompt = $report->description ?? "Explica brevemente los resultados obtenidos en base a los datos del sistema.";
        $interpreter = $report->interpreter;
        $component = $report->component;

        // 🔹 Paso 3: ejecutar el intérprete dinámico
        [$results, $summary] = $this->executeInterpreter($interpreter);

        // 🔹 Paso 4: generar una respuesta contextual con GPT (usando el prompt definido en BD)
        $aiResponse = $this->generateContextualExplanation($prompt, $results, $summary);

        // 🔹 Paso 5: registrar historial
        ChatHistory::logInteraction([
            'session_id'      => $sessionId,
            'user_id'         => $userId,
            'report_query_id' => $report->id,
            'user_message'    => $request->message,
            'ai_response'     => $aiResponse,
            'context'         => ['results' => $results, 'summary' => $summary],
        ]);

        return response()->json([
            'session_id'  => $sessionId,
            'component'   => $component,
            'results'     => $results,
            'summary'     => $summary,
            'message'     => $aiResponse,
        ]);
    }

    /**
     * 🧠 Encuentra la pregunta que más se parece a lo que escribió el usuario
     */
    private function findMatchingReportQuery(string $message): ?ReportQuery
    {
        $normalized = strtolower($message);

        return ReportQuery::where('is_active', true)
            ->get()
            ->first(function ($q) use ($normalized) {
                return str_contains(strtolower($q->question), $normalized)
                    || collect(json_decode($q->tags ?? '[]'))->contains(fn($tag) => str_contains($normalized, strtolower($tag)));
            });
    }

    /**
     * 🧩 Ejecuta el intérprete definido en la tabla report_queries
     */
    private function executeInterpreter(string $interpreter): array
    {
        // Ejemplo: "App\Services\Metrics\AlignmentMetricService@currentAlignment"
        try {
            if (str_contains($interpreter, '@')) {
                [$class, $method] = explode('@', $interpreter);
                $service = app($class);
                return $service->$method();
            }

            // Si el intérprete es una función global o helper
            if (function_exists($interpreter)) {
                return call_user_func($interpreter);
            }

            // Si el intérprete no existe
            return [[], '⚠️ No se pudo ejecutar el intérprete especificado.'];
        } catch (\Throwable $e) {
            Log::error('Error ejecutando intérprete: ' . $e->getMessage());
            return [[], '❌ Error interno al ejecutar el intérprete.'];
        }
    }

    /**
     * 🧩 Usa OpenAI para generar explicación basada en los resultados y prompt de BD
     */
    private function generateContextualExplanation(string $prompt, $results, $summary): string
    {
        $apiKey = env('OPENAI_API_KEY');

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => "Genera un resumen interpretando los siguientes datos: " . json_encode($summary)],
            ],
            'temperature' => 0.7,
            'max_tokens' => 400,
        ]);

        if ($response->failed()) {
            return "⚠️ No se pudo generar la explicación automática.";
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }
}
