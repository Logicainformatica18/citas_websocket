<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RemotiveCountry;
use App\Helpers\RegionMapper;

class RemotiveByLanguagesCommand extends Command
{
    protected $signature = 'remotive:languages';
    protected $description = 'Importa ofertas desde Remotive por lenguajes, con geolocalización estricta.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    public function handle()
    {
        $languages = Language::pluck('name', 'id');

        $this->info("🌎 Importando desde Remotive para {$languages->count()} lenguajes…");

        foreach ($languages as $languageId => $languageName) {

            $this->warn("\n🔎 Procesando lenguaje: {$languageName}");

            $totalFound = $totalNew = $totalDuplicates = 0;

            try {
                $response = Http::timeout(20)
                    ->get("https://remotive.com/api/remote-jobs", [
                        'search' => $languageName
                    ]);

                if ($response->failed()) {
                    $this->error("❌ Error API Remotive");
                    continue;
                }

                $jobs = $response->json()['jobs'] ?? [];
                $totalFound = count($jobs);

                foreach ($jobs as $job) {

                    $title      = $job['title'] ?? 'N/A';
                    $company    = $job['company_name'] ?? null;
                    $desc       = strtolower($job['description'] ?? '');
                    $urlJob     = $job['url'] ?? null;
                    $externalId = $job['id'] ?? null;

                    // -----------------------
                    // MODALIDAD REAL
                    // -----------------------
                    $modality = $this->detectModality($job);
                    $isRemote = ($modality === 'remote');

                    // -----------------------
                    // LOCATION REMOTIVE
                    // -----------------------
                    $location = $job['candidate_required_location'] ?? 'Unknown';

                    [$rawCity, $rawCountryRaw] = $this->extractRemotiveLocation($location);

                    // Normalizar país usando helper especializado
                    $rawCountry = RemotiveCountry::normalize($rawCountryRaw);

                    // -----------------------
                    // GEOLOCALIZACIÓN
                    // -----------------------
                    if ($isRemote) {
                        $finalCity = "Remote";
                        $lat = null;
                        $lng = null;
                    } else {
                        [$finalCity, $lat, $lng] = $this->tryGeocode($rawCity, $rawCountry);

                        // ❌ Si no es remote y NO hay geo → descartar
                        if (!$lat || !$lng) {
                            $this->stats['skipped']++;
                            continue;
                        }
                    }

                    // -----------------------
                    // DUPLICADOS
                    // -----------------------
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'Remotive')
                        ->first();

                    if ($existing) {
                        $existing->languages()->syncWithoutDetaching([$languageId]);
                        $totalDuplicates++;
                        continue;
                    }

                    // -----------------------
                    // REGIÓN (nuevo helper)
                    // -----------------------
                    $region = RegionMapper::resolve($rawCountry);

                    // -----------------------
                    // CREAR OFERTA
                    // -----------------------
                    $offer = JobOffer::create([
                        'title'             => $title,
                        'company'           => $company,
                        'country'           => $rawCountry,
                        'city'              => $finalCity,
                        'latitude'          => $lat,
                        'longitude'         => $lng,
                        'modality'          => $modality,
                        'salary_min'        => $this->extractMinSalary($job['salary'] ?? ''),
                        'salary_max'        => $this->extractMaxSalary($job['salary'] ?? ''),
                        'source'            => 'Remotive',
                        'compensation_type' => null,
                        'experience_level'  => $this->extractExperience($desc),
                        'education_level'   => $this->extractEducation($desc),
                        'certifications'    => $this->extractCertifications($desc),
                        'skills'            => $this->extractSkills($desc),
                        'requirements'      => strip_tags($desc),
                        'external_id'       => $externalId,
                        'url'               => $urlJob,
                        'search_query'      => $languageName,
                        'published_at'      => now(),
                        'region'            => $region,
                    ]);

                    $offer->languages()->syncWithoutDetaching([$languageId]);

                    $totalNew++;
                }

            } catch (\Throwable $e) {
                Log::error("❌ Error Remotive {$languageName}: ".$e->getMessage());
            }

            // -----------------------
            // MÉTRICAS DIARIAS
            // -----------------------
            $today = now()->toDateString();

            if (!LanguageMetric::whereDate('run_date', $today)
                    ->where('language_id', $languageId)
                    ->where('source', 'Remotive')
                    ->exists())
            {
                LanguageMetric::create([
                    'language_id'       => $languageId,
                    'language_name'     => $languageName,
                    'jobs_found_count'  => $totalFound,
                    'jobs_new_count'    => $totalNew,
                    'run_date'          => $today,
                    'source'            => 'Remotive',
                ]);
            }

            $this->info("✅ {$languageName}: {$totalNew} nuevas / {$totalFound} encontradas");
        }

        $this->line("\n🎯 Proceso completado:");
        $this->line("🛰️ API hits: {$this->stats['api_hits']}");
        $this->line("🗺️ Mapeadas: {$this->stats['mapped']}");
        $this->line("⏭️ Skipped: {$this->stats['skipped']}");
    }

    // ---------------------------
    // HELPERS REMOTIVE
    // ---------------------------

    protected function extractRemotiveLocation(string $txt): array
    {
        if (str_contains($txt, ',')) {
            [$city, $country] = array_map('trim', explode(',', $txt));
        } else {
            $city = $txt;
            $country = $txt;
        }

        return [$city, $country];
    }

    protected function tryGeocode(?string $city, ?string $country)
    {
        if (!$city || !$country || strtolower($city) === 'remote') {
            return [null, null, null];
        }

        // 1. Tabla cities
        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->first();

        if ($found) {
            $this->stats['mapped']++;
            return [$found->city, $found->lat, $found->lng];
        }

        // 2. Nominatim
        try {
            $res = Http::withHeaders(['User-Agent' => 'Observatorio/1.0'])
                ->timeout(10)
                ->get("https://nominatim.openstreetmap.org/search", [
                    'q' => "$city, $country",
                    'format' => 'json',
                    'limit' => 1
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $this->stats['api_hits']++;
                $d = $res->json()[0];
                return [$city, (float)$d['lat'], (float)$d['lon']];
            }
        } catch (\Throwable $e) {}

        return [null, null, null];
    }

    protected function detectModality(array $job): string
    {
        $cat = strtolower($job['job_type'] ?? '');
        $title = strtolower($job['title'] ?? '');
        $desc = strtolower($job['description'] ?? '');

        return match (true) {
            str_contains($cat, 'remote'),
            str_contains($title, 'remote'),
            str_contains($desc, 'remote') => 'remote',
            default => 'no_remote',
        };
    }

    protected function extractMinSalary(string $salary): ?float
    {
        preg_match('/(\d+)/', $salary, $m);
        return $m[1] ?? null;
    }

    protected function extractMaxSalary(string $salary): ?float
    {
        preg_match_all('/(\d+)/', $salary, $m);
        return $m[1][1] ?? ($m[1][0] ?? null);
    }

    protected function extractExperience(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'senior') => 'senior',
            str_contains($text, 'mid') => 'mid',
            str_contains($text, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'bachelor') => 'bachelor',
            str_contains($text, 'master') => 'master',
            str_contains($text, 'phd') => 'phd',
            str_contains($text, 'technical') => 'technical',
            default => null,
        };
    }

    protected function extractCertifications(string $text): ?string
    {
        $certs = [];
        foreach (['aws', 'azure', 'google cloud', 'scrum', 'pmp', 'cisco', 'ccna', 'itil'] as $c) {
            if (str_contains($text, $c)) $certs[] = strtoupper($c);
        }
        return $certs ? implode(', ', $certs) : null;
    }

    protected function extractSkills(string $text): ?string
    {
        $skills = [];
        foreach (['python', 'java', 'php', 'laravel', 'react', 'vue', 'sql', 'docker', 'aws', 'git', 'node'] as $s) {
            if (str_contains($text, $s)) $skills[] = strtoupper($s);
        }
        return $skills ? implode(', ', $skills) : null;
    }
}
