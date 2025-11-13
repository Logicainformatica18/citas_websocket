<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\JoobleLocation;
use App\Helpers\CountryNormalizer;

class JoobleByMethodologiesCommand extends Command
{
    protected $signature = 'jooble:methodologies {--country=United States} {--pages=5}';
    protected $description = 'Importa ofertas desde Jooble por metodología con geolocalización estricta.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    public function handle()
    {
        $apiKey = config('services.jooble.key');
        if (!$apiKey) {
            $this->error("❌ No existe JOOBLE_API_KEY en .env");
            return;
        }

        $joobleCountry = JoobleLocation::normalize($this->option('country'));
        $pages = (int)$this->option('pages');

        // 🔍 obtener metodologías con vínculo académico real
        $methodologies = Methodology::whereIn('id', function ($q) {
            $q->select('course_methodology.methodology_id')
              ->from('course_methodology')
              ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->pluck('name', 'id');

        $this->info("🧭 Importando Jooble por metodologías ({$joobleCountry})…");

        foreach ($methodologies as $methodologyId => $methodologyName) {

            $this->warn("🔎 Procesando metodología: {$methodologyName}");

            $totalFound = $totalNew = $totalDuplicates = 0;

            for ($page = 1; $page <= $pages; $page++) {

                $payload = [
                    "keywords" => $methodologyName,
                    "location" => $joobleCountry,
                    "page"     => $page
                ];

                try {
                    $response = Http::timeout(25)->post(
                        "https://jooble.org/api/{$apiKey}",
                        $payload
                    );

                    if ($response->failed()) {
                        $this->error("❌ Error página {$page}: {$response->body()}");
                        continue;
                    }

                    $jobs = $response->json()['jobs'] ?? [];
                    $totalFound += count($jobs);

                    foreach ($jobs as $job) {

                        $title      = $job['title'] ?? 'N/A';
                        $company    = $job['company'] ?? null;
                        $location   = $job['location'] ?? $joobleCountry;
                        $desc       = strtolower($job['snippet'] ?? '');
                        $urlJob     = $job['link'] ?? null;
                        $externalId = $job['id'];

                        // -----------------------
                        // MODALIDAD
                        // -----------------------
                        $modality = $this->detectModality($location, $desc);
                        $isRemote = in_array($modality, ['remote', 'fully_remote']);

                        // -----------------------
                        // LOCATION
                        // -----------------------
                        [$rawCity, $rawCountry] = $this->splitLocation($location);

                        if (strlen($rawCountry) == 2 && ctype_alpha($rawCountry)) {
                            $rawCountry = "United States"; // OH, CA, TX...
                        }

                        if ($rawCountry === 'Unknown' || !$rawCountry) {
                            $rawCountry = $joobleCountry;
                        }

                        $countryFull = CountryNormalizer::normalize($rawCountry);
                        $countryCode = $this->countryCodeIso($rawCountry);

                        // -----------------------
                        // GEOLOCALIZACIÓN
                        // -----------------------
                        if ($isRemote) {
                            // ✔ regla exacta pedida
                            $finalCity = "Remote";
                            $lat = null;
                            $lng = null;
                        } else {
                            [$finalCity, $lat, $lng] = $this->tryGeocode($rawCity, $countryCode);

                            // ❌ descartar ofertas sin lat/lng
                            if (!$lat || !$lng) {
                                $this->stats['skipped']++;
                                continue;
                            }
                        }

                        // -----------------------
                        // DUPLICADOS
                        // -----------------------
                        $existing = JobOffer::where('external_id', $externalId)->first();
                        if ($existing) {
                            $existing->methodologies()->syncWithoutDetaching([$methodologyId]);
                            $totalDuplicates++;
                            continue;
                        }

                        $region = RegionHelper::fromCountry($countryFull);

                        // -----------------------
                        // CREAR
                        // -----------------------
                        $offer = JobOffer::create([
                            'title'             => $title,
                            'company'           => $company,
                            'country'           => $countryFull,
                            'city'              => $finalCity,
                            'latitude'          => $lat,
                            'longitude'         => $lng,
                            'modality'          => $modality,
                            'salary_min'        => $this->extractMinSalary($job['salary'] ?? ''),
                            'salary_max'        => $this->extractMaxSalary($job['salary'] ?? ''),
                            'source'            => 'Jooble',
                            'compensation_type' => $job['type'] ?? null,
                            'experience_level'  => $this->extractExperience($desc),
                            'education_level'   => $this->extractEducation($desc),
                            'certifications'    => $this->extractCertifications($desc),
                            'skills'            => $this->extractSkills($desc),
                            'requirements'      => strip_tags($desc),
                            'external_id'       => $externalId,
                            'url'               => $urlJob,
                            'search_query'      => $methodologyName,
                            'published_at'      => Carbon::parse($job['updated'] ?? now()),
                            'region'            => $region,
                        ]);

                        $offer->methodologies()->syncWithoutDetaching([$methodologyId]);

                        $totalNew++;
                    }

                    sleep(1);

                } catch (\Throwable $e) {
                    Log::error("⚠️ Error {$methodologyName}: " . $e->getMessage());
                }
            }

            // -----------------------
            // MÉTRICAS
            // -----------------------
            $today = now()->toDateString();
            if (!MethodologyMetric::whereDate('run_date', $today)
                ->where('methodology_id', $methodologyId)
                ->where('source', 'Jooble')
                ->exists()) {

                MethodologyMetric::create([
                    'methodology_id'    => $methodologyId,
                    'methodology_name'  => $methodologyName,
                    'jobs_found_count'  => $totalFound,
                    'jobs_new_count'    => $totalNew,
                    'run_date'          => $today,
                    'source'            => 'Jooble',
                ]);
            }

            $this->info("✅ {$methodologyName}: {$totalNew} nuevas / {$totalFound} encontradas");
        }

        $this->info("🎯 Proceso completado");
        $this->line("🛰️ API hits: {$this->stats['api_hits']}");
        $this->line("🗺️ Mapeadas: {$this->stats['mapped']}");
        $this->line("⏭️ Skipped: {$this->stats['skipped']}");
    }

    // --------------------------------------
    // HELPERS
    // --------------------------------------

    protected function splitLocation(string $location): array
    {
        $parts = array_map('trim', explode(',', $location));
        return [$parts[0] ?? 'Unknown', $parts[1] ?? 'Unknown'];
    }

    protected function tryGeocode(?string $city, ?string $countryCode)
    {
        if (!$city || strtolower($city) === 'unknown') {
            return [null, null, null];
        }

        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)])
            ->first();

        if ($found) {
            $this->stats['mapped']++;
            return [$found->city, $found->lat, $found->lng];
        }

        try {
            $res = Http::withHeaders(['User-Agent' => 'Observatorio/1.0'])
                ->timeout(10)
                ->get("https://nominatim.openstreetmap.org/search", [
                    'q' => "$city, $countryCode",
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

    protected function detectModality(string $location, string $desc): string
    {
        $txt = strtolower($location . ' ' . $desc);

        return match (true) {
            str_contains($txt, 'fully remote') => 'fully_remote',
            str_contains($txt, 'remote') => 'remote',
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

    protected function countryCodeIso(string $country): string
    {
        return match (strtolower($country)) {
            'united states' => 'US',
            'india' => 'IN',
            'united kingdom' => 'GB',
            'germany' => 'DE',
            'spain' => 'ES',
            'canada' => 'CA',
            'italy' => 'IT',
            'mexico' => 'MX',
            default => strtoupper(substr($country, 0, 2)),
        };
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
        foreach (['python', 'java', 'php', 'laravel', 'react', 'sql', 'docker', 'aws', 'git', 'node'] as $s) {
            if (str_contains($text, $s)) $skills[] = strtoupper($s);
        }
        return $skills ? implode(', ', $skills) : null;
    }
}
