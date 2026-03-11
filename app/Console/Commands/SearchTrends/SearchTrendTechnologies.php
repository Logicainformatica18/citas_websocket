<?php

namespace App\Console\Commands\SearchTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\EntityTrend;

class SearchTrendTechnologies extends Command
{
    protected $signature = 'searchtrend:discover-technologies
                            {--limit=20}
                            {--sleep=10}';

    protected $description = 'Descubre tendencias de mercado SOLO para tecnologías usando Search + GPT';

    public function handle()
    {
        $year    = now()->year;
        $quarter = now()->quarter;

        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');

        $this->info("🔍 Descubriendo tendencias de TECNOLOGÍAS – Y{$year} Q{$quarter}");

        $technologies = $this->getTechnologies($limit);

        if (empty($technologies)) {
            $this->warn('🟢 No hay tecnologías pendientes');
            return Command::SUCCESS;
        }

        foreach ($technologies as $technology) {

            try {

                /* ===============================
                   0️⃣ BUSCAR FUENTES
                =============================== */
                $sources = $this->searchSources($technology['name']);
$this->line("📚 Sources encontradas: " . count($sources));
                /* ===============================
                   1️⃣ PROMPT
                =============================== */
                $prompt = $this->buildPrompt($technology['name'], $sources);

                /* ===============================
                   2️⃣ GPT
                =============================== */
                $response = $this->gptSearch($prompt);
                $this->line("🤖 GPT raw response:");
$this->line($response);

                if (is_string($response)) {
                    $response = $this->extractJson($response);
                }

                if (!is_array($response) || !isset($response['trends'])) {
                    throw new \Exception('Respuesta GPT inválida');
                }

                foreach ($response['trends'] as $trend) {

                    if (empty($trend['name']) || !isset($trend['score'])) {
                        continue;
                    }

                    $url = trim($trend['source']['url'] ?? '');
                    $url = strtok($url, '?');
                    $url = rtrim($url, '/');

                    $title = trim($trend['source']['title'] ?? '');

                    if (!$url) {
                        continue;
                    }

                    $exists = EntityTrend::where('market_entity_id', $technology['id'])
                        ->where('year', $year)
                        ->where('quarter', $quarter)
                        ->where(function ($q) use ($url, $title) {

                            if ($url) {
                                $q->where('source_url', $url);
                            }

                            if ($title) {
                                $q->orWhereRaw(
                                    'LOWER(TRIM(source_title)) = ?',
                                    [strtolower($title)]
                                );
                            }

                        })
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    EntityTrend::create([
                        'market_entity_id' => $technology['id'],
                        'year'             => $year,
                        'quarter'          => $quarter,
                        'trend_name'       => trim($trend['name']),
                        'trend_score'      => (float) $trend['score'],
                        'source_title'     => $trend['source']['title'] ?? null,
                        'source_url'       => $url,
                        'source_type'      => $trend['source']['type'] ?? null,
                        'match_type'       => 'explicit',
                        'confidence_score' => 0.90,
                        'discovered_by'    => 'search-gpt',
                        'discovered_at'    => now(),
                    ]);
                }

                $this->info("✅ {$technology['name']} procesado");

                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error("❌ Error en {$technology['name']}");
                $this->line($e->getMessage());

                Log::error('[TECHNOLOGY-TRENDS]', [
                    'technology' => $technology,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Proceso finalizado');
        return Command::SUCCESS;
    }

    /* =========================================================
       OBTENER TECNOLOGÍAS
    ========================================================= */

    protected function getTechnologies(int $limit): array
    {
        return DB::table('market_entities as me')
            ->leftJoin('entity_trends as et', function ($j) {
                $j->on('et.market_entity_id', '=', 'me.id');
            })
            ->where('me.entity_type', 'technology')
            ->groupBy('me.id', 'me.name')
            ->orderByRaw('MAX(et.created_at) IS NOT NULL')
            ->orderByRaw('MAX(et.created_at) ASC')
            ->limit($limit)
            ->select('me.id', 'me.name')
            ->get()
            ->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])
            ->toArray();
    }

    /* =========================================================
       DUCKDUCKGO SEARCH
    ========================================================= */

protected function searchSources(string $technology): array
{
    $apiKey = '251ea4c0f70ea21c74db6bcce1bfb1a4ecc2ee2e'; // guarda tu key en config

    $query = $technology . " technology trends";

    $this->line("🔎 Serper query: {$query}");

    try {

        $response = Http::withHeaders([
            'X-API-KEY' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://google.serper.dev/search', [
            'q' => $query,
            'num' => 5
        ]);

        if (!$response->successful()) {
            $this->error("Serper error: " . $response->body());
            return [];
        }

        $results = collect($response->json('organic'))
            ->take(5)
            ->map(function ($r) {

                return [
                    'title' => $r['title'] ?? '',
                    'url'   => $r['link'] ?? '',
                ];

            })
            ->toArray();

        foreach ($results as $r) {
            $this->line("• {$r['title']}");
        }

        return $results;

    } catch (\Throwable $e) {

        $this->error("Serper failed: " . $e->getMessage());

        return [];
    }
}

    protected function extractRealUrl($url)
    {
        if (str_contains($url, 'uddg=')) {

            parse_str(parse_url($url, PHP_URL_QUERY), $query);

            if (isset($query['uddg'])) {
                return urldecode($query['uddg']);
            }
        }

        return $url;
    }

    /* =========================================================
       PROMPT GPT
    ========================================================= */

    protected function buildPrompt(string $technology, array $sources): string
{
    $sourceText = '';

    foreach ($sources as $s) {
        $sourceText .= "- {$s['title']} | {$s['url']}\n";
    }

    return <<<PROMPT
You are a global enterprise technology market analyst.

Analyze current market trends related to the technology "{$technology}".

You MUST ONLY use the sources listed below.

STRICT RULES:
- Do NOT invent sources.
- Do NOT modify URLs.
- Only use the sources listed below.
- If the sources are insufficient, return fewer trends.

Available sources:

{$sourceText}

Return 3-5 trends supported ONLY by the sources above.

Return STRICT JSON:

{
  "trends": [
    {
      "name": "trend title",
      "score": 0-100,
      "source": {
        "title": "title FROM the list above",
        "url": "url FROM the list above",
        "type": "report | article | study | blog"
      }
    }
  ]
}

Do not include any text outside JSON.
PROMPT;
}

    /* =========================================================
       GPT REQUEST
    ========================================================= */

    protected function gptSearch(string $prompt): string
    {
        $apiKey = config('services.openai.key');

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-mini',
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert technology market analyst.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

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
