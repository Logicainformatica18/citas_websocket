<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ScrapingSource;
use App\Models\TechnologyTrend;
use App\Models\TrendTopic;

class DiscoverMegaTrendsCommand extends Command
{
    protected $signature = 'ai:mega-trends {--limit=0}';
    protected $description = 'Genera megatendencias tecnológicas usando GPT-5 Search basadas en los topics de trend_topics.';

    private function extractJson($text)
    {
        $s = strpos($text, '{');
        $e = strrpos($text, '}');
        return ($s !== false && $e !== false) ? substr($text, $s, $e - $s + 1) : null;
    }

    private function searchBlock($query, $blockLabel)
    {
        $prompt = "
You MUST perform a REAL WEB SEARCH for:

    {$query}

Goal:
Generate REAL technological trends validated by REAL URLs.

Return ONLY this JSON:

{
  \"label\": \"{$blockLabel}\",
  \"tendencias\": [
    {
      \"topic_name\": \"...\",
      \"trend_score\": 20-100,
      \"regions\": [\"Global\", \"North America\", \"Europe\", \"Asia\"],
      \"source_links\": [
        {\"title\": \"Real article\", \"url\": \"https://...\"}
      ]
    }
  ],
  \"used_sources\": [
    {\"title\": \"Source title\", \"url\": \"https://...\"}
  ]
}

RULES:
- Minimum 6 trends for this block.
- All URLs MUST be real.
- No invented technology names.
- JSON ONLY.
";

        try {
            $res = Http::withToken(env('OPENAI_API_KEY'))
                ->post("https://api.openai.com/v1/chat/completions", [
                    "model" => "gpt-5-search-api",
                    "messages" => [
                        ["role" => "system", "content" => "Use real Web Search and return ONLY JSON."],
                        ["role" => "user", "content" => $prompt]
                    ]
                ]);

            $raw = $res->json()['choices'][0]['message']['content'] ?? null;
            if (!$raw) return null;

            $json = $this->extractJson($raw);
            return $json ? json_decode($json, true) : null;

        } catch (\Exception $e) {
            Log::error("Search Block Error", [
                'query' => $query,
                'label' => $blockLabel,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function handle()
    {
        $this->info("\n🚀 INICIANDO OBSERVATORIO: MEGA-TENDENCIAS GLOBALES…");

        $topics = TrendTopic::where('active', 1)->get();

        if ($this->option('limit') > 0) {
            $topics = $topics->take($this->option('limit'));
        }

        $totalTrends = 0;

        foreach ($topics as $topic) {

            $query = $topic->search_query;
            $label = $topic->topic_name;

            $this->info("\n🔍 Procesando topic: {$label}");

            $result = $this->searchBlock($query, $label);

            if (!$result || !isset($result['tendencias'])) {
                $this->warn("⛔ No se generaron tendencias para {$label}");
                continue;
            }

            /* ==========================================================
             * GUARDAR FUENTES UTILIZADAS
             * ========================================================== */
            foreach ($result['used_sources'] ?? [] as $src) {

                if (!isset($src['url'])) continue;

                ScrapingSource::firstOrCreate(
                    ['url' => $src['url']],
                    [
                        'name' => $src['title'] ?? 'Fuente registrada',
                        'web_only' => 1,
                        'notes' => "Fuente usada para topic: {$label}"
                    ]
                );
            }

            /* ==========================================================
             * GUARDAR TENDENCIAS GENERADAS
             * ========================================================== */
            foreach ($result['tendencias'] as $t) {

                $source = null;

                if (!empty($t['source_links'][0]['url'])) {

                    $source = ScrapingSource::firstOrCreate(
                        ['url' => $t['source_links'][0]['url']],
                        [
                            'name' => $t['source_links'][0]['title'] ?? 'Fuente tendencia',
                            'web_only' => 1,
                            'notes' => "Fuente primaria para topic: {$label}"
                        ]
                    );
                }

                TechnologyTrend::create([
                    'topic_name'     => $t['topic_name'],
                    'topic_category' => $label,
                    'trend_score'    => $t['trend_score'],
                    'regions'        => json_encode($t['regions']),
                    'source_id'      => $source?->id ?? 1,
                    'source_url'     => $t['source_links'][0]['url'] ?? null,
                    'source_title'   => $t['source_links'][0]['title'] ?? null,
                    'raw_data'       => json_encode($t),
                    'year'           => date('Y'),
                    'quarter'        => ceil(date('n') / 3)
                ]);

                $totalTrends++;
            }

            $this->info("✅ Tendencias generadas para topic: {$label}");
        }

        $this->info("\n🌍 TOTAL DE TENDENCIAS GENERADAS: {$totalTrends}");
        $this->info("🏁 Observatorio completado correctamente.");

        return Command::SUCCESS;
    }
}
