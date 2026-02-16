<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\EntityTrend;

class DiscoverMethodologyTrendsCommand extends Command
{
    protected $signature = 'trends:discover-methodologies
                            {--limit=20}
                            {--sleep=10}';

    protected $description = 'Descubre tendencias de mercado SOLO para metodologías (market_entities)';

    public function handle()
    {
        $year    = now()->year;
        $quarter = now()->quarter;

        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');

        $this->info("🔍 Descubriendo tendencias de METODOLOGÍAS – Y{$year} Q{$quarter}");

        $methodologies = $this->getMethodologies($limit);

        if (empty($methodologies)) {
            $this->warn('🟢 No hay metodologías pendientes');
            return Command::SUCCESS;
        }

        foreach ($methodologies as $methodology) {

            try {

                $prompt   = $this->buildPrompt($methodology['name']);
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

                foreach ($response['trends'] as $trend) {

                    if (empty($trend['name']) || !isset($trend['score'])) {
                        continue;
                    }

                    $url = trim($trend['source']['url'] ?? '');
                    $url = strtok($url, '?');
                    $url = rtrim($url, '/');

                    if (!$url) {
                        continue;
                    }

                    $exists = EntityTrend::where('market_entity_id', $methodology['id'])
                        ->where('year', $year)
                        ->where('quarter', $quarter)
                        ->where('source_url', $url)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    EntityTrend::create([
                        'market_entity_id' => $methodology['id'],
                        'year'             => $year,
                        'quarter'          => $quarter,
                        'trend_name'       => trim($trend['name']),
                        'trend_score'      => (float) $trend['score'],
                        'source_title'     => $trend['source']['title'] ?? null,
                        'source_url'       => $url,
                        'source_type'      => $trend['source']['type'] ?? null,
                        'match_type'       => 'explicit',
                        'confidence_score' => 0.90,
                        'discovered_by'    => 'gpt-search',
                        'discovered_at'    => now(),
                    ]);
                }

                $this->info("✅ {$methodology['name']} procesado");
                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error("❌ Error en {$methodology['name']}");
                $this->line("👉 {$e->getMessage()}");

                Log::error('[METHODOLOGY-TRENDS]', [
                    'methodology' => $methodology,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Proceso finalizado');
        return Command::SUCCESS;
    }

    /* =========================================================
       OBTENER METODOLOGÍAS DESDE market_entities
    ========================================================= */
    protected function getMethodologies(int $limit): array
    {
        return DB::table('market_entities as me')
            ->leftJoin('entity_trends as et', function ($j) {
                $j->on('et.market_entity_id', '=', 'me.id');
            })
            ->where('me.entity_type', 'methodology')
            ->groupBy('me.id', 'me.name')
            ->orderByRaw('MAX(et.created_at) IS NOT NULL')
            ->orderByRaw('MAX(et.created_at) ASC')
            ->limit($limit)
            ->select(
                'me.id',
                'me.name',
                DB::raw('MAX(et.created_at) as last_trend_at')
            )
            ->get()
            ->map(fn ($m) => [
                'id'   => $m->id,
                'name' => $m->name,
            ])
            ->toArray();
    }

    /* =========================================================
       PROMPT GPT – METODOLOGÍAS
    ========================================================= */
    protected function buildPrompt(string $methodology): string
    {
        $currentYear  = now()->year;
        $previousYear = $currentYear - 1;

        return <<<PROMPT
You are a global enterprise methodology and digital transformation analyst.

Analyze current market trends related to the methodology "{$methodology}".

Focus on:
- Enterprise adoption
- Digital transformation impact
- Integration with cloud, AI, DevOps
- Workforce demand
- Industry-wide usage

Return 3 to 5 relevant trends.

STRICT REQUIREMENTS:
- Only use sources published in {$previousYear} or {$currentYear}.
- Exclude unverifiable or outdated sources.
- Do NOT invent sources.

Return STRICT JSON only:

{
  "trends": [
    {
      "name": "Short descriptive trend title",
      "score": 0-100,
      "source": {
        "title": "Source title",
        "url": "https://example.com",
        "type": "report | article | study | blog"
      }
    }
  ]
}
PROMPT;
    }

    protected function gptSearch(string $prompt): string
    {
        $apiKey = config('services.openai.key');

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

        return data_get($response->json(), 'choices.0.message.content');
    }

    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }
        return [];
    }
}
