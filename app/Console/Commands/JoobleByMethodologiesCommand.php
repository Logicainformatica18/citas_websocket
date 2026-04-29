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
use App\Services\ScraperRunService;
 use App\Services\SourceStatusService;
 
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
    // ▶️ INICIAR RUN
    $run = ScraperRunService::start(
        $this->signature,
        'Jooble',
        'methodologies'
    );

    $source = 'jooble_methodologies';

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

            ScraperRunService::failed($run, new \Exception("No existe JOOBLE_API_KEY"));

            SourceStatusService::failed(
                source: $source,
                runId: $run->id,
                e: new \Exception("No existe JOOBLE_API_KEY"),
                durationSeconds: now()->diffInSeconds($startedAt)
            );

            return;
        }

        $joobleCountry = JoobleLocation::normalize($this->option('country'));
        $pages = (int) $this->option('pages');

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        $lastMethodologyId = MethodologyMetric::where('source', 'Jooble')
            ->orderByDesc('created_at')
            ->value('methodology_id');

        $baseQuery = Methodology::whereIn('methodologies.id', function ($q) {
            $q->select('course_methodology.methodology_id')
              ->from('course_methodology')
              ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->orderBy('methodologies.id');

        $methodologiesQuery = clone $baseQuery;

        if ($lastMethodologyId) {
            $methodologiesQuery->where('methodologies.id', '>', $lastMethodologyId);
        }

        $methodologies = $methodologiesQuery->get();

        if ($methodologies->isEmpty()) {
            $methodologies = $baseQuery->get();
        }

        $this->info("🧭 Jooble ({$joobleCountry}) → {$methodologies->count()} metodologías");

        foreach ($methodologies as $methodology) {

            $methodologyId   = $methodology->id;
            $methodologyName = $methodology->name;

            $this->warn("🔎 {$methodologyName}");

            $totalFound = 0;
            $totalNew   = 0;

            for ($page = 1; $page <= $pages; $page++) {

                $payload = [
                    'keywords' => $methodologyName,
                    'location' => $joobleCountry,
                ];

                try {
                    $response = Http::timeout(25)
                        ->post("https://jooble.org/api/{$apiKey}", $payload);

                    if ($response->failed()) {
                        SourceStatusService::connectionFailed($source, "Error {$methodologyName} page {$page}");
                        $totalSkippedAll++;
                        continue;
                    }

                    $connectionOk = true;

                    $jobs = $response->json('jobs') ?? [];
                    $totalFound += count($jobs);

                    foreach ($jobs as $job) {

                        $externalId = $job['id'];

                        $existing = JobOffer::where('external_id', $externalId)
                            ->where('source', 'Jooble')
                            ->first();

                        if ($existing) {
                            $existing->methodologies()
                                ->syncWithoutDetaching([$methodologyId]);
                            $totalSkippedAll++;
                            continue;
                        }

                        $title    = $job['title'] ?? 'N/A';
                        $company  = $job['company'] ?? null;
                        $location = $job['location'] ?? $joobleCountry;
                        $desc     = strtolower($job['snippet'] ?? '');
                        $urlJob   = $job['link'] ?? null;

                        $modality = $this->detectModality($location, $desc);
                        $isRemote = in_array($modality, ['remote', 'fully_remote']);

                        [$rawCity, $rawCountry] = $this->splitLocation($location);

                        if (strlen($rawCountry) === 2 && ctype_alpha($rawCountry)) {
                            $rawCountry = 'United States';
                        }

                        if (!$rawCountry || $rawCountry === 'Unknown') {
                            $rawCountry = $joobleCountry;
                        }

                        $countryFull = CountryNormalizer::normalize($rawCountry);
                        $countryCode = $this->countryCodeIso($rawCountry);

                        if ($isRemote) {
                            $finalCity = 'Remote';
                            $lat = null;
                            $lng = null;
                        } else {
                            [$finalCity, $lat, $lng] = $this->tryGeocode($rawCity, $countryCode);

                            if (!$lat || !$lng) {
                                $totalSkippedAll++;
                                continue;
                            }
                        }

                        $region = RegionHelper::fromCountry($countryFull);

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

                        $offer->methodologies()
                            ->syncWithoutDetaching([$methodologyId]);

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
                    Log::error("Jooble {$methodologyName}: {$e->getMessage()}");
                    $totalSkippedAll++;
                }
            }

            MethodologyMetric::updateOrCreate(
                [
                    'methodology_id' => $methodologyId,
                    'run_date'       => now()->toDateString(),
                    'source'         => 'Jooble',
                ],
                [
                    'methodology_name' => $methodologyName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count'   => $totalNew,
                ]
            );

            $totalFoundAll += $totalFound;

            $this->info("✔ {$methodologyName}: {$totalNew} nuevas / {$totalFound}");
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

        // 🌍 Remoto global / sin país
        str_contains($txt, 'fully remote'),
        str_contains($txt, 'remote worldwide'),
        str_contains($txt, 'work from anywhere'),
        str_contains($txt, 'anywhere') 
            => 'fully_remote',

        // 🏠 Remoto con referencia local
        str_contains($txt, 'remote'),
        str_contains($txt, 'work from home'),
        str_contains($txt, 'teletrabajo'),
        str_contains($txt, 'home office')
            => 'remote',

        // 🧩 Híbrido
        str_contains($txt, 'hybrid'),
        str_contains($txt, 'híbrido'),
        str_contains($txt, 'mixto')
            => 'hybrid',

        // 🏢 Presencial explícito
        str_contains($txt, 'onsite'),
        str_contains($txt, 'on-site'),
        str_contains($txt, 'office'),
        str_contains($txt, 'presencial')
            => 'presencial',

        // ⚪ No se puede inferir
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
