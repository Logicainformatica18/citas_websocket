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
            $this->error("❌ Debes pasar empresas, ej: --company=stripe --company=cloudflare");
            return;
        }

        // 🔹 Cargar certificaciones con keywords
        $certifications = Certification::where('enabled', 1)
            ->select('id', 'name', 'keywords')
            ->get();

        $this->info("🌱 Greenhouse | {$certifications->count()} certificaciones");

        foreach ($companies as $companySlug) {

            $this->warn("\n🏢 Empresa: {$companySlug}");

            $url = "https://boards-api.greenhouse.io/v1/boards/{$companySlug}/jobs";

            try {
                $response = Http::timeout(20)->get($url);

                if ($response->failed()) {
                    $this->error("❌ No se pudo obtener datos de {$companySlug}");
                    continue;
                }

                $jobs = $response->json('jobs') ?? [];
                $hasContent = $this->companyHasContent($jobs);

                $this->line($hasContent
                    ? "✔ Esta empresa expone descripción"
                    : "⚠ Solo títulos (sin descripción)");

                foreach ($certifications as $cert) {

                    $certId   = $cert->id;
                    $certName = $cert->name;

                    // 🧠 Obtener keywords (JSON → array)
                    $keywords = [];

                    if (!empty($cert->keywords)) {
                        $keywords = is_array($cert->keywords)
                            ? $cert->keywords
                            : json_decode($cert->keywords, true);
                    }

                    // 🔁 Fallback si aún no tiene keywords
                    if (empty($keywords)) {
                        $keywords = [ strtolower($certName) ];
                    }

                    $this->line("\n🔎 Certificación: {$certName}");
                    $this->line("   🔑 Keywords: " . implode(', ', $keywords));

                    $found = [];
                    $new   = [];

                    foreach ($jobs as $job) {

                        $title   = $job['title'] ?? '';
                        $content = $hasContent ? ($job['content'] ?? '') : '';

                        $text = strtolower($title . ' ' . $content);

                        // 🔎 MATCH POR KEYWORDS
                        $matched = false;
                        foreach ($keywords as $kw) {
                            if ($kw && str_contains($text, strtolower($kw))) {
                                $matched = true;
                                break;
                            }
                        }

                        if (!$matched) {
                            continue;
                        }

                        $companyName = $job['company_name'] ?? ucfirst($companySlug);
                        $urlJob = $job['absolute_url'] ?? null;
                        $externalId = $job['id'];

                        // 📍 Ubicación cruda
                        $loc = strtolower($job['location']['name'] ?? '');

                        // Extraer país
                        $countryCode = $this->extractCountryCodeOrNull($loc);
                        if (!$countryCode) {
                            $this->stats['skipped']++;
                            continue;
                        }

                        $countryFull = CountryNormalizer::normalize($countryCode);

                        // Extraer ciudad
                        $cityRaw = $this->extractCity($loc);

                        // Coordenadas desde tabla cities
                        [$cityClean, $lat, $lng] = $this->getCoords($cityRaw, $countryCode);

                        if (!$lat || !$lng) {
                            $this->stats['skipped']++;
                            continue;
                        }

                        $modality = $this->detectModality($loc, $content);

                        // 🔁 Duplicados
                        $existing = JobOffer::where('source', 'Greenhouse')
                            ->where('external_id', $externalId)
                            ->first();

                        if ($existing) {
                            $existing->certifications()
                                ->syncWithoutDetaching([$certId]);
                            continue;
                        }

                        $region = RegionHelper::fromCountry($countryFull);

                        $offer = JobOffer::create([
                            'title'             => $title ?: 'N/A',
                            'company'           => $companyName,
                            'country'           => $countryFull,
                            'city'              => $cityClean,
                            'latitude'          => $lat,
                            'longitude'         => $lng,
                            'modality'          => $modality,
                            'experience_level'  => $this->extractExperience($content),
                            'education_level'   => $this->extractEducation($content),
                            'skills'            => $this->extractSkills($content),
                            'certifications'    => $certName, // inferida
                            'requirements'      => strip_tags($content),
                            'source'            => 'Greenhouse',
                            'external_id'       => $externalId,
                            'url'               => $urlJob,
                            'published_at'      => $job['updated_at'] ?? now(),
                            'region'            => $region,
                        ]);

                        $offer->certifications()
                            ->syncWithoutDetaching([$certId]);

                        $new[]   = $externalId;
                        $found[] = $externalId;
                    }

                    // 📊 Métrica diaria por certificación
                    CertificationMetric::updateOrCreate(
                        [
                            'certification_id' => $certId,
                            'run_date'         => now()->toDateString(),
                            'source'           => 'Greenhouse',
                        ],
                        [
                            'certification_name' => $certName,
                            'jobs_found_count'   => count($found),
                            'jobs_new_count'     => count($new),
                            'countries_breakdown'=> [],
                            'modality_breakdown' => [],
                        ]
                    );

                    $this->info("✔ {$certName}: " . count($new) . " nuevas");
                }

            } catch (\Throwable $e) {
                $this->error("⚠ Error Greenhouse {$companySlug}: {$e->getMessage()}");
                Log::error("Greenhouse error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("🎯 Finalizado");
        $this->line("   ⏭️ Skipped: {$this->stats['skipped']}");
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
            default => 'no_remote',
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
