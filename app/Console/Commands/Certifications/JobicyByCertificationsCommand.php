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
use App\Helpers\CountryNormalizer;
 use App\Services\SourceStatusService;
 use App\Services\ScraperRunService;
class JobicyByCertificationsCommand extends Command
{
    protected $signature = 'jobicy:certifications';

    protected $description = '🏅 Importa ofertas laborales desde Jobicy por certificación (compatible con CountryNormalizer).';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'fallback' => 0,
        'skipped'  => 0,
    ];

    /**
     * 🌍 Capitales para fallback
     * (NO dependen del CountryNormalizer)
     */
    protected $capitalMap = [
        'Estados Unidos' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'Canadá'         => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'Reino Unido'    => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'Australia'      => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093],
        'España'         => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'México'         => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'Brasil'         => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],
        'Alemania'       => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'Francia'        => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'India'          => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
    ];

    public function handle()
{
    $baseUrl = 'https://jobicy.com/api/v2/remote-jobs';

    $run = ScraperRunService::start(
        $this->signature,
        'Jobicy',
        'certifications'
    );

    $source = 'jobicy_certifications';

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

        // ✅ certificaciones
        $certifications = Certification::where('enabled', 1)
            ->pluck('name', 'id');

        if ($certifications->isEmpty()) {

            $this->error(
                '❌ No hay certificaciones habilitadas'
            );

            return;
        }

        $this->info(
            "🏅 Jobicy → {$certifications->count()} certificaciones"
        );

        // 🌐 UNA llamada global
        try {

            $response = Http::retry(3, 2000)
                ->timeout(25)
                ->get($baseUrl);

        } catch (\Throwable $e) {

            SourceStatusService::connectionFailed(
                $source,
                'Connection exception'
            );

            Log::error($e);

            return;
        }

        if ($response->failed()) {

            SourceStatusService::connectionFailed(
                $source,
                'HTTP failed'
            );

            $this->error(
                '❌ Jobicy API no respondió'
            );

            return;
        }

        $connectionOk = true;

        $this->stats['api_hits']++;

        $allJobs = collect(
            $response->json('jobs') ?? []
        );

        // ✅ evitar N+1
        $existingIds = JobOffer::whereIn(
            'external_id',
            $allJobs->pluck('id')->filter()
        )
        ->where('source', 'Jobicy')
        ->pluck('id', 'external_id');

        foreach ($certifications as $certId => $certName) {

            $this->warn(
                "\n🎯 Procesando: {$certName}"
            );

            $jobs = $allJobs->filter(function ($job) use ($certName) {

                return str_contains(
                    strtolower(
                        ($job['jobTitle'] ?? '') . ' ' .
                        ($job['jobDescription'] ?? '')
                    ),
                    strtolower($certName)
                );
            });

            $totalFound = $jobs->count();
            $totalNew   = 0;

            $countries  = [];
            $modalities = [];

            foreach ($jobs as $job) {

                try {

                    $externalId =
                        $job['id'] ?? null;

                    if (!$externalId) {

                        $totalSkippedAll++;

                        continue;
                    }

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

                        continue;
                    }

                    // 🌍 país
                    $countryRaw =
                        $job['jobGeo'] ?? null;

                    $country =
                        CountryNormalizer::normalize(
                            $countryRaw
                        );

                    // 📍 geo
                    [$city, $lat, $lng] =
                        $this->resolveCoords(
                            $country
                        );

                    // 🧠 modalidad
                    $desc = strtolower(
                        strip_tags(
                            $job['jobDescription']
                            ?? ''
                        )
                    );

                    $modality =
                        $this->detectModality(
                            $desc
                        );

                    $offer = JobOffer::create([

                        'title' =>
                            $job['jobTitle']
                            ?? 'N/A',

                        'company' =>
                            $job['companyName']
                            ?? null,

                        'country' =>
                            $country,

                        'region' =>
                            RegionHelper::fromCountry(
                                $country
                            ),

                        'city' =>
                            $city,

                        'latitude' =>
                            $lat,

                        'longitude' =>
                            $lng,

                        'modality' =>
                            $modality,

                        'description' =>
                            strip_tags(
                                $job['jobDescription']
                                ?? ''
                            ),

                        'source' =>
                            'Jobicy',

                        'external_id' =>
                            $externalId,

                        'url' =>
                            $job['url']
                            ?? null,

                        'search_query' =>
                            $certName,

                        'published_at' =>
                            isset($job['pubDate'])
                                ? Carbon::parse(
                                    $job['pubDate']
                                )
                                : now(),
                    ]);

                    $offer->certifications()
                        ->syncWithoutDetaching([
                            $certId
                        ]);

                    $totalNew++;

                    $countries[$country] =
                        ($countries[$country] ?? 0) + 1;

                    $modalities[$modality] =
                        ($modalities[$modality] ?? 0) + 1;

                    $this->line(
                        "✅ {$offer->title}"
                    );

                } catch (\Throwable $e) {

                    $totalSkippedAll++;

                    Log::error(
                        "Jobicy item error: {$e->getMessage()}"
                    );
                }
            }

            /* =====================================================
               📊 ACUMULADOS
            ===================================================== */

            $totalFoundAll += $totalFound;
            $totalInsertedAll += $totalNew;

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
                    'source'           => 'Jobicy',
                ],
                [
                    'certification_name' =>
                        $certName,

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

            $this->info(
                "📊 {$certName}: {$totalNew} nuevas | {$totalFound} encontradas"
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
            "\n🎯 Jobicy certificaciones finalizado"
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

    protected function detectModality(string $text): string
    {
        return match (true) {
            str_contains($text, 'hybrid') => 'hybrid',
            str_contains($text, 'remote'),
            str_contains($text, 'anywhere'),
            str_contains($text, 'worldwide') => 'remote',
            default => 'remote',
        };
    }

    /**
     * 🌍 Geo seguro compatible con CountryNormalizer
     */
    protected function resolveCoords(string $country): array
    {
        if (isset($this->capitalMap[$country])) {
            $this->stats['fallback']++;
            return array_values($this->capitalMap[$country]);
        }

        // Jobicy = remoto → no romper
        return ['Remoto', null, null];
    }
}
