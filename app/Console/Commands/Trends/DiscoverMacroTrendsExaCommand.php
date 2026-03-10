<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscoverMacroTrendsExaCommand extends Command
{
    protected $signature = 'trends:discover-exa
                            {--year=}
                            {--quarter=}
                            {--limit=40}';

    protected $description = 'Discover macro technology trends using Exa search + GPT analysis';

    public function handle()
    {
        $year = $this->option('year') ?? now()->year;
        $quarter = $this->option('quarter') ?? now()->quarter;
        $limit = (int) $this->option('limit');

        $this->info("🔎 Searching trend articles with Exa ({$year})");

        try {

            $articles = $this->searchArticles($year, $limit);

            if (!$articles) {
                $this->warn("No articles found");
                return Command::SUCCESS;
            }

            $this->info("Articles found: ".count($articles));

            $trends = $this->analyzeWithGPT($articles);

            if (!$trends) {
                $this->warn("GPT returned no trends");
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

            $this->info("✅ Trends stored successfully");

        } catch (\Throwable $e) {

            $this->error("Error: ".$e->getMessage());

            Log::error('[TREND-DISCOVERY]', [
                'error' => $e->getMessage()
            ]);
        }

        return Command::SUCCESS;
    }

    /* ==============================================
       SEARCH ARTICLES WITH EXA
    ============================================== */

    protected function searchArticles(int $year, int $limit): array
    {
        $queries = [
            "technology trends {$year}",
            "AI trends {$year}",
            "future of work technology trends {$year}",
            "cybersecurity trends {$year}",
            "digital transformation trends {$year}"
        ];

        $results = [];

        foreach ($queries as $query) {

            $response = Http::withHeaders([
                'x-api-key' => '6124bb4b-8b1f-4227-83f8-4244255c36c7',
                'Content-Type' => 'application/json'
            ])->post('https://api.exa.ai/search', [
                "query" => $query,
                "type" => "auto",
                "num_results" => $limit,
                "contents" => [
                    "highlights" => [
                        "max_characters" => 2000
                    ]
                ]
            ]);

            $data = $response->json();

            foreach ($data['results'] ?? [] as $item) {

                if (!isset($item['url'])) {
                    continue;
                }

                $results[] = [
                    'title' => $item['title'] ?? '',
                    'url' => $item['url']
                ];
            }
        }

        return $results;
    }

    /* ==============================================
       ANALYZE WITH GPT
    ============================================== */

    protected function analyzeWithGPT(array $articles): ?array
    {
        $prompt = $this->buildPrompt($articles);

        $response = Http::withToken(config('services.openai.key'))
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

    /* ==============================================
       VALIDATE URL
    ============================================== */

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

    /* ==============================================
       PROMPT
    ============================================== */

    protected function buildPrompt(array $articles): string
    {
        $list = collect($articles)
            ->take(40)
            ->map(fn($a) => "- {$a['title']} ({$a['url']})")
            ->implode("\n");

        return <<<PROMPT
You are analyzing articles about technology trends.

ARTICLES
{$list}

TASK
Identify 8–12 macro technology trends shaping global digital transformation.

IMPORTANT
- Trend names must be in Spanish
- Descriptions must be in Spanish
- Use the article list as evidence
- Do NOT invent sources
- Keep the original article title and URL

Return JSON only.

{
 "trends":[
  {
   "name":"Nombre de tendencia",
   "description":"Explicación breve",
   "source":{
      "name":"Website",
      "title":"Original article title",
      "url":"https://...",
      "type":"article"
   }
  }
 ]
}
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