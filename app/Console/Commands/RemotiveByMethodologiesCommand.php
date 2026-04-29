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
use App\Helpers\RemotiveCountry;
use App\Helpers\RegionMapper;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;
class RemotiveByMethodologiesCommand extends Command
{
    protected $signature = 'remotive:methodologies';
    protected $description = 'Importa ofertas desde Remotive por metodologías con geolocalización estricta.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

  public function handle()
{
    // ▶️ INICIAR RUN
    $run = ScraperRunService::start(
        $this->signature,
        'Remotive',
        'methodologies'
    );

    $source = 'remotive_methodologies';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://remotive.com/api/remote-jobs'
    );

    $connectionOk = false;
    $startedAt = now();

    SourceStatusService::progress($source, 0, 0, 0);

    try {

        /* =====================================================
           1️⃣ CURSOR – ÚLTIMA METODOLOGÍA
        ===================================================== */
        $lastMethodologyId = MethodologyMetric::where('source', 'Remotive')
            ->orderByDesc('created_at')
            ->value('methodology_id');

        /* =====================================================
           2️⃣ QUERY BASE (ISIL)
        ===================================================== */
        $baseQuery = Methodology::whereIn('methodologies.id', function ($q) {
                $q->select('course_methodology.methodology_id')
                  ->from('course_methodology')
                  ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
            })
            ->orderBy('methodologies.id');

        $query = clone $baseQuery;

        if ($lastMethodologyId) {
            $query->where('methodologies.id', '>', $lastMethodologyId);
        }

        $methodologies = $query->get();

        if ($methodologies->isEmpty()) {
            $methodologies = $baseQuery->get();
        }

        $this->info("🌎 Remotive → procesando {$methodologies->count()} metodologías");

        /* =====================================================
           3️⃣ CONTADORES GLOBALES
        ===================================================== */
        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        foreach ($methodologies as $methodology) {

            $methodologyId   = $methodology->id;
            $methodologyName = $methodology->name;

            $this->warn("\n🔎 {$methodologyName}");

            $totalFound = 0;
            $totalNew   = 0;

            try {
                $response = Http::timeout(20)
                    ->get('https://remotive.com/api/remote-jobs', [
                        'search' => $methodologyName
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    SourceStatusService::connectionFailed($source, $methodologyName);
                    $this->error("❌ Error API Remotive");
                    continue;
                }

                $connectionOk = true;

                $jobs = $response->json()['jobs'] ?? [];
                $totalFound = count($jobs);
                $totalFoundAll += $totalFound;

                foreach ($jobs as $job) {

                    $externalId = $job['id'] ?? null;

                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'Remotive')
                        ->first();

                    if ($existing) {
                        $existing->methodologies()->syncWithoutDetaching([$methodologyId]);
                        $totalSkippedAll++;
                        continue;
                    }

                    $title   = $job['title'] ?? 'N/A';
                    $company = $job['company_name'] ?? null;
                    $urlJob  = $job['url'] ?? null;
                    $desc    = strtolower(strip_tags($job['description'] ?? ''));

                    $modality = $this->detectModality($job);
                    $isRemote = ($modality === 'remote');

                    $location = $job['candidate_required_location'] ?? 'Unknown';
                    [$rawCity, $rawCountry] = $this->extractLocation($location);
                    $country = RemotiveCountry::normalize($rawCountry);

                    if ($isRemote) {
                        $finalCity = 'Remote';
                        $lat = $lng = null;
                    } else {
                        [$finalCity, $lat, $lng] = $this->tryGeocode($rawCity, $country);
                        if (!$lat || !$lng) {
                            $totalSkippedAll++;
                            continue;
                        }
                    }

                    $region = RegionMapper::resolve($country);

                    $offer = JobOffer::create([
                        'title'            => $title,
                        'company'          => $company,
                        'country'          => $country,
                        'city'             => $finalCity,
                        'latitude'         => $lat,
                        'longitude'        => $lng,
                        'modality'         => $modality,
                        'salary_min'       => $this->extractMinSalary($job['salary'] ?? ''),
                        'salary_max'       => $this->extractMaxSalary($job['salary'] ?? ''),
                        'experience_level' => $this->extractExperience($desc),
                        'education_level'  => $this->extractEducation($desc),
                        'certifications'   => $this->extractCertifications($desc),
                        'skills'           => $this->extractSkills($desc),
                        'requirements'     => $desc,
                        'source'           => 'Remotive',
                        'external_id'      => $externalId,
                        'url'              => $urlJob,
                        'search_query'     => $methodologyName,
                        'published_at'     => isset($job['publication_date'])
                            ? Carbon::parse($job['publication_date'])
                            : now(),
                        'region'           => $region,
                    ]);

                    $offer->methodologies()->syncWithoutDetaching([$methodologyId]);

                    $totalNew++;
                    $totalInsertedAll++;
                }

            } catch (\Throwable $e) {
                Log::error("❌ Remotive {$methodologyName}: ".$e->getMessage());
                $totalSkippedAll++;
            }

            MethodologyMetric::updateOrCreate(
                [
                    'methodology_id' => $methodologyId,
                    'run_date'       => now()->toDateString(),
                    'source'         => 'Remotive',
                ],
                [
                    'methodology_name' => $methodologyName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count'   => $totalNew,
                    'updated_at'       => now(),
                ]
            );

            $this->info("✔ {$methodologyName}: {$totalNew} nuevas / {$totalFound}");

            SourceStatusService::progress(
                $source,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );
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

        $this->info("\n🟢 Remotive (metodologías) finalizado correctamente");

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
    /* =====================================================
       HELPERS (SIN CAMBIOS)
    ===================================================== */

    protected function extractLocation(string $txt): array
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

        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])->first();

        if ($found) {
            $this->stats['mapped']++;
            return [$found->city, $found->lat, $found->lng];
        }

        try {
            $res = Http::withHeaders(['User-Agent' => 'Observatorio/1.0'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => "$city, $country",
                    'format' => 'json',
                    'limit' => 1,
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
        $cat   = strtolower($job['job_type'] ?? '');
        $title = strtolower($job['title'] ?? '');
        $desc  = strtolower($job['description'] ?? '');

        return match (true) {
            str_contains($cat, 'remote'),
            str_contains($title, 'remote'),
            str_contains($desc, 'remote') => 'remote',
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

    protected function extractExperience(string $txt): ?string
    {
        return match (true) {
            str_contains($txt, 'senior') => 'senior',
            str_contains($txt, 'mid')    => 'mid',
            str_contains($txt, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $txt): ?string
    {
        return match (true) {
            str_contains($txt, 'bachelor')  => 'bachelor',
            str_contains($txt, 'master')    => 'master',
            str_contains($txt, 'phd')       => 'phd',
            str_contains($txt, 'technical') => 'technical',
            default => null,
        };
    }

    protected function extractCertifications(string $txt): ?string
    {
        $certs = [];
        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $c) {
            if (str_contains($txt, $c)) $certs[] = strtoupper($c);
        }
        return $certs ? implode(', ', $certs) : null;
    }

    protected function extractSkills(string $txt): ?string
    {
        $skills = [];
        foreach (['agile','scrum','kanban','jira','confluence','lean','devops','github','git'] as $k) {
            if (str_contains($txt, $k)) $skills[] = strtoupper($k);
        }
        return $skills ? implode(', ', $skills) : null;
    }
}
