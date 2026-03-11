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
use Carbon\Carbon;
use App\Helpers\DataSanitizer;
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
            $q->where(function ($query) use ($search) {
                $query->where('prompt', 'like', "%$search%")
                      ->orWhere('description', 'like', "%$search%")
                      ->orWhere('interpreter', 'like', "%$search%");
            })
        )
        ->when($topic, fn($q) => $q->where('topic', $topic))
        ->orderBy('topic')
        ->orderByDesc('id')
        ->paginate(10);

    $topics = AITraining::select('topic')->distinct()->pluck('topic');

  $trainings->getCollection()->transform(function ($item) {

    $raw = $item->created_at;

    if (!$raw) {
        $item->created_at = null;
        return $item;
    }

    // Intentar formato estándar de BD
    try {
        return tap($item, function ($i) use ($raw) {
            $i->created_at = Carbon::parse($raw)->format('d/m/Y H:i');
        });
    } catch (\Exception $e) {}

    // Intentar formato latino
    try {
        return tap($item, function ($i) use ($raw) {
            $dt = Carbon::createFromFormat('d/m/Y H:i', $raw);
            $i->created_at = $dt->format('d/m/Y H:i');
        });
    } catch (\Exception $e) {}

    // Último recurso: dejarlo tal cual
    $item->created_at = $raw;
    return $item;
});



    // 🔹 Devuelve para Inertia o Axios indistintamente
    if ($request->wantsJson()) {
        return response()->json([
            'trainings' => $trainings,
            'topics' => $topics
        ]);
    }

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

    // Núcleo académico
    'careers',
    'courses',
    'career_course',

    'course_language',
    'course_technology',
    'course_methodology',

    // 🔴 FALTABA ESTA
    'certification_course',

    // Mercado laboral
    'job_offers',
    'cities',

    // Entidades
    'market_entities',

    // Relaciones mercado
    'language_job',
    'technology_job',
    'certification_job',
    'methodology_job',

    // Tendencias
    'entity_trends',
    'macro_trends',
    'macro_trend_entity_trend',
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

// $schemaText = collect($schemaData)
//     ->map(fn($t) => "{$t->table_name}({$t->columns})")
//     ->implode("\n");
$schemaText = collect($schemaData)
    ->map(function ($t) {
        $tableName = $t->table_name ?? $t->TABLE_NAME ?? array_values((array)$t)[0];
        $columns   = $t->columns ?? $t->COLUMNS ?? array_values((array)$t)[1] ?? '';
        return "{$tableName}({$columns})";
    })
    ->implode("\n");

$schemaText .= "

===============================
🔗 RELACIONES CLAVE DEL MODELO
===============================

📌 Relación Mercado Laboral:

language_job.market_entity_id → market_entities.id
language_job.job_offer_id → job_offers.id

technology_job.market_entity_id → market_entities.id
technology_job.job_offer_id → job_offers.id

certification_job.market_entity_id → market_entities.id
certification_job.job_offer_id → job_offers.id

📌 Relación Académica:

career_course.career_id → careers.id
career_course.course_id → courses.id

course_language.course_id → courses.id
course_technology.course_id → courses.id
course_methodology.course_id → courses.id

📌 Relación Geográfica:

job_offers.city → cities.city
job_offers.country → cities.country

📌 Relación Tendencias:

entity_trends.market_entity_id → market_entities.id
macro_trend_entity_trend.macro_trend_id → macro_trends.id
macro_trend_entity_trend.entity_trend_id → entity_trends.id
";


            // ============================================================
            // 🧠 3️⃣ Añadir contexto del historial reciente
            // ============================================================
            // $historyExamples = DB::table('sqltrainings')
            //     ->where('test_status', 'ok')
            //     ->orderByDesc('id')
            //     ->limit(5)
            //     ->get(['query_text', 'sql_validated'])
            //     ->map(fn($h, $i) => "🧩 Ejemplo " . ($i + 1) . ":\nUsuario pidió: {$h->query_text}\nSQL usada: {$h->sql_validated}\n")
            //     ->implode("\n");
    //       $historyExamples = DB::table('sqltrainings')
    // ->where('test_status', 'ok')
    // ->whereNotNull('sql_validated')
    // ->orderByDesc('id')
    // ->limit(5)
    // ->get(['query_text', 'sql_validated'])
    // ->map(function ($h, $i) {
    //     $cleanSql = preg_replace('/\b[a-z_]*\.id,?\s*/i', '', $h->sql_validated);
    //     return "🧩 Ejemplo " . ($i + 1) . ":\nUsuario pidió: {$h->query_text}\nSQL usada: {$cleanSql}\n";
    // })
    // ->implode("\n");



            // ============================================================
            // 🧠 4️⃣ Prompt del modelo IA
            // ============================================================
  $systemPrompt = <<<PROMPT
Eres VERA, analista del Observatorio Tecnológico ISIL.

Genera consultas SQL válidas para MariaDB usando únicamente las tablas indicadas.

================================
================================
TABLAS DISPONIBLES
================================

careers
courses
career_course

course_language
course_technology
course_methodology
certification_course

job_offers
cities

market_entities

language_job
technology_job
certification_job
methodology_job

entity_trends
macro_trends
macro_trend_entity_trend

================================
REGLAS IMPORTANTES
================================

1. Solo generar consultas SELECT.
2. No inventar tablas ni columnas.
3. No usar markdown ni explicaciones.
4. Si no puedes generar una consulta válida responde: NO_MATCH

================================
================================
RELACIONES IMPORTANTES
================================

Carreras → cursos

careers.id = career_course.career_id
career_course.course_id = courses.id

Lenguajes

courses.id = course_language.course_id
course_language.language_id = language_job.language_id
language_job.market_entity_id = market_entities.id
language_job.job_offer_id = job_offers.id

Tecnologías

courses.id = course_technology.course_id
course_technology.technology_id = technology_job.technology_id
technology_job.market_entity_id = market_entities.id
technology_job.job_offer_id = job_offers.id

Certificaciones

courses.id = certification_course.course_id
certification_course.certification_id = certification_job.certification_id
certification_job.market_entity_id = market_entities.id
certification_job.job_offer_id = job_offers.id

Metodologías

courses.id = course_methodology.course_id
course_methodology.methodology_id = methodology_job.methodology_id
methodology_job.market_entity_id = market_entities.id
methodology_job.job_offer_id = job_offers.id
================================
CONSULTA PARA LENGUAJES DE UNA CARRERA CON EMPLEO
================================

Si el usuario pregunta por lenguajes enseñados en una carrera con número de ofertas laborales usa este patrón:

SELECT
me.name AS lenguaje,
COUNT(DISTINCT lj.job_offer_id) AS total_ofertas
FROM careers c
JOIN career_course cc ON cc.career_id = c.id
JOIN courses co ON co.id = cc.course_id
JOIN course_language cl ON cl.course_id = co.id
JOIN language_job lj ON lj.language_id = cl.language_id
JOIN market_entities me ON me.id = lj.market_entity_id
WHERE c.name LIKE '%NOMBRE%'
GROUP BY me.name
ORDER BY total_ofertas DESC

TECNOLOGÍAS EN UNA CARRERA CON EMPLEO

SELECT
me.name AS tecnologia,
COUNT(DISTINCT tj.job_offer_id) AS total_ofertas
FROM careers c
JOIN career_course cc ON cc.career_id = c.id
JOIN courses co ON co.id = cc.course_id
JOIN course_technology ct ON ct.course_id = co.id
JOIN technology_job tj ON tj.technology_id = ct.technology_id
JOIN market_entities me ON me.id = tj.market_entity_id
WHERE c.name LIKE '%NOMBRE%'
GROUP BY me.name
ORDER BY total_ofertas DESC

CERTIFICACIONES EN UNA CARRERA CON EMPLEO

SELECT
me.name AS certificacion,
COUNT(DISTINCT cj.job_offer_id) AS total_ofertas
FROM careers c
JOIN career_course cc ON cc.career_id = c.id
JOIN courses co ON co.id = cc.course_id
JOIN certification_course cc2 ON cc2.course_id = co.id
JOIN certification_job cj ON cj.certification_id = cc2.certification_id
JOIN market_entities me ON me.id = cj.market_entity_id
WHERE c.name LIKE '%NOMBRE%'
GROUP BY me.name
ORDER BY total_ofertas DESC

METODOLOGÍAS EN UNA CARRERA CON EMPLEO

SELECT
me.name AS metodologia,
COUNT(DISTINCT mj.job_offer_id) AS total_ofertas
FROM careers c
JOIN career_course cc ON cc.career_id = c.id
JOIN courses co ON co.id = cc.course_id
JOIN course_methodology cm ON cm.course_id = co.id
JOIN methodology_job mj ON mj.methodology_id = cm.methodology_id
JOIN market_entities me ON me.id = mj.market_entity_id
WHERE c.name LIKE '%NOMBRE%'
GROUP BY me.name
ORDER BY total_ofertas DESC

================================
INTERPRETACIÓN DE TIEMPO Y DEMANDA
================================

Cuando el usuario pregunte por demanda en un año específico (por ejemplo 2026, 2025, etc.) debes usar la fecha de publicación de las ofertas laborales.

La fecha se encuentra en la tabla:

job_offers.published_at

Para filtrar por año usa:

YEAR(job_offers.published_at) = AÑO

Ejemplo:

Si el usuario pregunta:
"¿Qué lenguajes tienen mayor demanda en 2026?"

Genera una consulta como:

SELECT
me.name AS lenguaje,
COUNT(DISTINCT lj.job_offer_id) AS total_ofertas
FROM language_job lj
JOIN market_entities me ON me.id = lj.market_entity_id
JOIN job_offers jo ON jo.id = lj.job_offer_id
WHERE YEAR(jo.published_at) = 2026
GROUP BY me.name
ORDER BY total_ofertas DESC

Si el usuario pregunta por:

- "este año"
- "actualmente"
- "año actual"

usa:

YEAR(job_offers.published_at) = YEAR(CURDATE())

Si el usuario no menciona año, no agregues filtro de fecha.
================================
OBJETIVO
================================

Generar consultas SQL limpias y correctas para analizar carreras, tecnologías, lenguajes y mercado laboral del Observatorio ISIL.
PROMPT;



// \Log::channel('daily')->info('🧠 [VERA] FULL PROMPT (startTraining)', [
//     'user_prompt' => $prompt,
//     'schema_text' => mb_substr($schemaText, 0, 8000), // 🔍 parte o todo el esquema real

//     'system_prompt_full' => $systemPrompt, // 🔥 el texto completo que se envía a GPT
// ]);


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
                'message' => '🧩 Consulta generada automáticamente con contexto real e historial.',
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
        'sql_training_id' => 'required|integer|exists:sqltrainings,id',
        'prompt' => 'required|string',
        'voice_enabled' => 'nullable|boolean',
        'save' => 'nullable|boolean',
        'limit' => 'nullable|integer|min:1|max:1000',
    ]);

    $sqlTraining = DB::table('sqltrainings')->where('id', $request->sql_training_id)->first();

    if (!$sqlTraining || $sqlTraining->test_status !== 'ok') {
        return response()->json(['error' => 'SQL no validada o inexistente.'], 400);
    }

    $prompt       = $request->input('prompt');
    $voiceEnabled = filter_var($request->input('voice_enabled', false), FILTER_VALIDATE_BOOLEAN);
    $saveTraining = filter_var($request->input('save', false), FILTER_VALIDATE_BOOLEAN);
    $limit        = $request->input('limit');

    try {
        /* ============================================================
           1️⃣ Ejecutar SQL real
        ============================================================ */
        $rawSql = trim((string) $sqlTraining->sql_validated);

        if ($rawSql === '' || !str_starts_with(strtoupper($rawSql), 'SELECT')) {
            return response()->json(['error' => 'SQL inválido o no es SELECT.'], 400);
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|EXEC|CREATE|REPLACE|MERGE)\b/i', $rawSql)) {
            return response()->json(['error' => '❌ Operación SQL no permitida.'], 400);
        }

        $results = DataSanitizer::cleanCollection(
            collect(DB::select($rawSql)),
            ['company' => fn ($v) => $v && $v !== '0' ? trim($v) : 'Sin nombre']
        )->toArray();

        if (empty($results)) {
            return response()->json(['error' => 'La consulta no devolvió resultados.'], 400);
        }

        if ($limit !== null) {
            $results = array_slice($results, 0, (int) $limit);
        }

        /* ============================================================
           2️⃣ Exportar Excel
        ============================================================ */
        $filename     = "observatorio_result_" . now()->format('Ymd_His') . ".xlsx";
        $relativePath = "sql_results/{$filename}";

        Excel::store(
            new ArrayExport($results, [
                'title' => 'Resultados del Observatorio ISIL',
                'created_at' => now()->format('d/m/Y H:i'),
            ]),
            $relativePath,
            'public'
        );

        $excelPath = asset("storage/{$relativePath}");

        /* ============================================================
           3️⃣ Generar TEXTO CORTO (summary para card)
        ============================================================ */
        $contextPrompt = "
Eres VERA, analista del Observatorio ISIL.

Genera una descripción MUY BREVE (máx. 12 palabras) para un gráfico de dashboard.

Datos:
" . json_encode($results, JSON_UNESCAPED_UNICODE) . "

Reglas:
- Máximo 12 palabras
- Una sola frase
- Sin números largos
- Sin porcentajes
- Sin fechas
- Estilo ejecutivo
";

        $res = Http::withToken(env('OPENAI_API_KEY'))->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres VERA, analista institucional del Observatorio ISIL.'],
                    ['role' => 'user', 'content' => $contextPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 50,
            ]
        );

        if (!$res->ok()) {
            return response()->json(['error' => 'Error al generar resumen IA'], 500);
        }

        $summaryText = trim($res->json('choices.0.message.content'));

        /* ============================================================
           4️⃣ Guardar SUMMARY en sqltrainings (CLAVE 🔑)
        ============================================================ */
        DB::table('sqltrainings')->where('id', $sqlTraining->id)->update([
            'summary'    => $summaryText,
            'updated_at'=> now(),
        ]);

        /* ============================================================
           5️⃣ Voz (opcional)
        ============================================================ */
        $voiceUrl = null;
        if ($voiceEnabled && $summaryText) {
            try {
                $voiceRes = Http::post(route('api.ai.voice.speak'), ['text' => $summaryText]);
                if ($voiceRes->ok()) {
                    $voiceUrl = $voiceRes->json()['url'] ?? null;
                }
            } catch (\Throwable $e) {}
        }

        /* ============================================================
           6️⃣ Guardar entrenamiento completo (opcional)
        ============================================================ */
        $trainingId = null;
        if ($saveTraining) {
            $trainingId = DB::table('aitrainings')->insertGetId([
                'topic' => 'Análisis Observatorio',
                'prompt' => $prompt,
                'interpreter' => 'AITrainingController@finalizeTraining',
                'component' => 'vera-training',
                'description' => $summaryText, // 🔥 reutilizable
                'cached_response' => json_encode([
                    'summary' => $summaryText,
                    'excel'   => $excelPath,
                    'voice'   => $voiceUrl,
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

        /* ============================================================
           7️⃣ Respuesta FINAL
        ============================================================ */
        return response()->json([
            'training_id'     => $trainingId,
            'sql_training_id' => $sqlTraining->id,
            'summary'         => $summaryText, // 👈 PARA EL CARD
            'excel_path'      => $excelPath,
            'voice_url'       => $voiceUrl,
            'message' => $saveTraining
                ? '🎓 Entrenamiento guardado con resumen.'
                : '💡 Resumen generado correctamente.',
        ]);

    } catch (\Throwable $e) {
        Log::error('💥 Error finalizeTraining', ['error' => $e->getMessage()]);
        return response()->json([
            'error' => 'Error al finalizar análisis.',
            'details' => $e->getMessage(),
        ], 500);
    }
}





}
