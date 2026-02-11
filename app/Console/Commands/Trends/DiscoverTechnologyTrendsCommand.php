<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\EntityTrend;

class DiscoverTechnologyTrendsCommand extends Command
{
    protected $signature = 'trends:discover-technologies
                            {--limit=20 : Cantidad máxima de tecnologías}
                            {--sleep=10 : Segundos entre requests GPT}';

    protected $description = 'Descubre tendencias de mercado SOLO para tecnologías (market_entities)';

    /* =========================================================
       HANDLE
    ========================================================= */
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

                $prompt = $this->buildPrompt($technology['name']);
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

                    $url   = trim($trend['source']['url'] ?? '');
                    $url   = strtok($url, '?'); // elimina parámetros
                    $url   = rtrim($url, '/');

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
                                $q->orWhereRaw('LOWER(TRIM(source_title)) = ?', [strtolower($title)]);
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
                        'discovered_by'    => 'gpt-search',
                        'discovered_at'    => now(),
                    ]);
                }

                $this->info("✅ {$technology['name']} procesado");
                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error("❌ Error en {$technology['name']}");
                $this->line("👉 {$e->getMessage()}");

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
            ->select(
                'me.id',
                'me.name',
                DB::raw('MAX(et.created_at) as last_trend_at')
            )
            ->get()
            ->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])
            ->toArray();
    }

    /* =========================================================
       PROMPT GPT – TECNOLOGÍAS
    ========================================================= */
    protected function buildPrompt(string $technology): string
    {
        $currentYear  = now()->year;
        $previousYear = $currentYear - 1;

        return <<<PROMPT
You are a global enterprise technology market analyst.

Analyze current market trends related to the technology "{$technology}".

Focus on:
- Enterprise adoption
- Labor market demand
- Cloud & infrastructure relevance
- Integration with other technologies
- Industry usage

Return 3 to 5 relevant trends.

Each trend MUST be supported by a real and verifiable online source.

STRICT REQUIREMENTS:
- Only use sources published in {$previousYear} or {$currentYear}.
- If older than {$previousYear}, exclude it.
- If publication year cannot be verified, exclude it.
- Do NOT invent sources.

Return STRICTLY valid JSON:

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

PRIORITY SOURCES – AUTHORITATIVE AND TRUSTED INSTITUTIONS

Tier 1 – Global Strategic & Labor Reports (Highest Priority)
- World Economic Forum – Future of Jobs Report (weforum.org)
- OECD – Digital Economy Outlook / Skills for Jobs Database (oecd.org)
- UNESCO – AI Competency Framework / Digital Education (unesco.org)
- Gartner – Hype Cycle / Top Technology Trends / Cybersecurity Trends (gartner.com)
- McKinsey – Tech & AI Insights (mckinsey.com)
- PwC – AI Jobs Barometer (pwc.com)
- Deloitte – Technology Industry Insights (deloitte.com)
- HolonIQ – Global EdTech & Digital Skills Intelligence (holoniq.com)
- LinkedIn Economic Graph / Global Skills Report (linkedin.com)
- Coursera Global Skills Report (coursera.org)
- Lightcast / Burning Glass (lightcast.io)

Tier 2 – Industry & Enterprise Technology Sources
- TechCrunch
- The Verge
- ZDNet
- VentureBeat
- Computerworld
- InformationWeek
- TechRepublic
- Network World
- The Register
- TechTarget

Tier 3 – Academic & Research Sources
- IEEE Xplore
- Research conferences (e.g., ICSA, ConferenceIndex, DBTA)
- Towards Data Science
- KDnuggets

Tier 4 – Cloud & Enterprise Vendor Events
- AWS re:Invent
- Microsoft Ignite
- Oracle CloudWorld
- Salesforce Dreamforce
- Cisco Live
- CES Tech
- CloudSummit

Tier 5 – EdTech & Digital Education
- EDUCAUSE Horizon Report
- EdTech Magazine (Higher Ed & K12)
- Somos Digital (DigComp)
- APTC Peru

RULES:
- Prefer Tier 1 sources whenever possible.
- Use Tier 2–5 only if Tier 1 does not cover the specific entity.
- Do NOT use low-authority blogs unless highly relevant.
- Prioritize sources from the current year or previous year.

Do NOT include any text outside JSON.
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
                    ['role' => 'system', 'content' => 'You are an expert technology market analyst.'],
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
