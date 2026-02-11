<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\EntityTrend;

class DiscoverTrendsFromMarketEntitiesCommand extends Command
{
    protected $signature = 'trends:discover-from-market
                            {--entity=certification}
                            {--sleep=10}
                            {--limit=50}';

    protected $description = 'Descubre tendencias por entidad y las guarda en entity_trends (reiniciable)';

    /* =========================================================
       ENTIDADES A PROCESAR
    ========================================================= */
    protected function getEntities(string $entityType): array
    {
        return DB::table('market_entities as me')
            ->leftJoin('entity_trends as et', function ($join) {
                $join->on('et.market_entity_id', '=', 'me.id');
            })
            ->where('me.entity_type', $entityType)
            ->groupBy('me.id', 'me.name', 'me.entity_type')
            ->orderByRaw('MAX(et.created_at) IS NOT NULL')
            ->orderByRaw('MAX(et.created_at) ASC')
            ->select([
                'me.id',
                'me.name',
                'me.entity_type',
                DB::raw('MAX(et.created_at) as last_run_at'),
            ])
            ->get()
            ->map(fn ($e) => [
                'id'   => $e->id,
                'type' => $e->entity_type,
                'name' => $e->name,
                'last_run_at' => $e->last_run_at,
            ])
            ->toArray();
    }

    /* =========================================================
       HANDLE
    ========================================================= */
    public function handle()
    {
        $year       = now()->year;
        $quarter    = now()->quarter;
        $entityType = $this->option('entity');
        $limit      = (int) $this->option('limit');
        $sleep      = (int) $this->option('sleep');

        $this->info("🔍 Ejecutando GPT-Search para {$entityType} – Y{$year} Q{$quarter}");

        $entities = array_slice(
            $this->getEntities($entityType),
            0,
            $limit
        );

        if (empty($entities)) {
            $this->warn('⚠️ No hay entidades para procesar');
            return Command::SUCCESS;
        }

        foreach ($entities as $entity) {

            try {

                $prompt = $this->buildPrompt(
                    entityType: $entity['type'],
                    entityName: $entity['name']
                );

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
                    $url   = strtok($url, '?');
                    $url   = rtrim($url, '/');
                    $title = trim($trend['source']['title'] ?? '');

                    if (!$url) {
                        continue;
                    }

                    $exists = EntityTrend::where('market_entity_id', $entity['id'])
                        ->where('year', $year)
                        ->where('quarter', $quarter)
                        ->where(function ($q) use ($url, $title) {
                            $q->where('source_url', $url);

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
                        'market_entity_id' => $entity['id'],
                        'year'             => $year,
                        'quarter'          => $quarter,
                        'trend_name'       => trim($trend['name']),
                        'trend_score'      => (float) $trend['score'],
                        'source_title'     => $title,
                        'source_url'       => $url,
                        'source_type'      => $trend['source']['type'] ?? null,
                        'match_type'       => 'explicit',
                        'confidence_score' => 0.90,
                        'discovered_by'    => 'gpt-search',
                        'discovered_at'    => now(),
                    ]);
                }

                $this->info("✅ Tendencias guardadas para {$entity['name']}");
                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error("❌ Error con {$entity['name']}");
                $this->line("👉 {$e->getMessage()}");

                Log::error('[GPT-TRENDS ERROR]', [
                    'entity'  => $entity,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Proceso finalizado');
        return Command::SUCCESS;
    }

    /* =========================================================
       EXTRAER JSON
    ========================================================= */
    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return json_decode($matches[0], true) ?? [];
        }

        return [];
    }

    /* =========================================================
       PROMPT DINÁMICO CON AÑO ACTUAL
    ========================================================= */
    protected function buildPrompt(string $entityType, string $entityName): string
    {
        $currentYear  = now()->year;
        $previousYear = $currentYear - 1;

        return <<<PROMPT
You are a global labor market and technology trends analyst.

Analyze current global trends related to:

Entity type: {$entityType}
Entity name: {$entityName}

Identify 3 to 5 relevant trends backed by real and verifiable sources.

STRICT REQUIREMENTS:
- Only use sources published in {$previousYear} or {$currentYear}.
- If older than {$previousYear}, exclude it.
- If publication year cannot be verified, exclude it.
- Do NOT invent sources.

Return strictly valid JSON:

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

Do NOT include explanations outside JSON.
PROMPT;
    }

    /* =========================================================
       GPT SEARCH
    ========================================================= */
    protected function gptSearch(string $prompt): array|string
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
                    [
                        'role' => 'system',
                        'content' => 'You are an expert labor market analyst.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Error HTTP GPT: ' . $response->body());
        }

        return data_get($response->json(), 'choices.0.message.content');
    }
}
