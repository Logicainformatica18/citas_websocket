<?php

namespace App\Http\Controllers\AI;


use App\Http\Controllers\Controller;
use App\Models\AITraining;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; // también te falta este para las llamadas a OpenAI

class AITrainingController extends Controller
{
    /**
     * 📋 Listado de entrenamientos IA
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $topic = $request->get('topic');

        $trainings = AITraining::query()
            ->when($search, fn($q) =>
                $q->where('prompt', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhere('interpreter', 'like', "%$search%")
            )
            ->when($topic, fn($q) => $q->where('topic', $topic))
            ->orderBy('topic')
            ->orderByDesc('id')
            ->paginate(10);

        $topics = AITraining::select('topic')->distinct()->pluck('topic');

        // 🔹 JSON para peticiones AJAX
        if ($request->wantsJson()) {
            return response()->json([
                'trainings' => $trainings,
                'topics' => $topics
            ]);
        }

        // 🔹 Render para Inertia (primera carga)
        return Inertia::render('AITraining/AITrainingsIndex', [
            'trainings' => $trainings,
            'topics' => $topics
        ]);
    }

    /**
     * 🧩 Crear un nuevo entrenamiento IA
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic'           => 'required|string|max:150',
            'prompt'          => 'required|string',
            'interpreter'     => 'required|string',
            'component'       => 'nullable|string|max:150',
            'description'     => 'nullable|string',
            'tags'            => 'nullable|array',
            'is_active'       => 'boolean',
            'has_ai_response' => 'boolean',
        ]);

        $validated['tags'] = $validated['tags'] ?? [];
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['has_ai_response'] = $validated['has_ai_response'] ?? true;

        $training = AITraining::create($validated);

        return response()->json([
            'message' => '✅ Entrenamiento IA creado correctamente',
            'training' => $training
        ]);
    }

    /**
     * 📄 Mostrar un entrenamiento específico
     */
    public function show($id)
    {
        $training = AITraining::findOrFail($id);
        return response()->json(['training' => $training]);
    }

    /**
     * ✏️ Actualizar un entrenamiento existente
     */
    public function update(Request $request, $id)
    {
        $training = AITraining::findOrFail($id);

        $validated = $request->validate([
            'topic'           => 'required|string|max:150',
            'prompt'          => 'required|string',
            'interpreter'     => 'required|string',
            'component'       => 'nullable|string|max:150',
            'description'     => 'nullable|string',
            'tags'            => 'nullable|array',
            'is_active'       => 'boolean',
            'has_ai_response' => 'boolean',
        ]);

        $training->update($validated);

        return response()->json([
            'message' => '✅ Entrenamiento IA actualizado correctamente',
            'training' => $training
        ]);
    }

    /**
     * ❌ Eliminar un entrenamiento
     */
    public function destroy($id)
    {
        $training = AITraining::findOrFail($id);
        $training->delete();

        return response()->json(['message' => '🗑️ Entrenamiento IA eliminado correctamente']);
    }

    /**
     * 🔁 Alternar estado activo/inactivo
     */
    public function toggleActive($id)
    {
        $training = AITraining::findOrFail($id);
        $training->is_active = !$training->is_active;
        $training->save();

        return response()->json([
            'message' => $training->is_active
                ? '✅ Entrenamiento activado'
                : '🚫 Entrenamiento desactivado',
            'is_active' => $training->is_active
        ]);
    }

    /**
     * 🤖 Alternar uso de IA
     */
    public function toggleAI($id)
    {
        $training = AITraining::findOrFail($id);
        $training->has_ai_response = !$training->has_ai_response;
        $training->save();

        return response()->json([
            'message' => $training->has_ai_response
                ? '🤖 Respuesta IA activada'
                : '💤 Respuesta IA desactivada',
            'has_ai_response' => $training->has_ai_response
        ]);
    }

    /**
     * 🧬 Duplicar un entrenamiento
     */
    public function duplicate($id)
    {
        $original = AITraining::findOrFail($id);
        $copy = $original->replicate();
        $copy->prompt = '[Copia] ' . $original->prompt;
        $copy->is_active = false;
        $copy->save();

        return response()->json([
            'message' => '📋 Copia creada correctamente',
            'training' => $copy
        ]);
    }
    // ==========================================================
// 🎓 NUEVOS MÉTODOS: Flujo de entrenamiento SQL con IA
// ==========================================================

/**
 * 🧠 1️⃣ Inicia entrenamiento: crea pregunta y genera SQL tentativa
 */
/**
 * 🧠 1️⃣ Inicia entrenamiento: genera SQL basada en la estructura real del Observatorio
 */
public function startTraining(Request $request)
{
    $request->validate([
        'prompt' => 'required|string',
    ]);

    $prompt = trim($request->input('prompt'));
    $userId = auth()->id();

    // ============================================================
    // 🧩 1️⃣ Obtener estructura real de la base de datos
    // ============================================================
    $schemaData = DB::select("
        SELECT table_name, GROUP_CONCAT(column_name ORDER BY ordinal_position SEPARATOR ', ') AS columns
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        GROUP BY table_name
        ORDER BY table_name;
    ");

    $schemaText = collect($schemaData)
        ->map(fn($t) => "{$t->table_name}({$t->columns})")
        ->implode("\n");

    // ============================================================
    // 🧠 2️⃣ Generar SQL basada en la estructura real
    // ============================================================
    try {
        $response = Http::withToken(env('OPENAI_API_KEY'))->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Eres VERA, la analista IA institucional del Observatorio ISIL. 
                        Tu tarea es generar consultas SQL compatibles con MariaDB 
                        según el siguiente esquema real de base de datos.

                        ⚙️ Estructura disponible:
                        {$schemaText}

                        ⚖️ Reglas:
                        - Usa solo tablas y columnas del esquema.
                        - No inventes campos ni tablas.
                        - Devuelve solo la consulta SQL, sin texto adicional.
                        - Si no se puede construir, responde solo 'NO_MATCH'."
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 400,
            ]
        );

        $sqlGenerated = trim($response->json('choices.0.message.content') ?? '');

        // Limpieza de posibles bloques markdown
        $sqlGenerated = preg_replace('/```sql|```/i', '', $sqlGenerated);
        $sqlGenerated = trim($sqlGenerated);

        // Validar respuesta
        if (strtoupper($sqlGenerated) === 'NO_MATCH' || empty($sqlGenerated)) {
            return response()->json([
                'message' => '⚠️ No se pudo generar una consulta válida con la estructura actual.',
            ], 400);
        }

        // ============================================================
        // 💾 3️⃣ Guardar SQL en tabla sqltrainings (historial)
        // ============================================================
        $sqlId = DB::table('sqltrainings')->insertGetId([
            'query_text'   => $prompt,
            'sql_generated'=> $sqlGenerated,
            'created_by'   => $userId,
            'test_status'  => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // ============================================================
        // 💽 4️⃣ Guardar el entrenamiento en aitrainings
        // ============================================================
        $trainingId = DB::table('aitrainings')->insertGetId([
            'topic'               => 'Entrenamientos SQL Automáticos',
            'prompt'              => $prompt,
            'interpreter'         => null,
            'component'           => null,
            'description'         => 'Generación de SQL automática basada en la estructura real del Observatorio ISIL.',
            'explanation_prompt'  => "Consulta SQL generada por IA para la instrucción: {$prompt}",
            'cached_response'     => json_encode(['result' => $sqlGenerated], JSON_UNESCAPED_UNICODE),
            'sql_training_id'     => $sqlId,
            'is_trained'          => 1,
            'training_stage'      => 'draft',
            'last_trained_at'     => now(),
            'is_active'           => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // ============================================================
        // ✅ 5️⃣ Respuesta al frontend (VERA)
        // ============================================================
        return response()->json([
            'training_id'       => $trainingId,
            'sql_training_id'   => $sqlId,
            'sql_generated'     => $sqlGenerated,
            'message'           => '🧩 SQL generada automáticamente en base a la estructura real de la BD. Revisa antes de validar.',
        ]);

    } catch (\Throwable $e) {
        \Log::error('💥 Error generando SQL entrenamiento', ['error' => $e->getMessage()]);
        return response()->json([
            'error' => 'Error generando SQL.',
            'details' => $e->getMessage(),
        ], 500);
    }
}

/**
 * 🧪 2️⃣ Prueba la SQL generada (sin guardar aún)
 */
public function testSql(Request $request)
{
    $request->validate([
        'sql_training_id' => 'required|integer',
        'sql_query' => 'required|string',
    ]);

    $id = $request->input('sql_training_id');
    $sql = trim($request->input('sql_query'));

    try {
        $data = DB::select(DB::raw($sql));

        DB::table('sqltrainings')->where('id', $id)->update([
            'sql_validated' => $sql,
            'last_test_output' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'test_status' => 'ok',
            'test_message' => 'Consulta ejecutada correctamente.',
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
            'rows' => count($data),
            'preview' => array_slice($data, 0, 5),
            'message' => '✅ SQL válida y ejecutada correctamente.',
        ]);
    } catch (\Throwable $e) {
        DB::table('sqltrainings')->where('id', $id)->update([
            'test_status' => 'error',
            'test_message' => $e->getMessage(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'message' => '⚠️ Error al ejecutar la consulta.',
        ], 400);
    }
}

/**
 * 🎓 3️⃣ Finaliza el entrenamiento (GPT explica y guarda en aitrainings)
 */
public function finalizeTraining(Request $request)
{
    $request->validate([
        'sql_training_id' => 'required|integer',
        'prompt' => 'required|string',
    ]);

    $sqlTraining = DB::table('sqltrainings')->where('id', $request->sql_training_id)->first();
    if (!$sqlTraining || $sqlTraining->test_status !== 'ok') {
        return response()->json(['error' => 'SQL no validada o inexistente.'], 400);
    }

    $prompt = $request->input('prompt');

    $explanationPrompt = "Eres VERA, la analista IA institucional. Explica brevemente lo que representa el siguiente resultado SQL:\n\n"
        . $sqlTraining->sql_validated;

    try {
        $res = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $explanationPrompt],
                ],
                'max_tokens' => 300,
                'temperature' => 0.4,
            ]);

        $aiResponse = trim($res->json('choices.0.message.content') ?? 'No se pudo generar explicación.');

        $trainingId = DB::table('aitrainings')->insertGetId([
            'topic' => 'Entrenamientos SQL',
            'prompt' => $prompt,
            'explanation_prompt' => $explanationPrompt,
            'cached_response' => json_encode(['result' => $sqlTraining->sql_validated, 'explanation' => $aiResponse]),
            'sql_training_id' => $sqlTraining->id,
            'is_trained' => 1,
            'training_stage' => 'final',
            'last_trained_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'is_active' => 1,
        ]);

        return response()->json([
            'training_id' => $trainingId,
            'message' => '🎓 Entrenamiento completado y guardado.',
            'ai_response' => $aiResponse,
        ]);
    } catch (\Throwable $e) {
        \Log::error('💥 Error finalizando entrenamiento', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Error al finalizar entrenamiento.'], 500);
    }
}

}
