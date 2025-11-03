<?php

namespace App\Http\Controllers\AI;


use App\Http\Controllers\Controller;
use App\Models\AITraining;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; // también te falta este para las llamadas a OpenAI
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArrayExport;
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
            ->when(
                $search,
                fn($q) =>
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
            'topic' => 'required|string|max:150',
            'prompt' => 'required|string',
            'interpreter' => 'required|string',
            'component' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'is_active' => 'boolean',
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
            'topic' => 'required|string|max:150',
            'prompt' => 'required|string',
            'interpreter' => 'required|string',
            'component' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'is_active' => 'boolean',
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
            'force_save' => 'nullable|boolean',
        ]);

        $prompt = trim($request->input('prompt'));
        $userId = auth()->id();
        $forceSave = filter_var($request->input('force_save', false), FILTER_VALIDATE_BOOLEAN);

        try {
            // ============================================================
            // 🧩 1️⃣ Verificar si existe entrenamiento similar
            // ============================================================
            $similar = DB::table('aitrainings')
                ->where('topic', 'like', '%SQL%')
                ->whereRaw('LOWER(prompt) LIKE ?', ['%' . strtolower($prompt) . '%'])
                ->orderByDesc('id')
                ->first();


            // Si hay similar pero el usuario eligió forzar, continúa normalmente
            if ($similar && $forceSave) {
                // Log::info('🧩 Forzando creación de nuevo entrenamiento pese a duplicado.');
            }


            // ============================================================
            // 🧩 2️⃣ Obtener estructura real de la base de datos
            // ============================================================
            $database = DB::getDatabaseName();

            $allowedTables = [
                'careers',
                'courses',
                'career_course',
                'languages',
                'technologies',
                'methodologies',
                'course_language',
                'course_technology',
                'course_methodology',
                'language_metrics',
                'technology_metrics',
                'methodology_metrics',
                'job_offers',
                'cities'
            ];

            $schemaData = DB::select("
            SELECT table_name,
                   GROUP_CONCAT(column_name ORDER BY ordinal_position SEPARATOR ', ') AS columns
            FROM information_schema.columns
            WHERE table_schema = ?
              AND table_name IN ('" . implode("','", $allowedTables) . "')
            GROUP BY table_name
            ORDER BY table_name;
        ", [$database]);

            $schemaText = collect($schemaData)
                ->map(fn($t) => "{$t->table_name}({$t->columns})")
                ->implode("\n");

            $schemaText .= "\n\n🔗 Relaciones clave:
career_course.career_id → careers.id
career_course.course_id → courses.id
course_language.course_id → courses.id
course_technology.course_id → courses.id
course_methodology.course_id → courses.id
language_metrics.language_id → languages.id
technology_metrics.technology_id → technologies.id
methodology_metrics.methodology_id → methodologies.id
job_offers.city → cities.city
job_offers.country → cities.country
";

            // ============================================================
            // 🧠 3️⃣ Añadir contexto del historial reciente
            // ============================================================
            $historyExamples = DB::table('sqltrainings')
                ->where('test_status', 'ok')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['query_text', 'sql_validated'])
                ->map(fn($h, $i) => "🧩 Ejemplo " . ($i + 1) . ":\nUsuario pidió: {$h->query_text}\nSQL usada: {$h->sql_validated}\n")
                ->implode("\n");

            // ============================================================
            // 🧠 4️⃣ Prompt del modelo IA
            // ============================================================
         $systemPrompt = <<<PROMPT
Eres **VERA**, la analista IA institucional del Observatorio ISIL.
Tu misión es generar consultas SQL **compatibles con MariaDB**, precisas y útiles para analizar datos reales del Observatorio.

Tienes acceso al siguiente esquema y relaciones entre tablas:

{$schemaText}

⚙️ **Reglas de comportamiento:**
1. Usa **JOINs correctos** con claves foráneas (ejemplo: `technology_metrics.technology_id = technologies.id`).
2. Devuelve **solo la consulta SQL**, sin texto adicional, sin ```sql ni explicaciones.
3. Evita funciones no soportadas o incompatibles con MariaDB.
4. Si no puedes construir una consulta válida, devuelve únicamente `NO_MATCH`.

⚙️ **Reglas semánticas del Observatorio ISIL:**
- "Más demandadas", "más solicitadas", "más requeridas", "más populares" se refieren al campo **`jobs_found_count`**.
- Agrupa siempre por el identificador y nombre de la tabla principal (ejemplo: `GROUP BY t.id, t.name`).
- Usa **SUM(jobs_found_count)** para calcular la demanda total.
- Ordena con `ORDER BY jobs_found_count DESC LIMIT 10`.

⚙️ **Selección de tabla según contexto:**
- Si el usuario menciona *tecnologías, herramientas, frameworks, lenguajes de programación, stacks o software*, usa la tabla `technology_metrics` y une con `technologies`.
- Si menciona *lenguajes, idiomas o programming languages*, usa `language_metrics` y une con `languages`.
- Si menciona *metodologías, métodos ágiles, scrum, kanban, metodologías de trabajo*, usa `methodology_metrics` y une con `methodologies`.
- Si habla de *ofertas laborales, modalidades, países o ciudades*, usa `job_offers` (ya relacionada con `cities`).

⚙️ **Reglas adicionales:**
- Si el usuario pide por país o región, agrega `WHERE country = '...'`.
- Si el usuario pide el top global, omite el filtro de país.
- Si menciona “por carrera” o “por curso”, usa `career_course` y `courses` para vincular las métricas.
- Siempre usa alias claros:  
  `t` para tecnologías, `l` para lenguajes, `m` para metodologías, `tm`/`lm`/`mm` para métricas.
- Asegúrate de que todos los campos no agregados estén en el `GROUP BY` (para evitar errores `ONLY_FULL_GROUP_BY`).

Ejemplos previos:
{$historyExamples}

PROMPT;


            \Log::info('🧠 [VERA] Generando SQL entrenamiento', [
                'prompt' => $prompt,
                'schema_length' => strlen($schemaText),
                'examples' => strlen($historyExamples),
            ]);

            // ============================================================
            // 🤖 5️⃣ Llamada a OpenAI
            // ============================================================
            $response = Http::withToken(env('OPENAI_API_KEY'))->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                ]
            );

            if (!$response->ok()) {
                \Log::error('💥 Error HTTP OpenAI', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['message' => '💥 Error al conectar con OpenAI'], 500);
            }

            $sqlGenerated = trim($response->json('choices.0.message.content') ?? '');
            $sqlGenerated = preg_replace('/```sql|```/i', '', $sqlGenerated);
            $sqlGenerated = trim($sqlGenerated);

            if (strtoupper($sqlGenerated) === 'NO_MATCH' || empty($sqlGenerated)) {
                return response()->json([
                    'message' => '⚠️ No se pudo generar una consulta válida con la estructura actual.',
                ], 400);
            }

            // ============================================================
            // 💾 6️⃣ Guardar SQL y entrenamiento (solo si no es duplicado)
            // ============================================================
            $sqlId = DB::table('sqltrainings')->insertGetId([
                'query_text' => $prompt,
                'sql_generated' => $sqlGenerated,
                'created_by' => $userId,
                'test_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $trainingId = DB::table('aitrainings')->insertGetId([
                'topic' => 'Entrenamientos SQL Automáticos',
                'prompt' => $prompt,
                'interpreter' => 'SQL Trainer',
                'component' => 'vera-training',
                'description' => 'Entrenamiento IA SQL contextualizado con esquema y relaciones reales del Observatorio ISIL.',
                'explanation_prompt' => "Consulta SQL generada automáticamente para: {$prompt}",
                'cached_response' => json_encode(['result' => $sqlGenerated], JSON_UNESCAPED_UNICODE),
                'sql_training_id' => $sqlId,
                'is_trained' => 1,
                'training_stage' => 'draft',
                'last_trained_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ============================================================
            // ✅ 7️⃣ Respuesta al frontend
            // ============================================================
            return response()->json([
                'training_id' => $trainingId,
                'sql_training_id' => $sqlId,
                'sql_generated' => $sqlGenerated,
                'message' => '🧩 SQL generada automáticamente con contexto real e historial.',
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
    /**
     * 🧪 Prueba una consulta SQL generada por VERA (entrenamiento SQL)
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
            // ============================================================
            // 🧠 1️⃣ Preprocesamiento: limpiar SQL
            // ============================================================
            $sql = preg_replace('/;+\s*$/', '', $sql); // elimina ';' final
            $sql = trim(str_replace(["\n", "\r"], ' ', $sql));

            // ============================================================
            // 🔍 2️⃣ Detectar si la consulta hace referencia a web_scrapping
            // ============================================================
            $isWebScrapping = str_contains(strtolower($sql), 'web_scrapping.');
            $connection = DB::connection(); // usa la conexión por defecto

            // ============================================================
            // 🧩 3️⃣ Verificar existencia de tablas antes de ejecutar
            // ============================================================
            if (preg_match_all('/from\s+([a-zA-Z0-9_\.]+)/i', $sql, $matches)) {
                foreach ($matches[1] as $tbl) {
                    $tbl = trim(str_replace('`', '', $tbl));

                    // si tiene prefijo, separar schema.tabla
                    if (str_contains($tbl, '.')) {
                        [$schema, $table] = explode('.', $tbl);
                    } else {
                        $schema = DB::getDatabaseName();
                        $table = $tbl;
                    }

                    $exists = DB::selectOne("
                    SELECT COUNT(*) as c
                    FROM information_schema.tables
                    WHERE table_schema = ? AND table_name = ?
                ", [$schema, $table]);

                    if (!$exists || !$exists->c) {
                        throw new \Exception("❌ La tabla '{$tbl}' no existe en la base de datos '{$schema}'.");
                    }
                }
            }

            // ============================================================
            // ⚙️ 4️⃣ Ejecutar SQL en conexión por defecto
            // ============================================================
            $data = $connection->select($sql);

            $rows = count($data);

            // ============================================================
            // 💾 5️⃣ Guardar resultado en historial sqltrainings
            // ============================================================
            DB::table('sqltrainings')->where('id', $id)->update([
                'sql_validated' => $sql,
                'last_test_output' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'test_status' => 'ok',
                'test_message' => $isWebScrapping
                    ? "Ejecutada correctamente (incluye tablas del esquema web_scrapping)"
                    : "Ejecutada correctamente en la BD principal",
                'updated_at' => now(),
            ]);

            // ============================================================
            // ✅ 6️⃣ Respuesta al frontend
            // ============================================================
            return response()->json([
                'status' => 'ok',
                'rows' => $rows,
             'preview' => $data, // devuelve todos, no los corta
                'message' => $isWebScrapping
                    ? '✅ Consulta ejecutada correctamente (usando tablas del esquema web_scrapping).'
                    : '✅ Consulta ejecutada correctamente en la base principal.',
            ]);
        } catch (\Throwable $e) {
            // ============================================================
            // ❌ 7️⃣ Registro de error y respuesta
            // ============================================================
            DB::table('sqltrainings')->where('id', $id)->update([
                'test_status' => 'error',
                'test_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            Log::error('⚠️ Error ejecutando SQL', [
                'sql' => $sql,
                'error' => $e->getMessage(),
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
            'voice_enabled' => 'nullable|boolean',
            'save' => 'nullable|boolean',
           'limit' => 'nullable|integer|min:1|max:1000',

        ]);

        $sqlTraining = DB::table('sqltrainings')->where('id', $request->sql_training_id)->first();
        if (!$sqlTraining || $sqlTraining->test_status !== 'ok') {
            return response()->json(['error' => 'SQL no validada o inexistente.'], 400);
        }

        $prompt = $request->input('prompt');
        $voiceEnabled = filter_var($request->input('voice_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $saveTraining = filter_var($request->input('save', false), FILTER_VALIDATE_BOOLEAN);
       
$limit = $request->input('limit'); // sin valor por defecto


        try {
            // ============================================================
            // 📊 1️⃣ Recuperar los datos previos (última ejecución)
            // ============================================================
            $preview = json_decode($sqlTraining->last_test_output ?? '[]', true);
            if (empty($preview)) {
                return response()->json(['error' => 'No hay datos previos para analizar.'], 400);
            }
  
          // 🔹 Si se envía un límite, aplicar; si no, exportar todos los resultados
 
if ($limit !== null) {
    $preview = array_slice($preview, 0, (int) $limit);
}


            // ============================================================
            // 💾 2️⃣ Exportar a Excel (.xlsx)
            // ============================================================
            $filename = "observatorio_result_" . now()->format('Ymd_His') . ".xlsx";
            $relativePath = "sql_results/{$filename}";

            Excel::store(new ArrayExport($preview, [
                'title' => 'Resultados del Observatorio ISIL',
                'created_at' => now()->format('d/m/Y H:i'),
            ]), $relativePath, 'public');

            $excelPath = asset("storage/{$relativePath}");

            // ============================================================
            // 🧠 3️⃣ Explicación contextual (no SQL)
            // ============================================================
            $contextPrompt = "
Eres **VERA**, la analista de datos institucional del Observatorio Tecnológico ISIL**.

Debes analizar los siguientes datos en JSON y explicar qué revelan, en el contexto de empleabilidad tecnológica y demanda laboral.

JSON de entrada:
" . json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

Instrucciones:
- No hables de SQL ni de consultas.
- Describe los patrones, tendencias o jerarquías.
- Si detectas rankings, explica por qué esos elementos son los más importantes.

";

            $res = Http::withToken(env('OPENAI_API_KEY'))->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $contextPrompt],
                        ['role' => 'user', 'content' => "Prompt original: {$prompt}"],
                    ],
                    'temperature' => 0.45,
                    'max_tokens' => 300,
                ]
            );

            if (!$res->ok()) {
                Log::error('💥 Error HTTP OpenAI (finalize)', [
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                return response()->json(['error' => 'Error al conectar con OpenAI'], 500);
            }

            $aiResponse = trim($res->json('choices.0.message.content') ?? 'No se pudo generar explicación contextual.');

            // ============================================================
            // 🔊 4️⃣ Generar voz si está activado
            // ============================================================
            $voiceUrl = null;
            if ($voiceEnabled && !empty($aiResponse)) {
                try {
                    $voiceRes = Http::post(route('api.ai.voice.speak'), ['text' => $aiResponse]);
                    if ($voiceRes->ok() && isset($voiceRes->json()['url'])) {
                        $voiceUrl = $voiceRes->json()['url'];
                    }
                } catch (\Throwable $e) {
                    Log::warning('⚠️ Error generando voz para análisis', ['error' => $e->getMessage()]);
                }
            }

            // ============================================================
            // 💽 5️⃣ Guardar si el usuario confirma
            // ============================================================
            $trainingId = null;
            if ($saveTraining) {
                $trainingId = DB::table('aitrainings')->insertGetId([
                    'topic' => 'Análisis Observatorio',
                    'prompt' => $prompt,
                    'interpreter' => 'AITrainingController@finalizeTraining',
                    'component' => 'vera-training',
                    'description' => 'Análisis contextual de resultados del Observatorio ISIL.',
                    'explanation_prompt' => $contextPrompt,
                    'cached_response' => json_encode([
                        'result' => $preview,
                        'explanation' => $aiResponse,
                        'excel' => $excelPath,
                        'voice' => $voiceUrl,
                    ], JSON_UNESCAPED_UNICODE),
                    'sql_training_id' => $sqlTraining->id,
                    'is_trained' => 1,
                    'training_stage' => 'final',
                    'last_trained_at' => now(),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ============================================================
            // ✅ 6️⃣ Respuesta al frontend
            // ============================================================
            return response()->json([
                'training_id' => $trainingId,
                'ai_response' => $aiResponse,
                'excel_path' => $excelPath,
                'voice_url' => $voiceUrl,
                'message' => $saveTraining
                    ? '🎓 Análisis guardado correctamente.'
                    : '💡 Análisis generado correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('💥 Error finalizando análisis observatorio', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error al finalizar análisis observatorio.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }



}
