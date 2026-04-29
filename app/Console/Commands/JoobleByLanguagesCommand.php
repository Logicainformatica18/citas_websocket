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
use App\Helpers\RegionHelper;
use App\Helpers\JoobleLocation;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;
 use App\Services\SourceStatusService;



class JoobleByLanguagesCommand extends Command
{
    protected $signature = 'jooble:languages {--country=United States} {--pages=5}';
    protected $description = 'Importa ofertas desde Jooble con geolocalización estricta.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

public function handle()
{
    // ▶️ INICIAR RUN
    $run = ScraperRunService::start(
        $this->signature,
        'Jooble',
        'languages'
    );

    $source = 'jooble_languages';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://jooble.org/api'
    );

    $connectionOk = false;
    $startedAt = now();

    SourceStatusService::progress($source, 0, 0, 0);

    try {

        $apiKey = config('services.jooble.key');

        if (!$apiKey) {
            throw new \Exception('Missing Jooble API Key');
        }

        $joobleCountry = JoobleLocation::normalize($this->option('country'));
        $pages = (int) $this->option('pages');

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        // 🔁 MISMA lógica (NO optimizada)
        $languages = Language::pluck('name', 'id');

        $this->info("🌎 Jooble ({$joobleCountry})...");

        foreach ($languages as $languageId => $languageName) {

            $this->warn("🔎 {$languageName}");

            $totalFound = 0;
            $totalNew   = 0;

            for ($page = 1; $page <= $pages; $page++) {

                $payload = [
                    "keywords" => $languageName,
                    "location" => $joobleCountry,
                    "page"     => $page
                ];

                try {

                    $response = Http::timeout(25)
                        ->post("https://jooble.org/api/{$apiKey}", $payload);

                    if ($response->failed()) {
                        SourceStatusService::connectionFailed($source, "{$languageName} page {$page}");
                        $totalSkippedAll++;
                        continue;
                    }

                    $connectionOk = true;

                    $jobs = $response->json()['jobs'] ?? [];
                    $totalFound += count($jobs);

                    foreach ($jobs as $job) {

                        $externalId = $job['id'] ?? null;

                        if ($externalId && JobOffer::where('external_id', $externalId)->exists()) {
                            $totalSkippedAll++;
                            continue;
                        }

                        $title    = $job['title'] ?? 'N/A';
                        $company  = $job['company'] ?? null;
                        $location = $job['location'] ?? $joobleCountry;
                        $desc     = strtolower($job['snippet'] ?? '');
                        $urlJob   = $job['link'] ?? null;

                        $modality = $this->detectModality($location, $desc);

                        [$rawCity, $rawCountry] = $this->splitLocation($location);

                        if (!$rawCountry || $rawCountry === 'Unknown') {
                            $rawCountry = $joobleCountry;
                        }

                        $countryFull = CountryNormalizer::normalize($rawCountry);
                        $countryCode = $this->countryCodeIso($rawCountry);

                        [$city, $lat, $lng] = $this->tryGeocode($rawCity, $countryCode);

                        if (!$lat || !$lng) {
                            $totalSkippedAll++;
                            continue;
                        }

                        $offer = JobOffer::create([
                            'title'        => $title,
                            'company'      => $company,
                            'country'      => $countryFull,
                            'city'         => $city,
                            'latitude'     => $lat,
                            'longitude'    => $lng,
                            'modality'     => $modality,
                            'salary_min'   => $this->extractMinSalary($job['salary'] ?? ''),
                            'salary_max'   => $this->extractMaxSalary($job['salary'] ?? ''),
                            'source'       => 'Jooble',
                            'external_id'  => $externalId,
                            'url'          => $urlJob,
                            'search_query' => $languageName,
                            'published_at' => Carbon::parse($job['updated'] ?? now()),
                            'region'       => RegionHelper::fromCountry($countryFull),
                        ]);

                        $offer->languages()->syncWithoutDetaching([$languageId]);

                        $totalNew++;
                        $totalInsertedAll++;
                    }

                    SourceStatusService::progress(
                        $source,
                        $totalFoundAll + $totalFound,
                        $totalInsertedAll,
                        $totalSkippedAll
                    );

                    sleep(1);

                } catch (\Throwable $e) {
                    Log::error("Jooble {$languageName}: {$e->getMessage()}");
                    $totalSkippedAll++;
                }
            }

            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $languageId,
                    'run_date'    => now()->toDateString(),
                    'source'      => 'Jooble',
                ],
                [
                    'language_name'    => $languageName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count'   => $totalNew,
                    'updated_at'       => now(),
                ]
            );

            $this->info("✔ {$languageName}: {$totalNew} nuevas / {$totalFound}");

            $totalFoundAll += $totalFound;
        }

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

        $this->info("🎯 Jooble finalizado correctamente");

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
    // --------------------------------------
    // HELPERS
    // --------------------------------------

    protected function splitLocation(string $location): array
    {
        $parts = array_map('trim', explode(',', $location));
        return [
            $parts[0] ?? 'Unknown',
            $parts[1] ?? 'Unknown',
        ];
    }

    protected function tryGeocode(?string $city, ?string $countryCode)
    {
        if (!$city || strtolower($city) === 'unknown') {
            return [null, null, null];
        }

        // 1. Buscar en tabla Cities
        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)])
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
    $text = strtolower($location . ' ' . $desc);

    return match (true) {

        // 🌍 REMOTO (todas las variantes)
        str_contains($text, 'fully remote'),
        str_contains($text, 'remote worldwide'),
        str_contains($text, 'work from anywhere'),
        str_contains($text, 'anywhere'),
        str_contains($text, 'remote'),
        str_contains($text, 'teletrabajo'),
        str_contains($text, 'work from home'),
        str_contains($text, 'home office')
            => 'remote',

        // 🏠 HÍBRIDO
        str_contains($text, 'hybrid'),
        str_contains($text, 'híbrido'),
        str_contains($text, 'mixto'),
        str_contains($text, 'partial remote')
            => 'hybrid',

        // 🏢 PRESENCIAL
        str_contains($text, 'onsite'),
        str_contains($text, 'on-site'),
        str_contains($text, 'office'),
        str_contains($text, 'presencial'),
        str_contains($text, 'in office')
            => 'presencial',

        // ❓ NO PRECISA
        default => 'no_precisa',
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
