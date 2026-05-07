<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\JoobleLocation;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;
class JoobleByCertificationsCommand extends Command
{
    protected $signature = 'jooble:certifications {--country=United States} {--pages=5}';

    protected $description = '🏅 Importa ofertas desde Jooble por certificación (geolocalización estricta).';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

  public function handle()
{
    $apiKey = config('services.jooble.key');

    if (!$apiKey) {

        $this->error(
            "❌ No existe JOOBLE_API_KEY en .env"
        );

        return;
    }

    $baseUrl = "https://jooble.org/api";

    $run = ScraperRunService::start(
        $this->signature,
        'Jooble',
        'certifications'
    );

    $source = 'jooble_certifications';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [
            'country' => $this->option('country'),
            'pages'   => $this->option('pages'),
        ],
        apiUrl: $baseUrl
    );

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    $connectionOk = false;
    $startedAt = now();

    try {

        $joobleCountry =
            JoobleLocation::normalize(
                $this->option('country')
            );

        $pages =
            (int) $this->option('pages');

        // ✅ certificaciones
        $certifications = Certification::where('enabled', 1)
            ->pluck('name', 'id');

        $this->info(
            "🌎 Importando Jooble ({$joobleCountry}) para {$certifications->count()} certificaciones"
        );

        foreach ($certifications as $certId => $certificationName) {

            $this->warn(
                "🔎 Procesando certificación: {$certificationName}"
            );

            $totalFound = 0;
            $totalNew   = 0;
            $totalDuplicates = 0;

            $countries  = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {

                $payload = [
                    "keywords" =>
                        $certificationName,

                    "location" =>
                        $joobleCountry,

                    "page" =>
                        $page
                ];

                try {

                    $response = Http::retry(3, 2000)
                        ->timeout(25)
                        ->post(
                            "https://jooble.org/api/{$apiKey}",
                            $payload
                        );

                } catch (\Throwable $e) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "Connection exception page {$page}"
                    );

                    Log::error($e);

                    continue;
                }

                if ($response->failed()) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "HTTP failed page {$page}"
                    );

                    $this->error(
                        "❌ Página {$page}: {$response->body()}"
                    );

                    continue;
                }

                $connectionOk = true;

                $jobs = $response->json()['jobs'] ?? [];

                if (empty($jobs)) {
                    continue;
                }

                $totalFound += count($jobs);

                // ✅ evitar N+1
                $existingIds = JobOffer::whereIn(
                    'external_id',
                    collect($jobs)->pluck('id')->filter()
                )
                ->pluck('id', 'external_id');

                foreach ($jobs as $job) {

                    try {

                        $title =
                            $job['title'] ?? 'N/A';

                        $company =
                            $job['company'] ?? null;

                        $location =
                            $job['location']
                            ?? $joobleCountry;

                        $desc = strtolower(
                            $job['snippet'] ?? ''
                        );

                        $urlJob =
                            $job['link'] ?? null;

                        $externalId =
                            $job['id'] ?? null;

                        if (!$externalId) {

                            $totalSkippedAll++;

                            continue;
                        }

                        // -----------------------
                        // MODALIDAD
                        // -----------------------

                        $modality =
                            $this->detectModality(
                                $location,
                                $desc
                            );

                        $isRemote =
                            in_array(
                                $modality,
                                ['remote', 'fully_remote']
                            );

                        // -----------------------
                        // LOCATION
                        // -----------------------

                        [$rawCity, $rawCountry] =
                            $this->splitLocation(
                                $location
                            );

                        if (
                            strlen($rawCountry) === 2 &&
                            ctype_alpha($rawCountry)
                        ) {

                            $rawCountry =
                                "United States";
                        }

                        if (
                            !$rawCountry ||
                            $rawCountry === 'Unknown'
                        ) {

                            $rawCountry =
                                $joobleCountry;
                        }

                        $countryFull =
                            CountryNormalizer::normalize(
                                $rawCountry
                            );

                        $countryCode =
                            $this->countryCodeIso(
                                $rawCountry
                            );

                        // -----------------------
                        // GEO
                        // -----------------------

                        if ($isRemote) {

                            $finalCity = "Remote";

                            $lat = $lng = null;

                        } else {

                            [$finalCity, $lat, $lng] =
                                $this->tryGeocode(
                                    $rawCity,
                                    $countryCode
                                );

                            if (!$lat || !$lng) {

                                $this->stats['skipped']++;
                                $totalSkippedAll++;

                                continue;
                            }
                        }

                        $countries[$countryFull] =
                            ($countries[$countryFull] ?? 0) + 1;

                        $modalities[$modality] =
                            ($modalities[$modality] ?? 0) + 1;

                        // -----------------------
                        // DUPLICADOS
                        // -----------------------

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

                            $totalDuplicates++;

                            continue;
                        }

                        $region =
                            RegionHelper::fromCountry(
                                $countryFull
                            );

                        // -----------------------
                        // CREATE
                        // -----------------------

                        $offer = JobOffer::create([

                            'title' =>
                                $title,

                            'company' =>
                                $company,

                            'country' =>
                                $countryFull,

                            'city' =>
                                $finalCity,

                            'latitude' =>
                                $lat,

                            'longitude' =>
                                $lng,

                            'modality' =>
                                $modality,

                            'salary_min' =>
                                $this->extractMinSalary(
                                    $job['salary'] ?? ''
                                ),

                            'salary_max' =>
                                $this->extractMaxSalary(
                                    $job['salary'] ?? ''
                                ),

                            'source' =>
                                'Jooble',

                            'compensation_type' =>
                                $job['type'] ?? null,

                            'experience_level' =>
                                $this->extractExperience(
                                    $desc
                                ),

                            'education_level' =>
                                $this->extractEducation(
                                    $desc
                                ),

                            'certifications' =>
                                $this->extractCertifications(
                                    $desc
                                ),

                            'skills' =>
                                $this->extractSkills(
                                    $desc
                                ),

                            'requirements' =>
                                strip_tags($desc),

                            'external_id' =>
                                $externalId,

                            'url' =>
                                $urlJob,

                            'search_query' =>
                                $certificationName,

                            'published_at' =>
                                Carbon::parse(
                                    $job['updated'] ?? now()
                                ),

                            'region' =>
                                $region,
                        ]);

                        $offer->certifications()
                            ->syncWithoutDetaching([
                                $certId
                            ]);

                        $totalNew++;

                    } catch (\Throwable $e) {

                        $this->stats['skipped']++;
                        $totalSkippedAll++;

                        Log::error(
                            "❌ Jooble {$certificationName}: {$e->getMessage()}"
                        );
                    }
                }

                sleep(1);
            }

            /* =====================================================
               📊 ACUMULADOS
            ===================================================== */

            $totalFoundAll += $totalFound;
            $totalInsertedAll += $totalNew;
            $totalSkippedAll += $totalDuplicates;

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
               📊 MÉTRICAS
            ===================================================== */

            CertificationMetric::updateOrCreate(
                [
                    'certification_id' => $certId,
                    'run_date'         => now()->toDateString(),
                    'source'           => 'Jooble',
                ],
                [
                    'certification_name' =>
                        $certificationName,

                    'jobs_found_count' =>
                        $totalFound,

                    'jobs_new_count' =>
                        $totalNew,
                ]
            );

            $this->info(
                "✅ {$certificationName}: {$totalNew} nuevas / {$totalFound} encontradas"
            );
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

        $this->info(
            "🎯 Proceso Jooble certificaciones completado"
        );

        $this->line(
            "🛰️ API hits: {$this->stats['api_hits']}"
        );

        $this->line(
            "🗺️ Mapeadas: {$this->stats['mapped']}"
        );

        $this->line(
            "⏭️ Skipped: {$this->stats['skipped']}"
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

    /* ================= HELPERS (IGUALES) ================= */

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
