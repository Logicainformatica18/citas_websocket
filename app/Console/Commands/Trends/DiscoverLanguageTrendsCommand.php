<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\EntityTrend;

class DiscoverLanguageTrendsCommand extends Command
{
    protected $signature = 'trends:discover-languages
                            {--limit=20 : Cantidad máxima de lenguajes}
                            {--sleep=10 : Segundos entre requests GPT}';

    protected $description = 'Descubre tendencias de mercado SOLO para lenguajes (market_entities)';

    /* =========================================================
       HANDLE
    ========================================================= */
    public function handle()
    {
        $year    = now()->year;
        $quarter = now()->quarter;

        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');

        $this->info("🔍 Descubriendo tendencias de LENGUAJES – Y{$year} Q{$quarter}");

        $languages = $this->getLanguages($limit);

        if (empty($languages)) {
            $this->warn('🟢 No hay lenguajes pendientes');
            return Command::SUCCESS;
        }

        foreach ($languages as $language) {

            try {
                /* ===============================
                   1️⃣ PROMPT
                =============================== */
                $prompt = $this->buildPrompt($language['name']);

                /* ===============================
                   2️⃣ GPT SEARCH
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
                    throw new \Exception('Respuesta GPT inválida');
                }

                /* ===============================
                   3️⃣ INSERT LIMPIO
                =============================== */
                foreach ($response['trends'] as $trend) {

                    if (
                        empty($trend['name']) ||
                        !isset($trend['score'])
                    ) {
                        continue;
                    }

                  // ⛔ Si ya existe esta URL para este lenguaje, no hacemos nada
$exists = EntityTrend::where('market_entity_id', $language['id'])
    ->where('source_url', $trend['source']['url'] ?? null)
    ->exists();

if ($exists) {
    continue;
}

EntityTrend::create([
    'market_entity_id' => $language['id'],
    'year'             => $year,
    'quarter'          => $quarter,
    'trend_name'       => trim($trend['name']),
    'trend_score'      => (float) $trend['score'],
    'source_title'     => $trend['source']['title'] ?? null,
    'source_url'       => $trend['source']['url']   ?? null,
    'source_type'      => $trend['source']['type']  ?? null,
    'match_type'       => 'explicit',
    'confidence_score' => 0.90,
    'discovered_by'    => 'gpt-search',
    'discovered_at'    => now(),
]);

                }

                $this->info("✅ {$language['name']} procesado");
                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error("❌ Error en {$language['name']}");
                $this->line("👉 {$e->getMessage()}");

                Log::error('[LANGUAGE-TRENDS]', [
                    'language' => $language,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Proceso finalizado');
        return Command::SUCCESS;
    }

    /* =========================================================
       OBTENER LENGUAJES (PRIORIZADO)
    ========================================================= */
    protected function getLanguages(int $limit): array
    {
        return DB::table('market_entities as me')
            ->leftJoin('entity_trends as et', function ($j) {
                $j->on('et.market_entity_id', '=', 'me.id');
            })
            ->where('me.entity_type', 'language')
            ->groupBy('me.id', 'me.name')
            ->orderByRaw('MAX(et.created_at) IS NOT NULL') // primero sin trends
            ->orderByRaw('MAX(et.created_at) ASC')         // luego los más antiguos
            ->limit($limit)
            ->select(
                'me.id',
                'me.name',
                DB::raw('MAX(et.created_at) as last_trend_at')
            )
            ->get()
            ->map(fn ($l) => [
                'id'   => $l->id,
                'name' => $l->name,
            ])
            ->toArray();
    }

    /* =========================================================
       PROMPT GPT – LENGUAJES
    ========================================================= */
    protected function buildPrompt(string $language): string
    {
        return <<<PROMPT
You are a global labor market and software industry analyst.

Analyze current market trends related to the programming language "{$language}".

Focus on:
- Global developer demand
- Enterprise adoption
- Salary impact
- Remote work opportunities
- Ecosystem growth (frameworks, tooling)
- Industry usage

Return 3 to 5 relevant trends.

Each trend MUST be supported by a real and verifiable online source.

Return STRICTLY valid JSON in this format:

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
- Use information from the last 12–24 months
- Do NOT invent sources
- Scores must reflect relevance and strength
- Do NOT include any text outside JSON
PROMPT;
    }

    /* =========================================================
       GPT SEARCH
    ========================================================= */
    protected function gptSearch(string $prompt): string
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY no configurada');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-mini',
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert market analyst.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Error GPT: ' . $response->body());
        }

        return data_get($response->json(), 'choices.0.message.content');
    }

    /* =========================================================
       EXTRAER JSON
    ========================================================= */
    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }
        return [];
    }
}
