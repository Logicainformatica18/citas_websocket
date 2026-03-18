<?php

namespace App\Console\Commands\getTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscoverCertificationGapsCommand extends Command
{
    protected $signature = 'certifications:discover-gaps
                            {--limit=10}
                            {--sleep=5}
                            {--dry-run}';

    protected $description = 'Descubre certificaciones faltantes y las asocia a carreras usando GPT';

    protected function normalizeName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replace(['(', ')'], '')
            ->replace(['certification', 'certificate'], '')
            ->replace(['  '], ' ')
            ->trim()
            ->toString();
    }

    public function handle()
    {
        $limit  = (int) $this->option('limit');
        $sleep  = (int) $this->option('sleep');
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Analizando certificaciones existentes...');

        $existing = $this->getExistingCertifications();
        $careers  = $this->getCareers();

        $prompt   = $this->buildPrompt($existing, $careers, $limit);
        $response = $this->gptSearch($prompt);

        if (is_string($response)) {
            $response = $this->extractJson($response);
        }

        if (!isset($response['certifications'])) {
            $this->error('❌ Respuesta GPT inválida');
            return Command::FAILURE;
        }

        foreach ($response['certifications'] as $cert) {

            if (empty($cert['name']) || empty($cert['careers'])) {
                continue;
            }

            $slug = Str::slug($cert['name']);
            $normalized = $this->normalizeName($cert['name']);

            /* ===================================================
               🔍 VALIDACIÓN DUPLICADOS (NORMALIZADO)
            =================================================== */
            $exists = DB::table('market_entities')
                ->where('entity_type', 'certification')
                ->whereRaw("
                    LOWER(
                        REPLACE(
                            REPLACE(
                                REPLACE(name, 'certification', ''),
                            'certificate', ''),
                        '(', '')
                    ) = ?
                ", [$normalized])
                ->exists();

            if ($exists) {
                $this->warn("⏭ Ya existe: {$cert['name']}");
                continue;
            }

            /* ===================================================
               🔍 VALIDACIÓN POR SLUG (extra seguridad)
            =================================================== */
            $existsSlug = DB::table('market_entities')
                ->where('entity_type', 'certification')
                ->where('slug', $slug)
                ->exists();

            if ($existsSlug) {
                $this->warn("⏭ Ya existe por slug: {$cert['name']}");
                continue;
            }

            if ($dryRun) {
                $this->line("🆕 {$cert['name']}");
                continue;
            }

            DB::beginTransaction();

            try {

                /* ===================================================
                   1️⃣ INSERT EN MARKET_ENTITIES
                =================================================== */

                $marketEntityId = DB::table('market_entities')->insertGetId([
                    'name'             => $cert['name'],
                    'slug'             => $slug,
                    'entity_type'      => 'certification',
                    'origin'           => 'ai_generated',
                    'vendor'           => $cert['vendor'] ?? null,
                    'level'            => $cert['level'] ?? null,
                    'has_trend'        => 1,
                    'status'           => 'candidate',
                    'discovery_method' => 'gpt',
                    'discovery_engine' => 'gpt-4.1-mini',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                /* ===================================================
                   2️⃣ INSERT EN CERTIFICATIONS
                =================================================== */
$certExists = DB::table('certifications')
    ->whereRaw('LOWER(name) = ?', [strtolower($cert['name'])])
    ->exists();

if ($certExists) {
    $this->warn("⏭ Certification ya existe: {$cert['name']}");
    DB::rollBack();
    continue;
}
                DB::table('certifications')->insert([
                    'name'             => $cert['name'],
                    'vendor'           => $cert['vendor'] ?? null,
                    'level'            => $cert['level'] ?? null,
                    'enabled'          => 1,
                    'market_entity_id' => $marketEntityId,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                /* ===================================================
                   3️⃣ ASOCIAR A CARRERAS
                =================================================== */

                foreach ($cert['careers'] as $careerName) {

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

                $this->info("✅ Insertado: {$cert['name']}");
                sleep($sleep);

            } catch (\Throwable $e) {

                DB::rollBack();

                $this->error("❌ Error en {$cert['name']}");
                $this->error($e->getMessage());

                Log::error('[CERT-GAP]', [
                    'certification' => $cert,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Finalizado');
        return Command::SUCCESS;
    }

    protected function getExistingCertifications(): array
    {
        return DB::table('market_entities')
            ->where('entity_type', 'certification')
            ->pluck('name')
            ->toArray();
    }

    protected function getCareers(): array
    {
        return DB::table('careers')
            ->pluck('name')
            ->toArray();
    }

    protected function buildPrompt(array $existing, array $careers, int $limit): string
    {
        $existingList = implode("\n- ", $existing);
        $careerList   = implode("\n- ", $careers);

        return <<<PROMPT
You are a global technology certification strategist.

These are my CURRENT certifications:

- {$existingList}

These are my PROFESSIONAL CAREERS:

- {$careerList}

Suggest up to {$limit} important certifications missing from my system.

Return STRICT JSON:

{
  "certifications": [
    {
      "name": "Official certification name",
      "vendor": "Vendor name",
      "level": "Foundational | Associate | Professional | Expert | Specialty",
      "careers": ["Career 1", "Career 2"]
    }
  ]
}

Do NOT return text outside JSON.
PROMPT;
    }

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
                    ['role' => 'system', 'content' => 'You are an expert in global IT certifications.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Error GPT: ' . $response->body());
        }

        return data_get($response->json(), 'choices.0.message.content');
    }

    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }
        return [];
    }
}
