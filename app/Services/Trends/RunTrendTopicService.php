<?php

namespace App\Services\Trends;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ScrapingSource;
use App\Models\TechnologyTrend;
use App\Models\TrendTopic;

class RunTrendTopicService
{
    /* =========================================================
     * Public API
     * ========================================================= */

    public function run(TrendTopic $topic): int
    {
        $label = $topic->topic_name;
        $query = $topic->search_query;
        $intent = $topic->intent;

        $year = date('Y');
        $quarter = ceil(date('n') / 3);

        // ⏱️ Throttling defensivo (GPT-5 Search)
        sleep(3);

        Log::info("🔍 Ejecutando TrendTopic", [
            'topic' => $label,
            'intent' => $intent,
        ]);

        $result = $this->searchBlock($query, $label, $intent);

        if (
            !$result ||
            !isset($result['tendencias']) ||
            count($result['tendencias']) < $topic->min_required_results
        ) {
            throw new \RuntimeException(
                "No se generaron resultados suficientes para el topic."
            );
        }

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
        $saved = 0;

        foreach ($result['tendencias'] as $t) {

            $classification = $this->classifyResult($t, $intent);

            // 🛑 Protección: no guardar papers como certificaciones
            if ($intent === 'certification' && $classification !== 'certification') {
                continue;
            }

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
                    'source_id'  => $source->id ?? 1,
                ],
                [
                    'topic_category'   => $label,
                    'trend_score'      => $t['trend_score'],
                    'regions'          => json_encode($t['regions']),
                    'scanned_keywords' => json_encode($t['job_search_keywords'] ?? []),
                    'source_url'       => $t['source_links'][0]['url'] ?? null,
                    'source_title'     => $t['source_links'][0]['title'] ?? null,

                    // 🔥 trazabilidad total
                    'raw_data'         => json_encode([
                        'intent' => $intent,
                        'classification' => $classification,
                        'payload' => $t,
                    ]),
                ]
            );

            $saved++;
        }

        return $saved;
    }

    /* =========================================================
     * GPT-5 Search
     * ========================================================= */

    private function searchBlock(string $query, string $label, string $intent): ?array
    {
        $prompt = $this->buildPrompt($query, $label, $intent);

        try {
            $res = retry(3, function () use ($prompt) {
                return Http::withToken(env('OPENAI_API_KEY'))
                    ->timeout(120)
                    ->post("https://api.openai.com/v1/chat/completions", [
                        "model" => "gpt-5-search-api",
                        "messages" => [
                            [
                                "role" => "system",
                                "content" => "Return ONLY valid JSON. No markdown."
                            ],
                            [
                                "role" => "user",
                                "content" => $prompt
                            ]
                        ]
                    ]);
            }, 15);

            if ($res->failed()) {
                Log::error("GPT-5 Search HTTP Error", [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
                return null;
            }

            $content = $res->json('choices.0.message.content');
            $json = $this->extractJson($content);

            return $json ? json_decode($json, true) : null;

        } catch (\Throwable $e) {
            Log::error("GPT-5 Search Exception", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /* =========================================================
     * Prompt dinámico por intent
     * ========================================================= */

    private function buildPrompt(string $query, string $label, string $intent): string
    {
        return <<<PROMPT
You MUST perform a REAL WEB SEARCH for:

{$query}

Context intent: {$intent}

Interpretation rules:
- certification: ONLY professional certifications, credentials, exams. NO academic papers.
- technology_trend: adoption, platforms, tools, real-world usage.
- skill: skills demanded in job postings.
- workforce: workforce impact, roles, productivity, hiring.
- mixed: return relevant items from all categories.

Return ONLY this JSON:

{
  "label": "{$label}",
  "tendencias": [
    {
      "topic_name": "...",
      "trend_score": 20-100,
      "regions": ["Global", "North America", "Europe", "Asia"],
      "job_search_keywords": ["...", "..."],
      "source_links": [
        { "title": "Real article", "url": "https://..." }
      ]
    }
  ],
  "used_sources": [
    { "title": "Source title", "url": "https://..." }
  ]
}

RULES:
- Minimum {$label} results preferred.
- All URLs MUST be real.
- No fake or invented sources.
- Output MUST be valid JSON only.
PROMPT;
    }

    /* =========================================================
     * Clasificación post-GPT
     * ========================================================= */

    private function classifyResult(array $t, string $intent): string
    {
        $text = json_encode($t);

        if (preg_match('/certified|certification|exam|credential/i', $text)) {
            return 'certification';
        }

        if (preg_match('/skills?|competencies|required/i', $text)) {
            return 'skill';
        }

        if (preg_match('/workforce|hiring|roles|productivity|organization/i', $text)) {
            return 'workforce';
        }

        if (preg_match('/adoption|implementation|platform|deployment/i', $text)) {
            return 'technology_trend';
        }

        return $intent === 'mixed' ? 'mixed' : 'other';
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    private function extractJson(string $text): ?string
    {
        $s = strpos($text, '{');
        $e = strrpos($text, '}');

        return ($s !== false && $e !== false)
            ? substr($text, $s, $e - $s + 1)
            : null;
    }
}
