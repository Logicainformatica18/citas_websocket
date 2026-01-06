<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiQueryController extends Controller
{
    public function query(Request $request)
    {
        $userMessage = trim($request->get('query', ''));
        $selectedTables = $request->get('tables', []);
        $context = $request->get('context');

        if (!$userMessage) {
            return response()->json(['error' => '⚠️ Consulta vacía'], 400);
        }

        $userId = auth()->id();
        $normalized = mb_strtolower($userMessage);
        $hash = hash('sha256', $normalized);

        // 1️⃣ Cache
        $cached = DB::table('ai_queries')->where('hash', $hash)->first();
        if ($cached) {
            return response()->json([
                'sql' => $cached->sql_generated,
                'summary' => $cached->summary,
                'rows' => json_decode($cached->rows_json, true),
                'cached' => true,
            ]);
        }

        // 2️⃣ Estructura enriquecida
        $schemaArray = $this->getDatabaseSchema($selectedTables, $context);
        $schema = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // 3️⃣ Contexto semántico: ayuda al modelo a entender las uniones reales
$semanticHints = <<<HINTS
📘 CONTEXTO SEMÁNTICO (Observatorio ISIL):

⚠️ REGLA FUNDAMENTAL DE FUENTES DE DATOS:

1️⃣ TABLAS PIVOT (*_job) → FUENTE PRIMARIA DE DEMANDA REAL
Usa estas tablas cuando la pregunta implique:
- conteos
- rankings
- “cuántos empleos”
- “más demandados”
- “ofertas que piden X”

Relaciones clave:
- languages → language_job → job_offers
- technologies → technology_job → job_offers
- methodologies → methodology_job → job_offers
- certifications → certification_job → job_offers
- competencies → competency_job_offer → job_offers

Ejemplo correcto:
- “¿Cuántas ofertas piden Python?”
→ COUNT(*) desde language_job JOIN languages JOIN job_offers

2️⃣ TABLAS METRICS (*_metrics) → AGREGADOS HISTÓRICOS
Usa estas tablas SOLO si la pregunta menciona:
- evolución
- tendencia
- crecimiento
- histórico
- comparación temporal
- run_date, año, mes

Ejemplo:
- “Evolución de la demanda de Python en 2024”
→ language_metrics filtrando por language_id y fechas

3️⃣ FILTRO POR NOMBRE
Cuando se mencione un elemento específico:
- Filtra SIEMPRE por:
  - languages.name
  - technologies.name
  - methodologies.name
  - certifications.name
  - competencies.name

⚠️ NO uses tablas *_metrics para conteos actuales salvo que el usuario lo pida explícitamente.
HINTS;


        // 4️⃣ Prompt con esquema + contexto
        $prompt = <<<PROMPT
Eres un analista SQL experto en MariaDB que trabaja para el Observatorio de Empleabilidad y Tecnología ISIL.

{$semanticHints}

Tu tarea es generar una consulta SQL **válida, segura y optimizada** en base al siguiente esquema de base de datos:

===========================
📊 ESTRUCTURA DISPONIBLE
{$schema}
===========================

✅ INSTRUCCIONES:
- Solo utiliza las tablas, columnas y relaciones que aparecen en el esquema.
- Usa **SELECT** exclusivamente (nunca INSERT, UPDATE ni DELETE).
- Cuando existan relaciones (por ejemplo A.id → B.a_id), aplica los JOIN necesarios.
- Si se mencionan conteos, usa COUNT(*); si se pide promedio, usa AVG(columna).
- Si no se especifica el campo exacto, elige el más lógico dentro del esquema.
- Si hay duda, prioriza claridad y compatibilidad con MariaDB.
- Si se menciona un lenguaje (ej. "Python"), búscalo en languages.name y conéctalo con language_metrics.language_id.
- Para conteos, rankings o demanda actual, prioriza tablas *_job sobre *_metrics.

===========================
🧠 PREGUNTA DEL USUARIO
{$userMessage}
===========================

💾 Devuelve SOLO la consulta SQL completa (sin explicación ni texto adicional).
PROMPT;

     // 🧾 LOG COMPLETO: Ver exactamente lo que recibe GPT
Log::channel('daily')->info("🧠 [AIQuery FULL PROMPT] Consulta enviada a GPT", [
    'query_user' => $userMessage,
    'context' => $context,
    'selected_tables' => $selectedTables,
    'prompt_full_text' => $prompt, // 🔥 Prompt íntegro que se envía
]);


        // 5️⃣ Llamada a OpenAI
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

        if (!preg_match('/^select/i', $sql)) {
            return response()->json(['error' => 'Consulta no segura: solo se permite SELECT.'], 400);
        }

        // 6️⃣ Ejecutar SQL
        try {
            $rows = DB::select($sql);
        } catch (\Throwable $e) {
            Log::error('💥 Error ejecutando SQL IA', ['sql' => $sql, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al ejecutar SQL.', 'details' => $e->getMessage()], 500);
        }

        // 7️⃣ Generar resumen IA
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

        // 8️⃣ Guardar cache
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

        return response()->json([
            'sql' => $sql,
            'summary' => $summary,
            'rows' => $rows,
            'cached' => false,
        ]);
    }

    // ==========================================================
    // 🔍 Esquema enriquecido con relaciones reales y manuales
    // ==========================================================
    private function getDatabaseSchema(array $selectedTables = [], ?string $context = null): array
    {
        $contextTables = match ($context) {
            'laboral'   => ['job_offers', 'languages', 'technologies', 'language_metrics', 'technology_metrics'],
            'academico' => ['careers', 'courses', 'languages', 'course_language', 'career_course', 'language_metrics'],
            'global'    => ['stackoverflow_surveys', 'worldbank_indicators', 'technology_trend_enricheds'],
            default     => [],
        };

        $tables = !empty($selectedTables) ? $selectedTables : $contextTables;

        if (empty($tables)) {
            $tables = collect(DB::select('SHOW TABLES'))
                ->map(fn($t) => array_values((array)$t)[0])
                ->toArray();
        }

        // 🧹 Ignorar tablas internas de entrenamiento
        $ignoredTables = ['ai_trainings', 'sql_trainings', 'chat_histories', 'report_queries', 'ai_queries'];
        $tables = array_filter($tables, fn($t) => !in_array($t, $ignoredTables));

        $schema = [];

        foreach ($tables as $table) {
            $columns = DB::select("SHOW COLUMNS FROM {$table}");
            $foreignKeys = DB::select("
                SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = '{$table}'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            $relations = [];
            foreach ($foreignKeys as $fk) {
                $relations[$fk->REFERENCED_TABLE_NAME] =
                    "{$table}.{$fk->COLUMN_NAME} = {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}";
            }

            $schema[$table] = [
                'columns' => collect($columns)->pluck('Field'),
                'relations' => $relations,
            ];
        }

        // 🔧 Relaciones manuales adicionales (para guiar a GPT)
        $manualRelations = [
            'career_course' => [
                'careers' => 'career_course.career_id = careers.id',
                'courses' => 'career_course.course_id = courses.id',
            ],
            'course_language' => [
                'courses' => 'course_language.course_id = courses.id',
                'languages' => 'course_language.language_id = languages.id',
            ],
            'language_metrics' => [
                'languages' => 'language_metrics.language_id = languages.id',
            ],
        ];

        foreach ($manualRelations as $table => $rels) {
            if (!isset($schema[$table])) {
                $schema[$table] = ['columns' => [], 'relations' => []];
            }
            $schema[$table]['relations'] = array_merge($schema[$table]['relations'], $rels);
        }

        // 🧾 Log opcional para revisar
        Log::channel('daily')->info('🧩 [VERA] Relaciones detectadas', [
            'total_tables' => count($schema),
            'tables' => array_keys($schema),
            'relations' => collect($schema)->map(fn($meta, $t) => $meta['relations']),
        ]);

        return ['tables' => $schema];
    }
}
