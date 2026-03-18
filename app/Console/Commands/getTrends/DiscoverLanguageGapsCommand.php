<?php

namespace App\Console\Commands\getTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscoverLanguageGapsCommand extends Command
{
    protected $signature = 'languages:discover-gaps
                            {--limit=10}
                            {--sleep=5}
                            {--dry-run}';

    protected $description = 'Descubre lenguajes de programación estratégicos faltantes y los asocia a carreras';

    public function handle()
    {
        $limit  = (int) $this->option('limit');
        $sleep  = (int) $this->option('sleep');
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Analizando brechas estratégicas de lenguajes...');

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
            !isset($response['languages']) ||
            !is_array($response['languages'])
        ) {
            $this->error('❌ Respuesta GPT inválida');
            Log::error('GPT_INVALID_LANGUAGE_RESPONSE', ['response' => $rawResponse]);
            return Command::FAILURE;
        }

        foreach ($response['languages'] as $lang) {

            if (empty($lang['name']) || empty($lang['careers'])) {
                continue;
            }
$name = trim($lang['name']);
$normalizedName = $this->normalizeName($name);
$slug = Str::slug($normalizedName);

// 1. Validación por nombre (normalizado)
if ($this->languageExists($name)) {
    $this->warn("⏭ Ya existe: {$name}");
    continue;
}

// 2. Validación por slug
$existsSlug = DB::table('market_entities')
    ->where('entity_type', 'language')
    ->where('slug', $slug)
    ->exists();

if ($existsSlug) {
    $this->warn("⏭ Ya existe por slug: {$name}");
    continue;
}

// 3. Recién aquí
DB::beginTransaction();

            try {


                /* ======================================
                   1️⃣ INSERTAR LENGUAJE
                ====================================== */

                $marketEntityId = DB::table('market_entities')->insertGetId([
                    'name'        => $name,
                    'slug'        => $slug,
                    'entity_type' => 'language',
                    'origin'      => 'ai_generated',
                    'has_trend'   => 1,
                    'status'      => 'candidate',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                /* ======================================
                   2️⃣ ASOCIAR A CARRERAS
                ====================================== */

                foreach ($lang['careers'] as $careerName) {

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
                            'relevance_score'  => 0.85,
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

                Log::error('[LANGUAGE-GAP]', [
                    'language' => $lang,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Finalizado');
        return Command::SUCCESS;
    }
protected function normalizeName(string $name): string
{
    return Str::of($name)
        ->lower()
        ->replace(['(', ')'], '')
      ->replaceMatches('/\b(programming|language)\b/', '')
        ->replaceMatches('/\s+/', ' ')
        ->trim()
        ->toString();
}
    /* ====================================================== */

protected function languageExists(string $name): bool
{
    $normalized = $this->normalizeName($name);

    return DB::table('market_entities')
        ->where('entity_type', 'language')
        ->get()
        ->contains(function ($row) use ($normalized) {
            return $this->normalizeName($row->name) === $normalized;
        });
}

    /* ====================================================== */

    protected function buildPrompt(array $careers, int $limit): string
    {
        $careerList = implode("\n- ", $careers);

        return <<<PROMPT
You are a senior global software labor-market analyst.

Your mission:
Detect STRATEGIC programming language gaps in an academic technology observatory.

═══════════════════════════════
AVAILABLE PROFESSIONAL CAREERS
═══════════════════════════════

- {$careerList}

═══════════════════════════════
INSTRUCTIONS
═══════════════════════════════

Suggest up to {$limit} programming languages that:

1) Are currently in strong global demand
2) Are used professionally in industry
3) Are aligned to at least one career above
4) Are real programming languages (not frameworks)
5) Are not generic concepts

Avoid:
- Frameworks (e.g., React, Laravel)
- Databases
- DevOps tools
- Certifications

Return STRICT JSON:

{
  "languages": [
    {
      "name": "Language Name",
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
                    ['role' => 'system', 'content' => 'You are an expert in global programming language trends.'],
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
