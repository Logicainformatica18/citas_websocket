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
use App\Helpers\RemotiveCountry;
use App\Helpers\RegionMapper;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;
class RemotiveByCertificationsCommand extends Command
{
    protected $signature = 'remotive:certifications';

    protected $description = 'Importa ofertas desde Remotive por certificaciones usando keywordss desde BD.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

   public function handle()
{
    $baseUrl = 'https://remotive.com/api/remote-jobs';

    $run = ScraperRunService::start(
        $this->signature,
        'Remotive',
        'certifications'
    );

    $source = 'remotive_certifications';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: $baseUrl
    );

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    $connectionOk = false;
    $startedAt = now();

    try {

        /**
         * ✅ Certificaciones con keywords
         */
        $certifications = Certification::select(
            'id',
            'name',
            'keywords'
        )->get();

        if ($certifications->isEmpty()) {

            $this->error(
                '❌ No hay certificaciones'
            );

            return;
        }

        $this->info(
            "🌎 Importando desde Remotive para {$certifications->count()} certificaciones…"
        );

        foreach ($certifications as $cert) {

            if (empty($cert->keywords)) {

                $this->warn(
                    "⚠️ {$cert->name} sin keywords, se omite"
                );

                continue;
            }

            // 🔑 keywords limpias
            $keywords = collect(
                explode(',', strtolower($cert->keywords))
            )
            ->map(fn ($k) => trim($k))
            ->filter(fn ($k) => strlen($k) >= 2)
            ->values()
            ->all();

            if (empty($keywords)) {

                $this->warn(
                    "⚠️ {$cert->name} keywords inválidas"
                );

                continue;
            }

            $this->warn(
                "\n🏅 Procesando: {$cert->name}"
            );

            $this->line(
                "🔑 Keywords: " .
                implode(', ', $keywords)
            );

            $totalFound = 0;
            $totalNew   = 0;
            $totalDuplicates = 0;

            $countries  = [];
            $modalities = [];

            try {

                /**
                 * 📡 Remotive search
                 */
                $response = Http::retry(3, 2000)
                    ->timeout(20)
                    ->get($baseUrl, [
                        'search' => $keywords[0],
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "HTTP failed: {$cert->name}"
                    );

                    $this->error(
                        "❌ Error API Remotive"
                    );

                    continue;
                }

                $connectionOk = true;

                $jobs = $response->json()['jobs'] ?? [];

                $totalFound = count($jobs);

                // ✅ evitar N+1
                $existingIds = JobOffer::whereIn(
                    'external_id',
                    collect($jobs)->pluck('id')->filter()
                )
                ->where('source', 'Remotive')
                ->pluck('id', 'external_id');

                foreach ($jobs as $job) {

                    try {

                        $externalId =
                            $job['id'] ?? null;

                        $title =
                            $job['title'] ?? 'N/A';

                        $company =
                            $job['company_name']
                            ?? null;

                        $urlJob =
                            $job['url'] ?? null;

                        $desc = strtolower(
                            strip_tags(
                                $job['description']
                                ?? ''
                            )
                        );

                        /**
                         * 🧠 Match flexible
                         */

                        $text = strtolower(
                            $title . ' ' . $desc
                        );

                        $matched = false;

                        foreach ($keywords as $kw) {

                            if (
                                str_contains(
                                    $text,
                                    $kw
                                )
                            ) {

                                $matched = true;

                                break;
                            }
                        }

                        if (!$matched) {
                            continue;
                        }

                        /**
                         * ⚙️ Modalidad
                         */

                        $modality =
                            $this->detectModality(
                                $job
                            );

                        $isRemote =
                            ($modality === 'remote');

                        /**
                         * 🌍 Ubicación
                         */

                        $locationStr =
                            $job['candidate_required_location']
                            ?? 'Unknown';

                        [$rawCity, $rawCountry] =
                            $this->extractLocation(
                                $locationStr
                            );

                        $country =
                            RemotiveCountry::normalize(
                                $rawCountry
                            );

                        /**
                         * 🗺️ GEO
                         */

                        if ($isRemote) {

                            $finalCity = 'Remote';

                            $lat = $lng = null;

                        } else {

                            [$finalCity, $lat, $lng] =
                                $this->tryGeocode(
                                    $rawCity,
                                    $country
                                );

                            if (!$lat || !$lng) {

                                $this->stats['skipped']++;
                                $totalSkippedAll++;

                                continue;
                            }
                        }

                        /**
                         * 🛑 DEDUPE
                         */

                        if (
                            $externalId &&
                            isset($existingIds[$externalId])
                        ) {

                            $existing = JobOffer::find(
                                $existingIds[$externalId]
                            );

                            if (
                                $existing &&
                                method_exists(
                                    $existing,
                                    'certifications'
                                )
                            ) {

                                $existing->certifications()
                                    ->syncWithoutDetaching([
                                        $cert->id
                                    ]);
                            }

                            $totalDuplicates++;

                            continue;
                        }

                        /**
                         * 🌐 Región
                         */

                        $region =
                            RegionMapper::resolve(
                                $country
                            );

                        /**
                         * 📊 Counters
                         */

                        $countries[$country] =
                            ($countries[$country] ?? 0) + 1;

                        $modalities[$modality] =
                            ($modalities[$modality] ?? 0) + 1;

                        /**
                         * 💾 CREATE
                         */

                        $offer = JobOffer::create([

                            'title' =>
                                $title,

                            'company' =>
                                $company,

                            'country' =>
                                $country,

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

                            'experience_level' =>
                                $this->extractExperience(
                                    $desc
                                ),

                            'education_level' =>
                                $this->extractEducation(
                                    $desc
                                ),

                            'requirements' =>
                                $desc,

                            'source' =>
                                'Remotive',

                            'external_id' =>
                                $externalId,

                            'url' =>
                                $urlJob,

                            'search_query' =>
                                implode(', ', $keywords),

                            'published_at' =>
                                isset($job['publication_date'])
                                    ? Carbon::parse(
                                        $job['publication_date']
                                    )
                                    : now(),

                            'region' =>
                                $region,
                        ]);

                        if (
                            method_exists(
                                $offer,
                                'certifications'
                            )
                        ) {

                            $offer->certifications()
                                ->syncWithoutDetaching([
                                    $cert->id
                                ]);
                        }

                        $totalNew++;

                    } catch (\Throwable $e) {

                        $this->stats['skipped']++;
                        $totalSkippedAll++;

                        Log::error(
                            "❌ Remotive {$cert->name}: {$e->getMessage()}"
                        );
                    }
                }

            } catch (\Throwable $e) {

                SourceStatusService::connectionFailed(
                    $source,
                    "Exception: {$cert->name}"
                );

                Log::error(
                    "❌ Remotive {$cert->name}: " .
                    $e->getMessage()
                );
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

            /**
             * 📊 Métrica diaria
             */

            if ($totalFound > 0) {

                CertificationMetric::updateOrCreate(
                    [
                        'certification_id' =>
                            $cert->id,

                        'run_date' =>
                            now()->toDateString(),

                        'source' =>
                            'Remotive',
                    ],
                    [
                        'certification_name' =>
                            $cert->name,

                        'jobs_found_count' =>
                            $totalFound,

                        'jobs_new_count' =>
                            $totalNew,

                        'countries_breakdown' =>
                            $countries,

                        'modality_breakdown' =>
                            $modalities,
                    ]
                );
            }

            $this->info(
                "✅ {$cert->name}: {$totalNew} nuevas / {$totalFound} encontradas"
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

        $this->line(
            "\n🎯 REMOTIVE CERTIFICATIONS COMPLETADO"
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
    // =====================================================
    // HELPERS (idénticos al original)
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
}
