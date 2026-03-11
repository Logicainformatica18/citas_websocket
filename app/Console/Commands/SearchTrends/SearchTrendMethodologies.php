<?php

namespace App\Console\Commands\SearchTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\EntityTrend;

class SearchTrendMethodologies extends Command
{
    protected $signature = 'searchtrend:discover-methodologies
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

                /* ===============================
                   0️⃣ BUSCAR FUENTES
                =============================== */
                $sources = $this->searchSources($methodology['name']);

                /* ===============================
                   1️⃣ PROMPT
                =============================== */
                $prompt = $this->buildPrompt($methodology['name'], $sources);

                /* ===============================
                   2️⃣ GPT
                =============================== */
                $response = $this->gptSearch($prompt);

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

                    $url   = trim($trend['source']['url'] ?? '');
                    $title = trim($trend['source']['title'] ?? '');

                    $exists = EntityTrend::where('market_entity_id', $methodology['id'])
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
                $this->line($e->getMessage());

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
       OBTENER METODOLOGÍAS
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
            ->select('me.id','me.name')
            ->get()
            ->map(fn ($m) => [
                'id'   => $m->id,
                'name' => $m->name,
            ])
            ->toArray();
    }

    /* =========================================================
       DUCKDUCKGO SEARCH
    ========================================================= */

    protected function searchSources(string $methodology): array
    {
        $query = urlencode($methodology . " software methodology trends 2024 2025");

        $url = "https://html.duckduckgo.com/html/?q={$query}";

        sleep(rand(2,4));

        $client = new Client([
            'timeout' => 10
        ]);

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X)',
            'Mozilla/5.0 (X11; Linux x86_64)'
        ];

        try {

            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => $userAgents[array_rand($userAgents)],
                    'Accept-Language' => 'en-US,en;q=0.9'
                ]
            ]);

            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);

            $results = $crawler->filter('.result')->each(function ($node) {

                $title = $node->filter('.result__a')->count()
                    ? $node->filter('.result__a')->text()
                    : '';

                $url = $node->filter('.result__a')->count()
                    ? $node->filter('.result__a')->attr('href')
                    : '';

                return [
                    'title' => trim($title),
                    'url'   => $this->extractRealUrl($url),
                ];
            });

            return array_slice($results, 0, 5);

        } catch (\Throwable $e) {

            Log::warning("DuckDuckGo search failed for {$methodology}");

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

    protected function buildPrompt(string $methodology, array $sources): string
    {
        $sourceText = '';

        foreach ($sources as $s) {
            $sourceText .= "- {$s['title']} ({$s['url']})\n";
        }

        return <<<PROMPT
You are a global digital transformation and agile practices analyst.

Analyze trends for the software methodology "{$methodology}".

Recent sources discovered via search:

{$sourceText}

Return 3-5 trends supported by real sources.

Return STRICT JSON:

{
  "trends": [
    {
      "name": "trend title",
      "score": 0-100,
      "source": {
        "title": "article title",
        "url": "https://...",
        "type": "report | article | study | blog"
      }
    }
  ]
}
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
                    ['role' => 'system', 'content' => 'You are a market analyst'],
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
