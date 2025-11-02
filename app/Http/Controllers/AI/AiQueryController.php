<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiQueryController extends Controller
{
    public function query(Request $request)
    {
        $userMessage = trim($request->get('query', ''));
        if (!$userMessage) {
            return response()->json(['error' => '⚠️ Consulta vacía'], 400);
        }

        $userId = auth()->id();
        $normalized = mb_strtolower($userMessage);
        $hash = hash('sha256', $normalized);

        // ============================
        // 1️⃣ Revisar si existe en cache
        // ============================
        $cached = DB::table('ai_queries')->where('hash', $hash)->first();
        if ($cached) {
            Log::info("♻️ [AI QUERY CACHE] Reutilizando consulta", ['query' => $userMessage]);
            return response()->json([
                'sql' => $cached->sql_generated,
                'summary' => $cached->summary,
                'rows' => json_decode($cached->rows_json, true),
                'cached' => true,
            ]);
        }

        // ============================
        // 2️⃣ Obtener estructura de BD (JSON o generado)
        // ============================
        $schema = Storage::exists('ai/schema.json')
            ? Storage::get('ai/schema.json')
            : json_encode($this->getDatabaseSchema());

        // ============================
        // 3️⃣ Generar SQL con GPT
        // ============================
        $prompt = "Eres un analista SQL experto en MariaDB.
Genera una consulta SELECT válida y segura según este esquema:
{$schema}

Pregunta: {$userMessage}
Devuelve SOLO el SQL sin explicación ni texto adicional.";

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un generador SQL institucional para el Observatorio ISIL.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 400,
            ]);

        $sql = trim($response->json('choices.0.message.content') ?? '');

        // Seguridad básica: solo permitir SELECT
        if (!preg_match('/^select/i', $sql)) {
            return response()->json(['error' => 'Consulta no segura: solo se permite SELECT.'], 400);
        }

        // ============================
        // 4️⃣ Ejecutar la consulta
        // ============================
        try {
            $rows = DB::select($sql);
        } catch (\Throwable $e) {
            Log::error('💥 Error ejecutando SQL IA', ['sql' => $sql, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al ejecutar SQL.', 'details' => $e->getMessage()], 500);
        }

        // ============================
        // 5️⃣ Generar resumen IA (opcional)
        // ============================
        $summary = null;
        if (count($rows) > 0) {
            try {
                $summaryPrompt = "Resume brevemente estos resultados:\n" . json_encode($rows, JSON_PRETTY_PRINT);
                $summary = Http::withToken(env('OPENAI_API_KEY'))
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            ['role' => 'system', 'content' => 'Eres VERA, analista institucional del Observatorio ISIL. Explica resultados de forma breve, ejecutiva y técnica.'],
                            ['role' => 'user', 'content' => $summaryPrompt],
                        ],
                        'max_tokens' => 300,
                        'temperature' => 0.3,
                    ])
                    ->json('choices.0.message.content');
            } catch (\Throwable $e) {
                Log::warning('⚠️ Error generando resumen IA', ['error' => $e->getMessage()]);
            }
        }

        // ============================
        // 6️⃣ Guardar cache
        // ============================
        DB::table('ai_queries')->insert([
            'query_text' => $userMessage,
            'sql_generated' => $sql,
            'summary' => $summary,
            'rows_json' => json_encode($rows, JSON_UNESCAPED_UNICODE),
            'hash' => $hash,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ============================
        // 7️⃣ Respuesta final
        // ============================
        return response()->json([
            'sql' => $sql,
            'summary' => $summary,
            'rows' => $rows,
            'cached' => false,
        ]);
    }

    // ==========================================================
    // 🔍 Genera estructura simple de la base de datos
    // ==========================================================
    private function getDatabaseSchema(): array
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = env('DB_DATABASE');
        $schema = [];

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$dbName}"};
            $columns = DB::select("SHOW COLUMNS FROM {$tableName}");
            $schema[$tableName] = collect($columns)->pluck('Field');
        }

        return ['tables' => $schema];
    }
}
