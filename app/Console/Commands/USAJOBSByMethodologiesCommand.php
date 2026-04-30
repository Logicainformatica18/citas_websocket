<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;
class USAJOBSByMethodologiesCommand extends Command
{
    protected $signature = 'usajobs:methodologies {--pages=1}';

    protected $description = '🇺🇸 Importa ofertas laborales desde USAJOBS por metodologías del PE ISIL.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

   public function handle()
{
    $run = ScraperRunService::start(
        $this->signature,
        'USAJOBS',
        'methodologies'
    );

    $source = 'usajobs_methodologies';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://data.usajobs.gov/api/search'
    );

    $connectionOk = false;
    $startedAt = now();

    SourceStatusService::progress($source, 0, 0, 0);

    try {

        $methodologies = Methodology::whereIn('methodologies.id', function ($q) {
                $q->select('cm.methodology_id')
                  ->from('course_methodology as cm')
                  ->join('career_course as cc', 'cc.course_id', '=', 'cm.course_id');
            })
            ->orderBy('methodologies.id')
            ->get();

        $this->info("🇺🇸 USAJOBS → {$methodologies->count()} metodologías PE ISIL");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        foreach ($methodologies as $methodology) {

            $methodologyId   = $methodology->id;
            $methodologyName = $methodology->name;

            $this->warn("\n🔎 {$methodologyName}");

            $totalFound = 0;
            $totalNew   = 0;

            for ($page = 1; $page <= (int) $this->option('pages'); $page++) {

                $response = Http::timeout(40)
                    ->withHeaders([
                        'Host'              => 'data.usajobs.gov',
                        'User-Agent'        => 'Isil Scraper (contacto@isil.pe)',
                        'Authorization-Key' => config('services.usajobs.key'),
                    ])
                    ->get('https://data.usajobs.gov/api/Search', [
                        'Keyword'        => $methodologyName,
                        'ResultsPerPage' => 100,
                        'Page'           => $page,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    SourceStatusService::connectionFailed($source, $methodologyName);
                    break;
                }

                $connectionOk = true;

                $items = $response->json('SearchResult.SearchResultItems', []);

                if (empty($items)) break;

                foreach ($items as $item) {

                    $job = $item['MatchedObjectDescriptor'] ?? [];

                    $totalFound++;
                    $totalFoundAll++;

                    $externalId = 'usajobs-' . ($job['PositionID'] ?? uniqid());

                    $existing = JobOffer::where('source', 'usajobs')
                        ->where('external_id', $externalId)
                        ->first();

                    if ($existing) {
                        $existing->methodologies()
                            ->syncWithoutDetaching([$methodologyId]);
                        $totalSkippedAll++;
                        continue;
                    }

                    $locationRaw = $job['PositionLocation'][0]['LocationName'] ?? null;

                    $cityMatch = $locationRaw
                        ? City::whereRaw('LOWER(city_ascii) = ?', [strtolower($locationRaw)])
                            ->orWhereRaw('LOWER(city) = ?', [strtolower($locationRaw)])
                            ->first()
                        : null;

                    if ($cityMatch) {
                        $city    = $cityMatch->city;
                        $lat     = $cityMatch->lat;
                        $lng     = $cityMatch->lng;
                        $country = CountryNormalizer::normalize($cityMatch->country);
                    } else {
                        $city    = 'Washington D.C.';
                        $lat     = 38.8951;
                        $lng     = -77.0364;
                        $country = 'Estados Unidos';
                    }

                    $publishedAt = isset($job['PublicationStartDate'])
                        ? Carbon::parse($job['PublicationStartDate'])
                        : now();

                    $salaryMin = $this->cleanSalary(
                        $job['PositionRemuneration'][0]['MinimumRange'] ?? null
                    );

                    $salaryMax = $this->cleanSalary(
                        $job['PositionRemuneration'][0]['MaximumRange'] ?? null
                    );

                    $compType = $this->normalizeCompType(
                        $job['PositionRemuneration'][0]['RateIntervalCode'] ?? null
                    );

                    $offer = JobOffer::create([
                        'title'             => $job['PositionTitle'] ?? '',
                        'company'           => $job['OrganizationName'] ?? '',
                        'country'           => $country,
                        'city'              => $city,
                        'latitude'          => $lat,
                        'longitude'         => $lng,
                        'modality'          => 'presencial',
                        'salary_min'        => $salaryMin,
                        'salary_max'        => $salaryMax,
                        'currency'          => 'USD',
                        'compensation_type' => $compType,
                        'source'            => 'usajobs',
                        'external_id'       => $externalId,
                        'url'               => $job['PositionURI'] ?? null,
                        'search_query'      => $methodologyName,
                        'published_at'      => $publishedAt,
                    ]);

                    $offer->methodologies()
                        ->syncWithoutDetaching([$methodologyId]);

                    $totalNew++;
                    $totalInsertedAll++;
                }

                SourceStatusService::progress(
                    $source,
                    $totalFoundAll,
                    $totalInsertedAll,
                    $totalSkippedAll
                );

                sleep(1);
            }

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

    /* ===========================
       HELPERS
    ============================ */

    protected function cleanSalary(?string $value): ?float
    {
        if (!$value) return null;
        return (float) str_replace([',', ' '], '', $value);
    }

    protected function normalizeCompType(?string $code): ?string
    {
        return match ($code) {
            'PA' => 'yearly',
            'PH' => 'hourly',
            'PM' => 'monthly',
            'PD' => 'daily',
            default => null,
        };
    }
}
