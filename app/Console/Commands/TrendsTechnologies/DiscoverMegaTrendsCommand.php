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
- If fewer than 10 trends exist for this topic, expand the scope naturally (temas relacionados o subtemas) PERO solo usando contenido que realmente exista.
- No invented URLs, no fake studies, no fabricated pages.
- You may use ANY real online source (news, blogs, research portals, corporate reports, developer communities, etc.) as long as the URLs exist.
- Output MUST be JSON ONLY.";


        try {
            $res = Http::withToken(env('OPENAI_API_KEY'))
                ->post("https://api.openai.com/v1/chat/completions", [
                    "model" => "gpt-5-search-api",
                    "messages" => [
                        ["role" => "system", "content" => "Return ONLY JSON. Always valid JSON."],
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
                'label' => $label,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

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

            $label = $topic->topic_name;
            $query = $topic->search_query;

            $this->info("\n🔍 Procesando topic: {$label}");

            $result = $this->searchBlock($query, $label);

            /* ==========================================================
             *     MANEJO DE FALLO → NO HAY TENDENCIAS
             * ========================================================== */
            if (!$result || !isset($result['tendencias']) || count($result['tendencias']) === 0) {

                $this->warn("⛔ No se generaron tendencias para: {$label}");

                // Registrar fallo
                $topic->fail_count++;
                $topic->last_fail_at = now();

                // Si supera 3 fallos → desactivarlo automáticamente
                if ($topic->fail_count >= 3) {
                    $topic->active = 0;
                    $topic->auto_disabled_reason = "fail_count_limit_reached";
                    $this->error("❌ Topic DESACTIVADO automáticamente por fallos repetidos: {$label}");
                }

                $topic->save();
                continue;
            }

            /* ==========================================================
             *     REGISTRAR ÉXITO
             * ========================================================== */
            $topic->success_count++;
            $topic->last_success_at = now();

            // Resetear fallos si tuvo éxito
            if ($topic->fail_count > 0) {
                $topic->fail_count = 0;
                $topic->auto_disabled_reason = null;
            }

            $topic->save();

            /* ==========================================================
             *     GUARDAR FUENTES
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
             *     GUARDAR TENDENCIAS (UPSERT = SIN DUPLICADOS)
             * ========================================================== */
            foreach ($result['tendencias'] as $t) {

                // Registrar fuente principal
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
                        'topic_category' => $label,
                        'trend_score'    => $t['trend_score'],

                        'regions'      => json_encode($t['regions']),
                        'source_url'   => $t['source_links'][0]['url'] ?? null,
                        'source_title' => $t['source_links'][0]['title'] ?? null,
                        'raw_data'     => json_encode($t)
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
