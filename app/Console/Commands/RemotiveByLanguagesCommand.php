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
use App\Services\ScraperRunService;
 use App\Services\SourceStatusService;
class RemotiveByLanguagesCommand extends Command
{
    protected $signature = 'remotive:languages';
    protected $description = '🌎 Importa ofertas desde Remotive por lenguajes con geolocalización estricta.';

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
        'Remotive',
        'languages'
    );

    $source = 'remotive_languages';

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

        $lastLanguageId = LanguageMetric::where('source', 'Remotive')
            ->orderByDesc('created_at')
            ->value('language_id');

        $baseQuery = Language::whereIn('languages.id', function ($q) {
                $q->select('course_language.language_id')
                  ->from('course_language')
                  ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
            })
            ->orderBy('languages.id');

        $languagesQuery = clone $baseQuery;

        if ($lastLanguageId) {
            $languagesQuery->where('languages.id', '>', $lastLanguageId);
        }

        $languages = $languagesQuery->get();

        if ($languages->isEmpty()) {
            $languages = $baseQuery->get();
        }

        $this->info("🌎 Remotive → procesando {$languages->count()} lenguajes");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        foreach ($languages as $language) {

            $languageId   = $language->id;
            $languageName = $language->name;

            $this->warn("\n🔎 {$languageName}");

            $totalFound = 0;
            $totalNew   = 0;

            try {
                $response = Http::timeout(20)
                    ->get('https://remotive.com/api/remote-jobs', [
                        'search' => $languageName
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    SourceStatusService::connectionFailed($source, $languageName);
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
                        $existing->languages()->syncWithoutDetaching([$languageId]);
                        $totalSkippedAll++;
                        continue;
                    }

                    $title   = $job['title'] ?? 'N/A';
                    $company = $job['company_name'] ?? null;
                    $urlJob  = $job['url'] ?? null;
                    $desc    = strtolower($job['description'] ?? '');

                    $modality = $this->detectModality($job);
                    $isRemote = ($modality === 'remote');

                    $locationStr = $job['candidate_required_location'] ?? 'Unknown';
                    [$rawCity, $rawCountryRaw] = $this->extractLocation($locationStr);

                    $country = RemotiveCountry::normalize($rawCountryRaw);

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
                        'requirements'     => strip_tags($desc),
                        'source'           => 'Remotive',
                        'external_id'      => $externalId,
                        'url'              => $urlJob,
                        'search_query'     => $languageName,
                        'published_at'     => isset($job['publication_date'])
                            ? Carbon::parse($job['publication_date'])
                            : now(),
                        'region'           => $region,
                    ]);

                    $offer->languages()->syncWithoutDetaching([$languageId]);

                    $totalNew++;
                    $totalInsertedAll++;
                }

            } catch (\Throwable $e) {
                Log::error("❌ Remotive {$languageName}: {$e->getMessage()}");
                $totalSkippedAll++;
            }

            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $languageId,
                    'run_date'    => now()->toDateString(),
                    'source'      => 'Remotive',
                ],
                [
                    'language_name'    => $languageName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count'   => $totalNew,
                    'updated_at'       => now(),
                ]
            );

            $this->info("✔ {$languageName}: {$totalNew} nuevas / {$totalFound}");

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

        $this->info("\n🟢 Remotive finalizado correctamente");

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

    // =====================================================
    // HELPERS (SIN CAMBIOS FUNCIONALES)
    // =====================================================

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

    protected function extractExperience(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'senior') => 'senior',
            str_contains($text, 'mid')    => 'mid',
            str_contains($text, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'bachelor')  => 'bachelor',
            str_contains($text, 'master')    => 'master',
            str_contains($text, 'phd')       => 'phd',
            str_contains($text, 'technical') => 'technical',
            default => null,
        };
    }

    protected function extractCertifications(string $text): ?string
    {
        $certs = [];
        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $c) {
            if (str_contains($text, $c)) $certs[] = strtoupper($c);
        }
        return $certs ? implode(', ', $certs) : null;
    }

    protected function extractSkills(string $text): ?string
    {
        $out = [];
        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $s) {
            if (str_contains($text, $s)) $out[] = strtoupper($s);
        }
        return $out ? implode(', ', $out) : null;
    }
}
