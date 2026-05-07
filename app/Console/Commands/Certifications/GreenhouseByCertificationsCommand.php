<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\City;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
 use App\Services\SourceStatusService;
 use App\Services\ScraperRunService;
class GreenhouseByCertificationsCommand extends Command
{
    protected $signature = 'greenhouse:certifications {--company=*}';

    protected $description = '🌱 Importa ofertas desde Greenhouse usando keywords por certificación (skills-based).';

    protected $stats = [
        'mapped'  => 0,
        'skipped' => 0,
    ];

    public function handle()
{
    $companies = $this->option('company');

    if (empty($companies)) {

        $this->error(
            "❌ Debes pasar empresas, ej: --company=stripe --company=cloudflare"
        );

        return;
    }

    $baseUrl = 'https://boards-api.greenhouse.io/v1/boards';

    $run = ScraperRunService::start(
        $this->signature,
        'Greenhouse',
        'certifications'
    );

    $source = 'greenhouse_certifications';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [
            'companies' => $companies,
        ],
        apiUrl: $baseUrl
    );

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    $connectionOk = false;
    $startedAt = now();

    try {

        // 🔹 certificaciones
        $certifications = Certification::where('enabled', 1)
            ->select('id', 'name', 'keywords')
            ->get();

        $this->info(
            "🌱 Greenhouse | {$certifications->count()} certificaciones"
        );

        foreach ($companies as $companySlug) {

            $this->warn("\n🏢 Empresa: {$companySlug}");

            $url =
                "https://boards-api.greenhouse.io/v1/boards/{$companySlug}/jobs";

            try {

                $response = Http::retry(3, 2000)
                    ->timeout(20)
                    ->get($url);

            } catch (\Throwable $e) {

                SourceStatusService::connectionFailed(
                    $source,
                    "Connection exception: {$companySlug}"
                );

                Log::error($e);

                continue;
            }

            if ($response->failed()) {

                SourceStatusService::connectionFailed(
                    $source,
                    "HTTP failed: {$companySlug}"
                );

                $this->error(
                    "❌ No se pudo obtener datos de {$companySlug}"
                );

                continue;
            }

            $connectionOk = true;

            $jobs = $response->json('jobs') ?? [];

            $hasContent = $this->companyHasContent($jobs);

            $this->line(
                $hasContent
                    ? "✔ Esta empresa expone descripción"
                    : "⚠ Solo títulos (sin descripción)"
            );

            // ✅ evitar N+1
            $existingIds = JobOffer::whereIn(
                'external_id',
                collect($jobs)->pluck('id')->filter()
            )
            ->where('source', 'Greenhouse')
            ->pluck('id', 'external_id');

            foreach ($certifications as $cert) {

                $certId   = $cert->id;
                $certName = $cert->name;

                // 🧠 keywords
                $keywords = [];

                if (!empty($cert->keywords)) {

                    $keywords = is_array($cert->keywords)
                        ? $cert->keywords
                        : json_decode($cert->keywords, true);
                }

                // 🔁 fallback
                if (empty($keywords)) {

                    $keywords = [
                        strtolower($certName)
                    ];
                }

                $this->line("\n🔎 Certificación: {$certName}");

                $this->line(
                    "   🔑 Keywords: " .
                    implode(', ', $keywords)
                );

                $found = [];
                $new   = [];

                $countries  = [];
                $modalities = [];

                foreach ($jobs as $job) {

                    try {

                        $title =
                            $job['title'] ?? '';

                        $content =
                            $hasContent
                                ? ($job['content'] ?? '')
                                : '';

                        $text = strtolower(
                            $title . ' ' . $content
                        );

                        // 🔎 match keywords
                        $matched = false;

                        foreach ($keywords as $kw) {

                            if (
                                $kw &&
                                str_contains(
                                    $text,
                                    strtolower($kw)
                                )
                            ) {

                                $matched = true;

                                break;
                            }
                        }

                        if (!$matched) {
                            continue;
                        }

                        $companyName =
                            $job['company_name']
                            ?? ucfirst($companySlug);

                        $urlJob =
                            $job['absolute_url'] ?? null;

                        $externalId =
                            $job['id'];

                        // 📍 ubicación
                        $loc = strtolower(
                            $job['location']['name']
                            ?? ''
                        );

                        // 🌍 país
                        $countryCode =
                            $this->extractCountryCodeOrNull(
                                $loc
                            );

                        if (!$countryCode) {

                            $this->stats['skipped']++;
                            $totalSkippedAll++;

                            continue;
                        }

                        $countryFull =
                            CountryNormalizer::normalize(
                                $countryCode
                            );

                        // 🏙 ciudad
                        $cityRaw =
                            $this->extractCity($loc);

                        [$cityClean, $lat, $lng] =
                            $this->getCoords(
                                $cityRaw,
                                $countryCode
                            );

                        if (!$lat || !$lng) {

                            $this->stats['skipped']++;
                            $totalSkippedAll++;

                            continue;
                        }

                        $modality =
                            $this->detectModality(
                                $loc,
                                $content
                            );

                        $countries[$countryFull] =
                            ($countries[$countryFull] ?? 0) + 1;

                        $modalities[$modality] =
                            ($modalities[$modality] ?? 0) + 1;

                        // 🔁 duplicado
                        if (
                            isset($existingIds[$externalId])
                        ) {

                            $existing = JobOffer::find(
                                $existingIds[$externalId]
                            );

                            if ($existing) {

                                $existing->certifications()
                                    ->syncWithoutDetaching([
                                        $certId
                                    ]);
                            }

                            $found[] = $externalId;

                            continue;
                        }

                        $region =
                            RegionHelper::fromCountry(
                                $countryFull
                            );

                        $offer = JobOffer::create([

                            'title' =>
                                $title ?: 'N/A',

                            'company' =>
                                $companyName,

                            'country' =>
                                $countryFull,

                            'city' =>
                                $cityClean,

                            'latitude' =>
                                $lat,

                            'longitude' =>
                                $lng,

                            'modality' =>
                                $modality,

                            'experience_level' =>
                                $this->extractExperience(
                                    $content
                                ),

                            'education_level' =>
                                $this->extractEducation(
                                    $content
                                ),

                            'skills' =>
                                $this->extractSkills(
                                    $content
                                ),

                            'certifications' =>
                                $certName,

                            'requirements' =>
                                strip_tags($content),

                            'source' =>
                                'Greenhouse',

                            'external_id' =>
                                $externalId,

                            'url' =>
                                $urlJob,

                            'published_at' =>
                                $job['updated_at']
                                ?? now(),

                            'region' =>
                                $region,
                        ]);

                        $offer->certifications()
                            ->syncWithoutDetaching([
                                $certId
                            ]);

                        $new[]   = $externalId;
                        $found[] = $externalId;

                    } catch (\Throwable $e) {

                        $this->stats['skipped']++;
                        $totalSkippedAll++;

                        Log::error(
                            "Greenhouse item error: {$e->getMessage()}"
                        );
                    }
                }

                /* =====================================================
                   📊 ACUMULADOS
                ===================================================== */

                $totalFoundAll += count($found);
                $totalInsertedAll += count($new);

                /* =====================================================
                   📊 STATUS PROGRESS
                ===================================================== */

                SourceStatusService::progress(
                    $source,
                    $totalFoundAll,
                    $totalInsertedAll,
                    $totalSkippedAll
                );

                /* =====================================================
                   📊 MÉTRICA
                ===================================================== */

                CertificationMetric::updateOrCreate(
                    [
                        'certification_id' => $certId,
                        'run_date'         => now()->toDateString(),
                        'source'           => 'Greenhouse',
                    ],
                    [
                        'certification_name' =>
                            $certName,

                        'jobs_found_count' =>
                            count($found),

                        'jobs_new_count' =>
                            count($new),

                        'countries_breakdown' =>
                            $countries,

                        'modality_breakdown' =>
                            $modalities,
                    ]
                );

                $this->info(
                    "✔ {$certName}: " .
                    count($new) .
                    " nuevas"
                );
            }
        }

        /* =====================================================
           ✅ SUCCESS
        ===================================================== */

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        if ($connectionOk) {

            SourceStatusService::connectionOk($source);
        }

        SourceStatusService::success(
            source: $source,
            runId: $run->id,
            found: $totalFoundAll,
            inserted: $totalInsertedAll,
            skipped: $totalSkippedAll,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

        $this->newLine();

        $this->info("🎯 Finalizado");

        $this->line(
            "   ⏭️ Skipped: {$this->stats['skipped']}"
        );

    } catch (\Throwable $e) {

        ScraperRunService::failed($run, $e);

        SourceStatusService::failed(
            source: $source,
            runId: $run->id,
            e: $e,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

        throw $e;
    }
}
    /* ================= HELPERS ================= */

    protected function companyHasContent(array $jobs): bool
    {
        foreach ($jobs as $job) {
            if (!empty($job['content'])) return true;
        }
        return false;
    }

    protected function extractCity(string $loc): ?string
    {
        $parts = explode(',', $loc);
        return trim($parts[0] ?? null);
    }

    protected function extractCountryCodeOrNull(string $loc): ?string
    {
        $map = [
            'united states' => 'us',
            'usa' => 'us',
            'canada' => 'ca',
            'mexico' => 'mx',
            'brazil' => 'br',
            'spain' => 'es',
            'france' => 'fr',
            'germany' => 'de',
            'italy' => 'it',
            'argentina' => 'ar',
            'chile' => 'cl',
            'peru' => 'pe',
            'colombia' => 'co',
            'uk' => 'gb',
            'united kingdom' => 'gb',
            'ireland' => 'ie',
            'australia' => 'au',
            'new zealand' => 'nz',
            'india' => 'in',
            'singapore' => 'sg',
        ];

        foreach ($map as $k => $v) {
            if (str_contains($loc, $k)) return $v;
        }

        return null;
    }

    protected function getCoords(?string $city, string $code): array
    {
        if (!$city) return [null, null, null];

        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->whereRaw('LOWER(iso2) = ?', [strtolower($code)])
            ->first();

        if ($found) {
            $this->stats['mapped']++;
            return [$found->city, $found->lat, $found->lng];
        }

        return [null, null, null];
    }

    protected function detectModality(string $loc, string $desc): string
    {
        $t = strtolower($loc . ' ' . $desc);

        return match (true) {
            str_contains($t, 'remote') => 'remote',
            str_contains($t, 'hybrid') => 'hybrid',
            default => 'no_precisa',
        };
    }

    protected function extractExperience(string $text): ?string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'senior') => 'senior',
            str_contains($t, 'mid')    => 'mid',
            str_contains($t, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $text): ?string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'bachelor') => 'bachelor',
            str_contains($t, 'master')   => 'master',
            str_contains($t, 'phd')      => 'phd',
            default => null,
        };
    }

    protected function extractSkills(string $text): ?string
    {
        $t = strtolower($text);
        $skills = [];

        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node','kubernetes','terraform'] as $skill) {
            if (str_contains($t, $skill)) $skills[] = strtoupper($skill);
        }

        return $skills ? implode(', ', $skills) : null;
    }
}
