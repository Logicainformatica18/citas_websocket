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
                            {--limit=10 : Máximo de lenguajes a sugerir}
                            {--sleep=0 : (Sin uso, se mantiene por compatibilidad)}
                            {--dry-run : Muestra lo que insertaría sin escribir en la BD}';

    protected $description = 'Descubre lenguajes de programación estratégicos faltantes y los asocia a carreras';

    /**
     * Entidades que NO son lenguajes (instituciones, consultoras, empresas, plataformas).
     * Se comparan contra el nombre normalizado.
     */
    protected array $blocklist = [
        'idc', 'ocde', 'oecd', 'unesco', 'wef', 'world economic forum', 'world bank',
        'banco mundial', 'oit', 'ilo', 'gartner', 'forrester', 'mckinsey', 'deloitte',
        'pwc', 'kpmg', 'accenture', 'ieee', 'iso', 'acm', 'linkedin', 'coursera',
        'lightcast', 'holoniq', 'microsoft', 'google', 'oracle', 'amazon', 'ibm',
        'aws', 'azure', 'google cloud', 'gcp', 'docker', 'kubernetes',
    ];

    public function handle()
    {
        $limit  = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info('🔍 Analizando brechas estratégicas de lenguajes...');

        if ($dryRun) {
            $this->warn('🧪 Modo dry-run: no se escribirá nada en la base de datos');
        }

        /* ======================================
           CARRERAS (mapa nombre normalizado => id)
        ====================================== */
        $careers = DB::table('careers')->select('id', 'name')->get();

        if ($careers->isEmpty()) {
            $this->error('No hay carreras registradas.');
            return Command::FAILURE;
        }

        $careerMap = [];
        foreach ($careers as $c) {
            $careerMap[$this->normalizeCareer($c->name)] = $c->id;
        }

        /* ======================================
           LENGUAJES EXISTENTES (se cargan una sola vez)
        ====================================== */
        $existing = DB::table('market_entities')
            ->where('entity_type', 'language')
            ->select('name', 'slug')
            ->get();

        $existingNames = $existing
            ->map(fn ($row) => $this->normalizeName($row->name))
            ->flip()
            ->all();

        $existingSlugs = $existing
            ->pluck('slug')
            ->filter()
            ->flip()
            ->all();

        /* ======================================
           PROMPT + GPT
        ====================================== */
        $prompt = $this->buildPrompt(
            $careers->pluck('name')->all(),
            $existing->pluck('name')->all(),
            $limit
        );

        try {
            $rawResponse = $this->gptSearch($prompt);
        } catch (\Throwable $e) {
            $this->error('❌ ' . $e->getMessage());
            Log::error('[LANGUAGE-GAP] GPT request failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        $response = $this->extractJson($rawResponse);

        if (!isset($response['languages']) || !is_array($response['languages'])) {
            $this->error('❌ Respuesta GPT inválida');
            Log::error('GPT_INVALID_LANGUAGE_RESPONSE', ['response' => $rawResponse]);
            return Command::FAILURE;
        }

        if (empty($response['languages'])) {
            $this->warn('🟡 GPT no encontró candidatos que cumplan los criterios');
            return Command::SUCCESS;
        }

        /* ======================================
           PROCESAR CANDIDATOS
        ====================================== */
        $inserted = 0;

        foreach (array_slice($response['languages'], 0, $limit) as $lang) {

            if (empty($lang['name']) || empty($lang['careers']) || !is_array($lang['careers'])) {
                continue;
            }

            $name           = trim($lang['name']);
            $normalizedName = $this->normalizeName($name);
            $slug           = Str::slug($normalizedName);

            if ($normalizedName === '' || $slug === '') {
                continue;
            }

            // 1. Lista negra (instituciones, consultoras, empresas...)
            if (in_array($normalizedName, $this->blocklist, true)) {
                $this->warn("⛔ Bloqueado (no es lenguaje): {$name}");
                continue;
            }

            // 2. Ya existe por nombre normalizado
            if (isset($existingNames[$normalizedName])) {
                $this->warn("⏭ Ya existe: {$name}");
                continue;
            }

            // 3. Ya existe por slug
            if (isset($existingSlugs[$slug])) {
                $this->warn("⏭ Ya existe por slug: {$name}");
                continue;
            }

            // 4. Resolver carreras (solo las que existen en la BD)
            $careerIds = [];
            foreach ($lang['careers'] as $careerName) {
                if (!is_string($careerName)) {
                    continue;
                }

                $key = $this->normalizeCareer($careerName);

                if (isset($careerMap[$key])) {
                    $careerIds[] = $careerMap[$key];
                } else {
                    $this->line("   ⚠ Carrera no encontrada: {$careerName}");
                }
            }

            $careerIds = array_values(array_unique($careerIds));

            if (empty($careerIds)) {
                $this->warn("⏭ Sin carreras válidas, se omite: {$name}");
                continue;
            }

            if ($dryRun) {
                $this->info("🧪 Se insertaría: {$name} → " . count($careerIds) . ' carrera(s)');
                $existingNames[$normalizedName] = true;
                $existingSlugs[$slug] = true;
                continue;
            }

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
                foreach ($careerIds as $careerId) {
                    DB::table('market_entity_career')->updateOrInsert(
                        [
                            'market_entity_id' => $marketEntityId,
                            'career_id'        => $careerId,
                        ],
                        [
                            'relevance_score' => 0.85,
                            'source'          => 'ai',
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]
                    );
                }

                DB::commit();

                // Evita duplicados si GPT repite el mismo lenguaje en la respuesta
                $existingNames[$normalizedName] = true;
                $existingSlugs[$slug] = true;
                $inserted++;

                $this->info("✅ Insertado: {$name}");

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

        $this->info("🏁 Finalizado. Insertados: {$inserted}");
        return Command::SUCCESS;
    }

    /* ====================================================== */

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

    /**
     * Normaliza nombres de carrera (minúsculas UTF-8 y sin tildes)
     * para que "Ingeniería" e "ingenieria" coincidan.
     */
    protected function normalizeCareer(string $name): string
    {
        return Str::of(Str::ascii($name))
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    /* ====================================================== */

    protected function buildPrompt(array $careers, array $existingLanguages, int $limit): string
    {
        $careerList   = implode("\n", $careers);
        $existingList = empty($existingLanguages)
            ? '(none)'
            : implode(', ', $existingLanguages);

        return <<<PROMPT
You are a senior global software labor-market analyst specializing in programming languages, technology classification, and evidence-based academic intelligence.

Your mission:
Detect STRATEGIC programming language gaps in an academic technology observatory, ensuring maximum accuracy, transparency, and consistency.

═══════════════════════════════
AVAILABLE PROFESSIONAL CAREERS
═══════════════════════════════
{$careerList}

═══════════════════════════════
ALREADY REGISTERED LANGUAGES
═══════════════════════════════
The following languages already exist in the observatory.
Do NOT suggest them, their alternative names, or their aliases:
{$existingList}

═══════════════════════════════
DATA SOURCES AND EVIDENCE
═══════════════════════════════
Use two complementary sources:

LABOR-MARKET DATA (PRIMARY SOURCE)
Analyze the available job postings and extracted technology requirements.
Identify programming languages explicitly mentioned in professional job requirements.
Use actual job-market evidence to assess demand whenever available.
Do not invent job postings, frequencies, demand levels, or market trends.

AI TECHNOLOGY KNOWLEDGE (SECONDARY SOURCE)
Use established technical knowledge to validate whether an entity is a real programming language.
Recognize legitimate programming languages and their alternative names.
Assess their professional relevance to the available careers.
Never use general AI knowledge as a substitute for observed job-market demand.
Never present an AI inference as a finding extracted from job postings.

═══════════════════════════════
LANGUAGE SELECTION CRITERIA
═══════════════════════════════
Suggest up to {$limit} programming languages that:
Are recognized, real programming languages.
Are used professionally in industry.
Are relevant to at least one available career.
Have identifiable labor-market demand supported by the available evidence.
Represent meaningful strategic opportunities for academic evaluation.

Prioritize languages according to available labor-market evidence and professional relevance.
Do not assume that a language represents an academic gap unless the available information supports that conclusion.

═══════════════════════════════
STRICT ENTITY CLASSIFICATION
═══════════════════════════════
Before including any entity, verify that it is genuinely a programming language.

NEVER classify the following as programming languages:
Companies or organizations (Microsoft, Google, Oracle, Amazon).
Brands or technology vendors.
Research, analyst, or consulting firms (IDC, Gartner, Forrester, McKinsey, Deloitte).
International, governmental, or multilateral institutions (OCDE/OECD, UNESCO, WEF, World Bank, ILO/OIT).
Publishers of reports, surveys, or market studies, and the names of those reports.
Universities, associations, or standards bodies (IEEE, ISO, ACM).
Cloud platforms (AWS, Azure, Google Cloud).
Frameworks or libraries (React, Angular, Laravel).
DevOps tools or container technologies (Docker, Kubernetes).
Databases or database management systems.
Certifications.
Software products.
Generic concepts, methodologies, or technical skills.

ACRONYMS:
An acronym or abbreviation is NOT a programming language by default.
Include an acronym only if it is the established name of a recognized programming language (e.g., SQL, COBOL, PHP).
If an acronym refers to an organization, institution, report, standard, or company (e.g., IDC, OCDE, OECD, WEF, ILO), exclude it.
If the meaning of an acronym cannot be confirmed as a programming language, exclude it.

IMPORTANT:
A company, institution, or report cited as a SOURCE of data is never a programming language, regardless of how often it appears.
A company, product, or platform appearing frequently in job postings does not make it a programming language.
Do not infer a programming language from a related company, framework, or product unless the language itself is explicitly identified in the available evidence.
Distinguish between a language and its associated ecosystem.
Recognize valid programming languages even when they share names or associations with companies or products.
When an entity is ambiguous, include it only if the available context clearly establishes that it refers to a programming language.
If classification remains uncertain, exclude it.

═══════════════════════════════
DEMAND VALIDATION
═══════════════════════════════
Prioritize observed demand from the available job postings.
Do not fabricate demand, frequencies, percentages, rankings, or growth trends.
Do not classify a language as strongly demanded solely because it is popular or widely known.
Do not confuse technology mentions with explicit programming language requirements.
Do not interpret the absence of evidence as evidence of market demand.
If the available evidence is insufficient to support a recommendation, exclude the candidate.

═══════════════════════════════
CAREER ALIGNMENT
═══════════════════════════════
Associate each language only with careers where it has a clear and defensible professional application.
Use only the exact career names provided, copied exactly as written above.
Do not automatically associate every language with every career.
Do not invent academic curriculum gaps or assume that a language is absent from a career's curriculum.

═══════════════════════════════
ACCURACY AND TRANSPARENCY
═══════════════════════════════
Accuracy takes priority over completeness.
Return a maximum of {$limit} validated languages.
Return fewer than {$limit} if fewer candidates meet the criteria.
Return an empty array if no candidates qualify.
Never add languages merely to complete the list.
Never invent missing information.
Avoid duplicate languages and normalize equivalent names.
Do not present assumptions as verified findings.
Exclude any candidate whose classification or supporting evidence cannot be established with sufficient confidence.

═══════════════════════════════
MANDATORY FINAL VALIDATION
═══════════════════════════════
Before generating the final response, verify internally that:
Every entity is a real programming language.
No company, brand, product, platform, or framework has been included.
No research firm, institution, report publisher, or organizational acronym (e.g., IDC, OCDE) has been included.
No already registered language or alias has been included.
Each language has identifiable supporting evidence of labor-market demand.
Career associations are technically justified.
No unsupported academic gap has been assumed.
No duplicate languages are present.
The result contains no more than {$limit} languages.
The JSON follows the required schema exactly.
Remove any candidate that fails validation.

═══════════════════════════════
OUTPUT FORMAT
═══════════════════════════════
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
Do NOT include additional fields.
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
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4.1-mini',
                'temperature'     => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a strict programming-language classifier and labor-market analyst. You respond only with valid JSON.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Error GPT: ' . $response->body());
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    /* ====================================================== */

    protected function extractJson(string $text): array
    {
        $text = trim(preg_replace('/```json|```/i', '', $text));

        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }

        return [];
    }
}
