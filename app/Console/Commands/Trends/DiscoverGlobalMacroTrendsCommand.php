<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscoverGlobalMacroTrendsCommand extends Command
{
    protected $signature = 'trends:discover-global
                            {--year=}
                            {--quarter=}
                            {--limit=30}';

    protected $description = 'Descubre macro tendencias usando Tavily + GPT';

    public function handle()
    {
        $year    = $this->option('year') ?? now()->year;
        $quarter = $this->option('quarter') ?? now()->quarter;
        $limit   = (int) $this->option('limit');

        $this->info("🌍 Buscando artículos de tendencias {$year}");

        try {

            $articles = $this->searchArticles($year, $limit);

            if (empty($articles)) {
                $this->warn("No se encontraron artículos");
                return Command::SUCCESS;
            }

            $this->info("Artículos encontrados: ".count($articles));

            $trends = $this->extractTrends($articles);

            if (!$trends) {
                $this->warn("GPT no devolvió tendencias");
                return Command::SUCCESS;
            }

            foreach ($trends as $trend) {

                $url = $trend['source']['url'] ?? null;

                if (!$url) {
                    continue;
                }

                if (!$this->validateUrl($url)) {
                    continue;
                }

                $exists = DB::table('macro_trend_raw')
                    ->where('source_url', $url)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('macro_trend_raw')->insert([
                    'trend_name'   => $trend['name'],
                    'description'  => $trend['description'] ?? null,
                    'year'         => $year,
                    'quarter'      => $quarter,
                    'source_name'  => $trend['source']['name'] ?? null,
                    'source_title' => $trend['source']['title'] ?? null,
                    'source_url'   => $url,
                    'source_type'  => $trend['source']['type'] ?? 'article',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $this->info("✅ Tendencias guardadas");

        } catch (\Throwable $e) {

            $this->error("Error: ".$e->getMessage());

            Log::error('[GLOBAL-TRENDS]', [
                'error' => $e->getMessage()
            ]);
        }

        return Command::SUCCESS;
    }

    /* =====================================================
       BUSCAR ARTÍCULOS CON TAVILY
    ===================================================== */

    protected function searchArticles(int $year, int $limit): array
    {
        $queries = [
            "technology trends {$year}",
            "AI trends {$year}",
            "future of work technology {$year}",
            "cybersecurity trends report {$year}",
            "digital transformation trends {$year}"
        ];

        $results = [];

        foreach ($queries as $query) {

            $response = Http::post(
                'https://api.tavily.com/search',
                [
                    'api_key' => env('TAVILY_KEY'),
                    'query' => $query,
                    'search_depth' => 'basic',
                    'max_results' => $limit
                ]
            );

            $data = $response->json();

            foreach ($data['results'] ?? [] as $item) {

                if (!isset($item['url'])) {
                    continue;
                }

                $results[] = [
                    'title' => $item['title'] ?? '',
                    'url'   => $item['url']
                ];
            }
        }

        return $results;
    }

    /* =====================================================
       EXTRAER TENDENCIAS CON GPT
    ===================================================== */

    protected function extractTrends(array $articles): ?array
    {
        $apiKey = config('services.openai.key');

        $prompt = $this->buildPrompt($articles);

        $response = Http::withToken($apiKey)
            ->timeout(200)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a global technology intelligence analyst.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        $text = data_get($response->json(), 'choices.0.message.content');

        return $this->extractJson($text)['trends'] ?? null;
    }

    /* =====================================================
       VALIDAR URL
    ===================================================== */

    protected function validateUrl(string $url): bool
    {
        try {

            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0'
                ])
                ->get($url);

            return $response->status() >= 200 && $response->status() < 400;

        } catch (\Throwable $e) {
            return false;
        }
    }

    /* =====================================================
       PROMPT GPT
    ===================================================== */

 protected function buildPrompt(array $articles): string
{
    $list = collect($articles)
        ->take(40) // más contexto mejora resultados
        ->map(fn($a) => "- {$a['title']} ({$a['url']})")
        ->implode("\n");

    return <<<PROMPT
You are a global technology intelligence analyst.

Below is a list of real articles about technology, digital transformation, AI, and future trends.

ARTICLES:
{$list}

TASK
Identify 6–12 MACRO technology trends shaping global digital transformation.

REQUIREMENTS
- Trend names MUST be written in Spanish.
- Descriptions MUST be written in Spanish.
- Use short strategic descriptions (max 3 sentences).
- Use the article list as evidence.
- Do NOT invent sources.
- Keep the original article title and URL exactly as provided.
- Prefer trends appearing across multiple sources.

OUTPUT
Return STRICT JSON only.

{
 "trends":[
  {
   "name":"Nombre de la tendencia en español",
   "description":"Explicación estratégica breve en español",
   "source":{
      "name":"Website or institution",
      "title":"Original article title",
      "url":"https://...",
      "type":"article | report | study"
   }
  }
 ]
}

Do NOT include any text outside the JSON.
PROMPT;
}

    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }

        return [];
    }
}
