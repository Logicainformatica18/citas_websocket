<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\EntityTrend;

class DiscoverTrendsFromMarketEntitiesCommand extends Command
{
    protected $signature = 'trends:discover-from-market
                            {--entity=certification}
                            {--sleep=10}
                            {--limit=50}';

    protected $description = 'Descubre tendencias por entidad y las guarda en entity_trends (reiniciable)';

    /* =========================================================
       ENTIDADES A PROCESAR
    ========================================================= */
protected function getEntities(): array
{
    return DB::table('market_entities as me')
        ->leftJoin('entity_trends as et', function ($join) {
            $join->on('et.market_entity_id', '=', 'me.id');
        })
        ->where('me.entity_type', 'certification')
        ->groupBy('me.id', 'me.name', 'me.entity_type')
        ->orderByRaw('MAX(et.created_at) IS NOT NULL') // NULL primero
        ->orderByRaw('MAX(et.created_at) ASC')         // más antiguo primero
        ->select([
            'me.id',
            'me.name',
            'me.entity_type',
            DB::raw('MAX(et.created_at) as last_run_at'),
        ])
        ->get()
        ->map(fn ($e) => [
            'id'   => $e->id,
            'type' => $e->entity_type,
            'name' => $e->name,
            'last_run_at' => $e->last_run_at, // solo informativo
        ])
        ->toArray();
}



    /* =========================================================
       HANDLE
    ========================================================= */
public function handle()
{
    $year    = now()->year;
    $quarter = now()->quarter;

    $limit = (int) $this->option('limit');
    $sleep = (int) $this->option('sleep');

    $this->info("🔍 Ejecutando GPT-Search para tendencias (Y{$year} Q{$quarter})");

    $entities = array_slice($this->getEntities(), 0, $limit);

    if (empty($entities)) {
        $this->warn('⚠️ No hay entidades para procesar');
        return Command::SUCCESS;
    }

    foreach ($entities as $entity) {

        try {

            /* ===============================
               1. PROMPT
            =============================== */
            $prompt = $this->buildPrompt(
                entityType: $entity['type'],
                entityName: $entity['name']
            );

            /* ===============================
               2. GPT SEARCH
            =============================== */
            $response = $this->gptSearch($prompt);

            if (is_string($response)) {
                $response = $this->extractJson($response);
            }

            if (
                !is_array($response) ||
                !isset($response['trends']) ||
                !is_array($response['trends'])
            ) {
                throw new \Exception('Respuesta GPT inválida o sin trends');
            }

            /* ===============================
               3. INSERT LIMPIO (SEGÚN TU TABLA)
            =============================== */
            foreach ($response['trends'] as $trend) {

                if (empty($trend['name']) || !isset($trend['score'])) {
                    continue;
                }

               EntityTrend::firstOrCreate(
    [
        'market_entity_id' => $entity['id'],
        'year'             => $year,
        'quarter'          => $quarter,
        'trend_name'       => trim($trend['name']),
    ],
    [
        'trend_score'      => (float) $trend['score'],

        'source_title'     => $trend['source']['title'] ?? null,
        'source_url'       => $trend['source']['url']   ?? null,
        'source_type'      => $trend['source']['type']  ?? null,

        'match_type'       => 'explicit',
        'confidence_score' => 0.90,

        'discovered_by'    => 'gpt-search',
        'discovered_at'    => now(),
    ]
);

            }

            $this->info("✅ Tendencias guardadas para {$entity['name']}");
            sleep($sleep);

        } catch (\Throwable $e) {

            $this->error("❌ Error con {$entity['name']}");
            $this->line("👉 {$e->getMessage()}");

            Log::error('[GPT-TRENDS ERROR]', [
                'entity'  => $entity,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    $this->info('🏁 Proceso finalizado');
    return Command::SUCCESS;
}

 


    /* =========================================================
       EXTRAER JSON (por si GPT devuelve texto)
    ========================================================= */
    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return json_decode($matches[0], true) ?? [];
        }

        return [];
    }

    /* =========================================================
       PROMPT GPT
    ========================================================= */
    protected function buildPrompt(string $entityType, string $entityName): string
    {
        return <<<PROMPT
You are a labor market and technology trends analyst.

Analyze current global trends related to the following entity:

Entity type: {$entityType}
Entity name: {$entityName}

Your task:
- Identify 3 to 5 current and relevant trends
- Focus on labor demand, adoption, salaries, remote work, certifications value, or enterprise usage
- Each trend must be backed by a real and verifiable online source

Return the result strictly in JSON format with this structure:

{
  "trends": [
    {
      "name": "Short descriptive trend title",
      "score": 0-100,
      "source": {
        "title": "Source article or report title",
        "url": "https://example.com",
        "type": "report | article | study | blog"
      }
    }
  ]
}

Rules:
- Do NOT invent sources
- Use recent information (last 12–24 months)
- Scores must reflect relevance and strength of the trend
- Do NOT include explanations outside JSON
PROMPT;
    }
    protected function gptSearch(string $prompt): array|string
{
    /**
     * Ajusta esto según cómo llamas hoy a GPT-Search
     * Aquí va una versión genérica y segura
     */

    $apiKey = config('services.openai.key');

    if (!$apiKey) {
        throw new \Exception('OPENAI_API_KEY no configurada');
    }

    $response = Http::withToken($apiKey)
        ->timeout(60)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4.1-mini', // o el que estés usando para search
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert labor market analyst.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

    if (!$response->successful()) {
        throw new \Exception('Error HTTP GPT: ' . $response->body());
    }

    $content = data_get($response->json(), 'choices.0.message.content');

    if (!$content) {
        throw new \Exception('Respuesta GPT vacía');
    }

    return $content;
}

}
