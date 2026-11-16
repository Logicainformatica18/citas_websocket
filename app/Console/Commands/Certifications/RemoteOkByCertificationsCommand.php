<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;
use App\Services\Matching\OfferEntityLinker;
use App\Services\Matching\SearchTerms;

class RemoteOkByCertificationsCommand extends Command
{
    protected $signature = 'remoteok:certifications';

    protected $description = '🌍 RemoteOK por certificación usando keywordss flexibles desde BD';

   public function handle()
{
    $baseUrl = 'https://remoteok.com/api';

    $run = ScraperRunService::start(
        $this->signature,
        'RemoteOK',
        'certifications'
    );

    $source = 'remoteok_certifications';

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

        $certifications = Certification::active()->select(
            'id',
            'name',
            'keywords',
            'market_entity_id'
        )->get();

        if ($certifications->isEmpty()) {

            $this->error(
                '❌ No hay certificaciones'
            );

            return;
        }

        try {

            $response = Http::retry(3, 2000)
                ->timeout(30)
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
                '❌ API RemoteOK falló'
            );

            return;
        }

        $connectionOk = true;

        $jobs = collect($response->json())
            ->skip(1)
            ->filter(fn ($j) =>
                isset($j['position'])
            );

        $this->info(
            "📡 Ofertas cargadas: {$jobs->count()}"
        );

        // ✅ evitar N+1
        $existingIds = JobOffer::whereIn(
            'external_id',
            $jobs->pluck('id')->filter()
        )
        ->where('source', 'RemoteOK')
        ->pluck('id', 'external_id');

        foreach ($certifications as $cert) {

            // keywords es JSON: antes se partía como texto y por palabras sueltas ("aws", "certified")
            $keywords = collect(SearchTerms::forCertification($cert->keywords, $cert->name, $cert->market_entity_id));

            if ($keywords->isEmpty()) {

                $this->warn(
                    "⚠️ {$cert->name} keywords inválidas"
                );

                continue;
            }

            $this->warn(
                "\n🏅 {$cert->name}"
            );

            $this->line(
                "🔑 Keywords: " .
                $keywords->implode(', ')
            );

            $found = 0;
            $new   = 0;

            $countries  = [];
            $modalities = [];

            foreach ($jobs as $job) {

                try {

                    $text = strtolower(

                        ($job['position'] ?? '') . ' ' .

                        implode(
                            ' ',
                            $job['tags'] ?? []
                        ) . ' ' .

                        ($job['description'] ?? '')
                    );

                    // Matcher
                    if (! OfferEntityLinker::for('certifications')->confirms($cert->id, $job['position'] ?? '', OfferEntityLinker::body($job['description'] ?? '', $job['tags'] ?? []))) {
                        continue;
                    }

                    // 🌍 solo remote
                    $location = strtolower(
                        $job['location'] ?? ''
                    );

                    if (
                        !str_contains(
                            $location,
                            'remote'
                        )
                    ) {
                        continue;
                    }

                    $externalId =
                        $job['id'] ?? null;

                    if (!$externalId) {

                        $totalSkippedAll++;

                        continue;
                    }

                    $found++;

                    // 🔁 duplicado
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

                            OfferEntityLinker::for('certifications')->attachIfConfirmed($existing, $cert->id, false, OfferEntityLinker::body($job['description'] ?? '', $job['tags'] ?? []));
                        }

                        continue;
                    }

                    // 🌍 país
                    $country = 'Remote';

                    if (
                        preg_match(
                            '/remote\s*[-,\/]?\s*(.+)$/i',
                            $location,
                            $m
                        )
                    ) {

                        $country =
                            CountryNormalizer::normalize(
                                trim($m[1])
                            );
                    }

                    $region =
                        RegionHelper::fromCountry(
                            $country
                        ) ?? 'REMOTE';

                    $modality = 'remote';

                    $countries[$country] =
                        ($countries[$country] ?? 0) + 1;

                    $modalities[$modality] =
                        ($modalities[$modality] ?? 0) + 1;

                    $jobOffer = JobOffer::create([

                        'title' =>
                            $job['position'],

                        'company' =>
                            $job['company']
                            ?? null,

                        'country' =>
                            $country,

                        'region' =>
                            $region,

                        'modality' =>
                            'remote',

                        'source' =>
                            'RemoteOK',

                        'external_id' =>
                            $externalId,

                        'search_query' =>
                            $matched,

                        'url' =>
                            $job['url'] ?? null,

                        'published_at' =>
                            isset($job['date'])
                                ? Carbon::parse(
                                    $job['date']
                                )
                                : now(),
                    ]);

                    if (
                        method_exists(
                            $jobOffer,
                            'certifications'
                        )
                    ) {

                        OfferEntityLinker::for('certifications')->attachIfConfirmed($jobOffer, $cert->id, false, OfferEntityLinker::body($job['description'] ?? '', $job['tags'] ?? []));
                    }

                    $new++;

                } catch (\Throwable $e) {

                    $totalSkippedAll++;

                    Log::error(
                        "RemoteOK item error: {$e->getMessage()}"
                    );
                }
            }

            /* =====================================================
               📊 ACUMULADOS
            ===================================================== */

            $totalFoundAll += $found;
            $totalInsertedAll += $new;

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

            if ($found > 0) {

                CertificationMetric::updateOrCreate(
                    [
                        'certification_id' =>
                            $cert->id,

                        'run_date' =>
                            now()->toDateString(),

                        'source' =>
                            'RemoteOK',
                    ],
                    [
                        'certification_name' =>
                            $cert->name,

                        'jobs_found_count' =>
                            $found,

                        'jobs_new_count' =>
                            $new,

                        'countries_breakdown' =>
                            $countries,

                        'modality_breakdown' =>
                            $modalities,
                    ]
                );

                $this->info(
                    "✅ {$found} ofertas detectadas"
                );

            } else {

                $this->warn(
                    "❌ Sin resultados"
                );
            }
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
            "\n🎯 RemoteOK terminado"
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
}
