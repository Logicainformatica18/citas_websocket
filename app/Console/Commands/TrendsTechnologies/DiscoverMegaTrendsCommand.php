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

    /* =========================================================
     * Helpers
     * ========================================================= */

    private function extractJson($text)
    {
        $s = strpos($text, '{');
        $e = strrpos($text, '}');

        return ($s !== false && $e !== false)
            ? substr($text, $s, $e - $s + 1)
            : null;
    }

    private function searchBlock($query, $label)
    {
        $prompt = "
You MUST perform a REAL WEB SEARCH for:

{$query}

Goal:
Generate REAL technological trends validated by REAL URLs.

Return ONLY this JSON:

{
  \"label\": \"{$label}\",
  \"tendencias\": [
    {
      \"topic_name\": \"...\",
      \"trend_score\": 20-100,
      \"regions\": [\"Global\", \"North America\", \"Europe\", \"Asia\"],
      \"job_search_keywords\": [
        \"keyword used in job postings\",
        \"alternative keyword used in job postings\",
        \"industry-standard term used in vacancies\"
      ],
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
- Return a MINIMUM of 10 trends.
- All URLs MUST be real and verifiable.
- If fewer than 10 trends exist, expand the scope naturally BUT only with real existing content.
- No invented URLs, no fake studies.
- Output MUST be JSON ONLY.
";

        try {
            // 🔁 Retry + backoff (CRÍTICO para GPT-5 Search)
            $res = retry(3, function () use ($prompt, $label) {
                return Http::withToken(env('OPENAI_API_KEY'))
                    ->timeout(120)
                    ->post("https://api.openai.com/v1/chat/completions", [
                        "model" => "gpt-5-search-api",
                        "messages" => [
                            ["role" => "system", "content" => "Return ONLY JSON. Always valid JSON."],
                            ["role" => "user", "content" => $prompt]
                        ]
                    ]);
            }, 15); // ⏱️ espera 15s entre reintentos

            if ($res->failed()) {
                Log::error("OpenAI HTTP Error", [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                    'label'  => $label
                ]);
                return null;
            }

            $jsonResponse = $res->json();

            if (!isset($jsonResponse['choices'][0]['message']['content'])) {
                Log::warning("GPT-5 Search returned empty content", [
                    'label' => $label,
                    'response' => $jsonResponse
                ]);
                return null;
            }

            $raw = $jsonResponse['choices'][0]['message']['content'];
            $json = $this->extractJson($raw);

            if (!$json) {
                Log::warning("Invalid JSON structure returned", [
                    'label' => $label,
                    'raw' => $raw
                ]);
                return null;
            }

            return json_decode($json, true);

        } catch (\Throwable $e) {
            Log::error("Search Block Exception", [
                'query' => $query,
                'label' => $label,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /* =========================================================
     * Main
     * ========================================================= */

    public function handle()
    {
        $this->info("\n🚀 INICIANDO OBSERVATORIO: MEGA-TENDENCIAS GLOBALES…");

        $topics = TrendTopic::where('active', 1)->get();

        if ($limit = $this->option('limit')) {
            $topics = $topics->take($limit);
        }

        $totalTrends = 0;
        $year = date('Y');
        $quarter = ceil(date('n') / 3);

        foreach ($topics as $topic) {

            // ⏱️ THROTTLING OBLIGATORIO
            $this->info("⏳ Esperando antes de ejecutar búsqueda…");
            sleep(7);

            $label = $topic->topic_name;
            $query = $topic->search_query;

            $this->info("\n🔍 Procesando topic: {$label}");

            $result = $this->searchBlock($query, $label);

            /* ======================================================
             * Manejo de fallo (NO desactivar topic)
             * ====================================================== */
            if (
                !$result ||
                !isset($result['tendencias']) ||
                count($result['tendencias']) === 0
            ) {
                $this->warn("⛔ No se generaron tendencias para: {$label}");

                $topic->fail_count++;
                $topic->last_fail_at = now();
                $topic->save();

                continue;
            }

            /* ======================================================
             * Registrar éxito
             * ====================================================== */
            $topic->success_count++;
            $topic->last_success_at = now();
            $topic->fail_count = 0;
            $topic->auto_disabled_reason = null;
            $topic->save();

            /* ======================================================
             * Guardar fuentes usadas
             * ====================================================== */
            foreach ($result['used_sources'] ?? [] as $src) {
                if (empty($src['url'])) continue;

                ScrapingSource::firstOrCreate(
                    ['url' => $src['url']],
                    [
                        'name' => $src['title'] ?? 'Fuente registrada',
                        'web_only' => 1,
                        'notes' => "Fuente usada para topic: {$label}"
                    ]
                );
            }

            /* ======================================================
             * Guardar tendencias
             * ====================================================== */
            foreach ($result['tendencias'] as $t) {

                $source = null;

                if (!empty($t['source_links'][0]['url'])) {
                    $source = ScrapingSource::firstOrCreate(
                        ['url' => $t['source_links'][0]['url']],
                        [
                            'name' => $t['source_links'][0]['title'] ?? 'Fuente tendencia',
                            'web_only' => 1,
                            'notes' => "Fuente principal para topic: {$label}"
                        ]
                    );
                }

               TechnologyTrend::updateOrCreate(
    [
        'topic_name' => $t['topic_name'],
        'year'       => $year,
        'quarter'    => $quarter,
        'source_id'  => $source->id ?? 1
    ],
    [
        'topic_category'   => $label,
        'trend_score'      => $t['trend_score'],
        'regions'          => json_encode($t['regions']),
        'scanned_keywords' => json_encode($t['job_search_keywords'] ?? []),
        'source_url'       => $t['source_links'][0]['url'] ?? null,
        'source_title'     => $t['source_links'][0]['title'] ?? null,
        'raw_data'         => json_encode($t)
    ]
);


                $totalTrends++;
            }

            $this->info("✅ Tendencias procesadas para topic: {$label}");
        }

        $this->info("\n🌍 TOTAL DE TENDENCIAS REGISTRADAS/ACTUALIZADAS: {$totalTrends}");
        $this->info("🏁 Observatorio completado correctamente.\n");

        return Command::SUCCESS;
    }
}
