<?php

namespace App\Console\Commands\getTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscoverTechnologyGapsCommand extends Command
{
    protected $signature = 'technologies:discover-gaps
                            {--limit=10}
                            {--sleep=5}
                            {--dry-run}';

    protected $description = 'Descubre nuevas tecnologías estratégicas y las asocia a carreras';

    public function handle()
    {
        $limit  = (int) $this->option('limit');
        $sleep  = (int) $this->option('sleep');
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Analizando brechas tecnológicas estratégicas...');

        $careers = DB::table('careers')->pluck('name')->toArray();

        if (empty($careers)) {
            $this->error('No hay carreras registradas.');
            return Command::FAILURE;
        }

        $prompt = $this->buildPrompt($careers, $limit);

        $rawResponse = $this->gptSearch($prompt);
        $response    = $this->extractJson($rawResponse);

        if (
            !is_array($response) ||
            !isset($response['technologies']) ||
            !is_array($response['technologies'])
        ) {
            $this->error('❌ Respuesta GPT inválida');
            Log::error('GPT_INVALID_RESPONSE', ['response' => $rawResponse]);
            return Command::FAILURE;
        }

        foreach ($response['technologies'] as $tech) {

            if (empty($tech['name']) || empty($tech['careers'])) {
                continue;
            }

            $name = trim($tech['name']);
            $slug = Str::slug($name);

            if ($this->technologyExists($name, $slug)) {
                $this->warn("⏭ Ya existe: {$name}");
                continue;
            }

            if ($dryRun) {
                $this->line("🆕 {$name}");
                continue;
            }

            DB::beginTransaction();

            try {

                /* ======================================
                   1️⃣ INSERTAR TECHNOLOGY
                ====================================== */

                $marketEntityId = DB::table('market_entities')->insertGetId([
                    'name'        => $name,
                    'slug'        => $slug,
                    'entity_type' => 'technology',
                    'origin'      => 'ai_generated',
                    'has_trend'   => 1,
                    'status'      => 'candidate',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                /* ======================================
                   2️⃣ ASOCIAR A CARRERAS
                ====================================== */

                foreach ($tech['careers'] as $careerName) {

                    $career = DB::table('careers')
                        ->whereRaw('LOWER(name) = ?', [strtolower($careerName)])
                        ->first();

                    if (!$career) continue;

                    DB::table('market_entity_career')->updateOrInsert(
                        [
                            'market_entity_id' => $marketEntityId,
                            'career_id'        => $career->id,
                        ],
                        [
                            'relevance_score'  => 0.80,
                            'source'           => 'ai',
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]
                    );
                }

                DB::commit();

                $this->info("✅ Insertado: {$name}");

                sleep($sleep);

            } catch (\Throwable $e) {

                DB::rollBack();

                $this->error("❌ Error en {$name}");
                $this->error($e->getMessage());

                Log::error('[TECH-GAP]', [
                    'technology' => $tech,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Finalizado');
        return Command::SUCCESS;
    }

    /* ====================================================== */

    protected function technologyExists(string $name, string $slug): bool
    {
        return DB::table('market_entities')
            ->where('entity_type', 'technology')
            ->where(function ($q) use ($name, $slug) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($name)])
                  ->orWhere('slug', $slug)
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($name) . '%']);
            })
            ->exists();
    }

    /* ====================================================== */

    protected function buildPrompt(array $careers, int $limit): string
    {
        $careerList = implode("\n- ", $careers);

        return <<<PROMPT
You are a senior global labor-market technology analyst.

Your mission:
Detect STRATEGIC technology gaps in an academic technology observatory.

═══════════════════════════════
AVAILABLE PROFESSIONAL CAREERS
═══════════════════════════════

- {$careerList}

═══════════════════════════════
INSTRUCTIONS
═══════════════════════════════

Suggest up to {$limit} SPECIFIC technologies that:

1) Are currently in strong global demand
2) Are tools, frameworks, platforms, engines, databases, or technical systems
3) Are aligned to at least one career above
4) Are NOT certifications
5) Are NOT generic terms (avoid: "Software Development", "Artificial Intelligence", etc.)
6) Are short and concrete names (e.g., Kafka, Snowflake, Terraform, FastAPI, Databricks)

Prioritize:
- Cloud & DevOps
- AI & Data Engineering
- Cybersecurity
- Distributed Systems
- Modern Backend
- Enterprise Architecture

Return STRICT JSON:

{
  "technologies": [
    {
      "name": "Technology Name",
      "careers": ["Career 1", "Career 2"]
    }
  ]
}

Do NOT include explanations.
Do NOT include text outside JSON.
PROMPT;
    }

    /* ====================================================== */

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
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert in global technology strategy and labor markets.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Error GPT: ' . $response->body());
        }

        return data_get($response->json(), 'choices.0.message.content');
    }

    /* ====================================================== */

    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }

        return [];
    }
}