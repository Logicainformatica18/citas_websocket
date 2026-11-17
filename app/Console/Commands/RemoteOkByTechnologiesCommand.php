<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Support\JobFunctions;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;
 use App\Services\SourceStatusService;
use App\Services\Matching\OfferEntityLinker;

class RemoteOkByTechnologiesCommand extends Command
{
    protected $signature = 'remoteok:technologies';
    protected $description = '🧩 Importa empleos 100% remotos desde RemoteOK por tecnología';

    protected $stats = [
        'found'   => 0,
        'new'     => 0,
        'skipped' => 0,
    ];

 public function handle()
{
    // ▶️ INICIAR RUN
    $run = ScraperRunService::start(
        $this->signature,
        'RemoteOK',
        'technologies'
    );

    $source = 'remoteok_technologies';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://remoteok.com/api'
    );

    $connectionOk = false;
    $startedAt = now();

    SourceStatusService::progress($source, 0, 0, 0);

    try {

        $lastTechnologyId = TechnologyMetric::where('source', 'RemoteOK')
            ->orderByDesc('created_at')
            ->value('technology_id');

        $baseQuery = Technology::active()->whereIn('technologies.id', function ($q) {
                $q->select('course_technology.technology_id')
                  ->from('course_technology')
                  ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
            })
            ->orderBy('technologies.id');

        $technologiesQuery = clone $baseQuery;

        if ($lastTechnologyId) {
            $technologiesQuery->where('technologies.id', '>', $lastTechnologyId);
        }

        $technologies = $technologiesQuery->get();

        if ($technologies->isEmpty()) {
            $technologies = $baseQuery->get();
        }

        $this->info("🚀 RemoteOK → procesando {$technologies->count()} tecnologías");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        $response = Http::timeout(25)->get('https://remoteok.com/api');

        if ($response->failed()) {
            SourceStatusService::connectionFailed($source, 'API failed');
            throw new \Exception('RemoteOK API no respondió');
        }

        $connectionOk = true;

        $jobs = collect($response->json())
            ->skip(1)
            ->filter(fn ($j) => isset($j['position']));

        foreach ($technologies as $technology) {

            $technologyId   = $technology->id;
            $technologyName = $technology->name;

            $this->warn("\n🔎 {$technologyName}");

            $totalFound = 0;
            $totalNew   = 0;

            foreach ($jobs as $job) {

                $title = $job['position'] ?? '';
                $tags  = $job['tags'] ?? [];

                $descriptionText = OfferEntityLinker::plain($job['description'] ?? '');

                // Fase 3 (matcher): antes str_contains sin límites de palabra.
                if (! OfferEntityLinker::for('technologies')->confirms($technologyId, $title, OfferEntityLinker::body($descriptionText, $tags))) {
                    continue;
                }

                $totalFound++;
                $totalFoundAll++;

                $externalId = $job['id'] ?? null;

                $existing = $externalId ? JobOffer::where('source', 'RemoteOK')->where('external_id', $externalId)->first() : null;
                if ($existing) {
                    OfferEntityLinker::for('technologies')->attach($existing, [$technologyId]);
                    JobFunctions::fillMissing($existing, JobFunctions::fromDescription($job['description'] ?? null));
                    $totalSkippedAll++;
                    continue;
                }

                $modality = 'remote';

                $locationRaw = strtolower(trim($job['location'] ?? ''));
                $country = 'Remote';

                if (preg_match('/remote\s*[-,\/]?\s*(.+)$/i', $locationRaw, $m)) {
                    $country = CountryNormalizer::normalize(trim($m[1]));
                }

                $region = RegionHelper::fromCountry($country) ?? 'REMOTE';

                $offer = JobOffer::create([
                    'title'        => $title ?: 'N/A',
                    'company'      => $job['company'] ?? null,
                    'country'      => $country,
                    'region'       => $region,
                    'city'         => null,
                    'latitude'     => null,
                    'longitude'    => null,
                    'modality'     => $modality,
                    'source'       => 'RemoteOK',
                    'search_query' => $technologyName,
                    'external_id'  => $externalId,
                    'url'          => $job['url'] ?? null,
                    'description'  => $descriptionText,
                    ...JobFunctions::fromDescription($job['description'] ?? null),
                    'skills'       => implode(', ', $tags),
                    'published_at' => isset($job['date'])
                        ? Carbon::parse($job['date'])
                        : now(),
                ]);
                // Antes no se asociaba la tecnología: la oferta quedaba sin pivote.
                OfferEntityLinker::for('technologies')->attach($offer, [$technologyId]);

                $totalNew++;
                $totalInsertedAll++;
            }

            TechnologyMetric::updateOrCreate(
                [
                    'technology_id' => $technologyId,
                    'run_date'      => now()->toDateString(),
                    'source'        => 'RemoteOK',
                ],
                [
                    'technology_name' => $technologyName,
                    'jobs_found_count'=> $totalFound,
                    'jobs_new_count'  => $totalNew,
                    'updated_at'      => now(),
                ]
            );

            $this->info("✔ {$technologyName}: {$totalNew} nuevas / {$totalFound}");

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

        $this->info("\n🟢 RemoteOK (technologies) finalizado correctamente");

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
