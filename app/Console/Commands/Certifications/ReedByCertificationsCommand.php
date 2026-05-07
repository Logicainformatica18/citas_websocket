<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;
class ReedByCertificationsCommand extends Command
{
    protected $signature = 'reed:certifications {--pages=1}';

    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por certificación.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

   public function handle()
{
    $baseUrl = 'https://www.reed.co.uk/api/1.0/search';

    $run = ScraperRunService::start(
        $this->signature,
        'Reed',
        'certifications'
    );

    $source = 'reed_certifications';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [
            'pages' => $this->option('pages'),
        ],
        apiUrl: $baseUrl
    );

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    $connectionOk = false;
    $startedAt = now();

    try {

        /**
         * ✅ certificaciones
         */
        $certifications = Certification::pluck('name', 'id');

        if ($certifications->isEmpty()) {

            $this->error(
                '❌ No hay certificaciones registradas.'
            );

            return;
        }

        $this->info(
            "🇬🇧 Importando desde Reed para {$certifications->count()} certificaciones…"
        );

        foreach ($certifications as $certificationId => $certificationName) {

            $this->warn(
                "\n🏅 Buscando: {$certificationName}"
            );

            $totalFound = 0;
            $totalNew   = 0;

            $countries  = [];
            $modalities = [];

            for (
                $page = 0;
                $page < (int) $this->option('pages');
                $page++
            ) {

                try {

                    $response = Http::retry(3, 2000)
                        ->withBasicAuth(
                            env('REED_API_KEY'),
                            ''
                        )
                        ->timeout(25)
                        ->get($baseUrl, [
                            'keywords'      =>
                                $certificationName,

                            'resultsToTake' =>
                                100,

                            'resultsToSkip' =>
                                $page * 100,
                        ]);

                } catch (\Throwable $e) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "Connection exception page {$page}"
                    );

                    Log::error($e);

                    continue;
                }

                $this->stats['api_hits']++;

                if ($response->failed()) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "HTTP failed page {$page}"
                    );

                    $this->error(
                        "❌ Error API Reed ({$certificationName}) página {$page}"
                    );

                    continue;
                }

                $connectionOk = true;

                $jobs = $response->json()['results'] ?? [];

                if (empty($jobs)) {

                    $this->info(
                        "⚠️ Sin resultados"
                    );

                    break;
                }

                $totalFound += count($jobs);

                // ✅ evitar N+1
                $existingIds = JobOffer::whereIn(
                    'external_id',
                    collect($jobs)
                        ->pluck('jobId')
                        ->map(fn ($id) => 'reed-' . $id)
                        ->filter()
                )
                ->where('source', 'reed')
                ->pluck('id', 'external_id');

                foreach ($jobs as $job) {

                    try {

                        $externalId =
                            'reed-' . $job['jobId'];

                        /**
                         * 🛑 DEDUPE
                         */

                        if (
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
                                        $certificationId
                                    ]);
                            }

                            $this->stats['skipped']++;
                            $totalSkippedAll++;

                            continue;
                        }

                        /**
                         * 🌍 Reed = UK
                         */

                        $countryIso = 'GB';

                        $country =
                            CountryNormalizer::normalize(
                                'GB'
                            );

                        /**
                         * 🏙️ LOCATION
                         */

                        $locationRaw =
                            $job['locationName']
                            ?? null;

                        $cityMatch =
                            City::where(
                                'city_ascii',
                                $locationRaw
                            )
                            ->orWhere(
                                'city',
                                $locationRaw
                            )
                            ->first();

                        if ($cityMatch) {

                            $lat  = $cityMatch->lat;
                            $lng  = $cityMatch->lng;
                            $city = $cityMatch->city;

                            $country =
                                CountryNormalizer::normalize(
                                    $cityMatch->country
                                );

                        } else {

                            $fallback =
                                self::fallbackCapital(
                                    $countryIso
                                );

                            $lat  = $fallback['lat'];
                            $lng  = $fallback['lng'];
                            $city = $fallback['city'];

                            $country =
                                $fallback['country'];
                        }

                        /**
                         * 📅 FECHA
                         */

                        $publishedAt =
                            $this->parseReedDate(
                                $job['date'] ?? null
                            );

                        /**
                         * 🧠 MODALITY
                         */

                        $modality = 'no_remote';

                        /**
                         * 💾 CREATE
                         */

                        $jobOffer = JobOffer::create([

                            'title' =>
                                $job['jobTitle'] ?? '',

                            'company' =>
                                $job['employerName'] ?? '',

                            'country' =>
                                $country,

                            'city' =>
                                $city,

                            'latitude' =>
                                $lat,

                            'longitude' =>
                                $lng,

                            'modality' =>
                                $modality,

                            'salary_min' =>
                                $job['minimumSalary']
                                ?? null,

                            'salary_max' =>
                                $job['maximumSalary']
                                ?? null,

                            'currency' =>
                                null,

                            'compensation_type' =>
                                null,

                            'source' =>
                                'reed',

                            'external_id' =>
                                $externalId,

                            'url' =>
                                $job['jobUrl'] ?? null,

                            'search_query' =>
                                $certificationName,

                            'published_at' =>
                                $publishedAt,
                        ]);

                        /**
                         * 🔗 Pivot
                         */

                        if (
                            method_exists(
                                $jobOffer,
                                'certifications'
                            )
                        ) {

                            $jobOffer->certifications()
                                ->syncWithoutDetaching([
                                    $certificationId
                                ]);
                        }

                        $totalNew++;

                        $countries[$country] =
                            ($countries[$country] ?? 0) + 1;

                        $modalities[$modality] =
                            ($modalities[$modality] ?? 0) + 1;

                        $this->stats['mapped']++;

                    } catch (\Throwable $e) {

                        $this->stats['skipped']++;
                        $totalSkippedAll++;

                        Log::error(
                            "Reed item error: {$e->getMessage()}"
                        );
                    }
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
                    'certification_id' =>
                        $certificationId,

                    'run_date' =>
                        now()->toDateString(),

                    'source' =>
                        'reed',
                ],
                [
                    'certification_name' =>
                        $certificationName,

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
                "✅ {$certificationName}: {$totalNew} nuevas"
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
            "\n🟢 REED CERTIFICATIONS COMPLETADO"
        );

        $this->info(
            "API Hits: {$this->stats['api_hits']}"
        );

        $this->info(
            "Ofertas nuevas: {$this->stats['mapped']}"
        );

        $this->info(
            "Saltadas: {$this->stats['skipped']}"
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
    /**
     * 📅 Convierte fecha DD/MM/YYYY → Carbon
     */
    private function parseReedDate(?string $date)
    {
        if (!$date) return now();

        $parts = explode('/', $date);

        if (count($parts) === 3) {
            [$day, $month, $year] = $parts;
            return Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-{$day}");
        }

        return now();
    }

    /**
     * 🌐 Capital fallback
     */
    public static function fallbackCapital(string $iso2): array
    {
        return [
            'GB' => [
                'city'    => 'London',
                'lat'     => 51.5072,
                'lng'     => -0.1276,
                'country' => 'Reino Unido',
            ],
        ][$iso2] ?? [
            'city'    => 'Unknown',
            'lat'     => 0,
            'lng'     => 0,
            'country' => 'Desconocido',
        ];
    }
}
