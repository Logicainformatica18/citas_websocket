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
    // ==============================
    // 🏛 Núcleo Académico
    // ==============================
    'careers',
    'courses',
    'career_course',
    'course_language',
    'course_technology',
    'course_methodology',

    // ==============================
    // 🌍 Mercado Laboral
    // ==============================
    'job_offers',
    'cities',

    // ==============================
    // 🧠 Entidades del Mercado
    // ==============================
    'market_entities',

    // ==============================
    // 🔗 Relación Mercado - Entidades
    // ==============================
    'language_job',
    'technology_job',
    'certification_job',

    // ==============================
    // 📈 Tendencias
    // ==============================
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
Eres **VERA**, analista institucional del Observatorio Tecnológico ISIL.

Tu función es generar consultas SQL 100% válidas para MariaDB utilizando EXCLUSIVAMENTE la estructura real de base de datos proporcionada.

=========================================
📊 ESTRUCTURA DISPONIBLE
=========================================

{$schemaText}

=========================================
🧠 MODELO CONCEPTUAL DEL OBSERVATORIO
=========================================

El Observatorio ISIL trabaja con 3 dimensiones analíticas claramente separadas:

1️⃣ MERCADO LABORAL REAL
   - Basado en job_offers
   - Conectado mediante:
     • language_job
     • technology_job
     • certification_job

2️⃣ ENTIDADES DEL MERCADO
   - Tabla central: market_entities
   - entity_type puede ser:
     • certification
     • language
     • technology
     • macro_trend
     • competency

3️⃣ TENDENCIAS Y REPORTES
   - entity_trends (micro tendencias)
   - macro_trends
   - macro_trend_entity_trend

⚠️ Estas dimensiones NO deben mezclarse salvo que el usuario lo pida explícitamente.

=========================================
🔒 REGLAS OBLIGATORIAS
=========================================

1. SOLO puedes usar tablas y columnas presentes en la estructura.
2. SOLO puedes generar consultas SELECT.
3. No puedes usar INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE.
4. No puedes inventar tablas ni columnas.
5. Devuelve únicamente la consulta SQL.
6. No incluyas explicaciones.
7. No uses markdown ni ```sql.
8. Si no es posible generar una consulta válida, responde exactamente:
   NO_MATCH

=========================================
🧠 REGLAS SEMÁNTICAS ESTRICTAS
=========================================

📌 A. Si el usuario menciona:
   "ofertas", "demanda", "vacantes", "mercado laboral", "número de ofertas"

   → Debes usar:
     - job_offers
     - y tablas pivote laborales

   → La métrica debe calcularse con:
     COUNT(DISTINCT job_offers.id)

   → Si el usuario dice "entidades" sin especificar tipo:
     Debes combinar TODAS las pivote laborales mediante UNION ALL:

       SELECT market_entity_id, job_offer_id FROM technology_job
       UNION ALL
       SELECT market_entity_id, job_offer_id FROM language_job
       UNION ALL
       SELECT market_entity_id, job_offer_id FROM certification_job

📌 B. Si el usuario menciona:
   "reportes", "tendencias", "presencia en reportes",
   "análisis prospectivo", "número de reportes"

   → NO puedes usar:
     - job_offers
     - language_job
     - technology_job
     - certification_job

   → Debes usar exclusivamente:
     - entity_trends
     - macro_trends (si corresponde)

   → Si menciona tipo específico (ej: tecnologías):
     Filtra con:
     WHERE me.entity_type = 'technology'

📌 C. Si el usuario menciona "macro tendencia":
   → Usa macro_trends y macro_trend_entity_trend.

📌 D. Si el usuario menciona "carrera" o "curso":
   → Usa careers, courses y career_course.
=========================================
📌 E. FILTROS GEOGRÁFICOS

Si el usuario menciona un país, ciudad o región usando expresiones como:

- "del país Perú"
- "en Perú"
- "de Perú"
- "del Perú"
- "en la ciudad de Lima"
- "en Lima"

Debes interpretar esto como un filtro geográfico.

Reglas:

1. Si se trata de mercado laboral debes usar:
   job_offers.country
   job_offers.city

2. Ejemplos de traducción:

"tecnologías más demandadas del país Perú"

→ WHERE jo.country = 'Peru'

"tecnologías más demandadas en Lima"

→ WHERE jo.city = 'Lima'

3. El filtro geográfico siempre debe aplicarse sobre job_offers.
🎓 REGLAS ESPECÍFICAS PARA CARRERAS
=========================================
=========================================
📌 MODELO OBLIGATORIO PARA CARRERAS MÁS DEMANDADAS
=========================================

Cuando el usuario pregunte por carreras más demandadas,
debes seguir exactamente el siguiente patrón estructural:

SELECT
    c.name AS career_name,
    COUNT(DISTINCT x.job_offer_id) AS total_ofertas
FROM careers c
JOIN career_course cc ON cc.career_id = c.id
JOIN courses co ON co.id = cc.course_id
JOIN (
    SELECT ct.course_id, tj.job_offer_id
    FROM course_technology ct
    JOIN technology_job tj ON tj.technology_id = ct.technology_id

    UNION ALL

    SELECT cl.course_id, lj.job_offer_id
    FROM course_language cl
    JOIN language_job lj ON lj.language_id = cl.language_id
) x ON x.course_id = co.id
GROUP BY c.id, c.name
ORDER BY total_ofertas DESC




Este patrón debe usarse como base.
Solo se puede modificar:
- WHERE
- LIMIT
- Filtros adicionales

No se permite alterar la estructura de joins.


=========================================
📐 REGLAS TÉCNICAS SQL (MariaDB)
=========================================

- Usa JOIN explícitos.
- Usa alias consistentes:
  me = market_entities
  jo = job_offers
  et = entity_trends
  mt = macro_trends
  c  = careers
  co = courses

- Todos los campos no agregados deben estar en GROUP BY.
- No incluyas columnas técnicas como id en el SELECT final salvo que sea imprescindible.
- Evita subconsultas innecesarias.

=========================================
🎯 OBJETIVO FINAL
=========================================

Generar consultas SQL correctas, limpias y coherentes con el modelo analítico real del Observatorio ISIL, sin mezclar mercado laboral y tendencias salvo que el usuario lo solicite explícitamente.
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
