<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\LanguageMetric;
use App\Models\JobOffer;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;

class USAJOBSByLanguagesCommand extends Command
{
    protected $signature = 'usajobs:languages {--pages=1}';

    protected $description = '🇺🇸 Importa ofertas laborales desde USAJOBS por lenguajes del PE ISIL.';

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
        'languages'
    );

    $source = 'usajobs_languages';

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

        $languages = Language::whereIn('languages.id', function ($q) {
                $q->select('cl.language_id')
                  ->from('course_language as cl')
                  ->join('career_course as cc', 'cc.course_id', '=', 'cl.course_id');
            })
            ->orderBy('languages.id')
            ->get();

        $this->info("🇺🇸 USAJOBS → {$languages->count()} lenguajes PE ISIL");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        foreach ($languages as $language) {

            $languageId   = $language->id;
            $languageName = $language->name;

            $this->warn("\n🔎 {$languageName}");

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
                        'Keyword'        => $languageName,
                        'ResultsPerPage' => 100,
                        'Page'           => $page,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    SourceStatusService::connectionFailed($source, $languageName);
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

                    if (
                        JobOffer::where('source', 'usajobs')
                            ->where('external_id', $externalId)
                            ->exists()
                    ) {
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

                    $offer = JobOffer::create([
                        'title'        => $job['PositionTitle'] ?? '',
                        'company'      => $job['OrganizationName'] ?? '',
                        'country'      => $country,
                        'city'         => $city,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'modality'     => 'presencial',
                        'source'       => 'usajobs',
                        'external_id'  => $externalId,
                        'url'          => $job['PositionURI'] ?? null,
                        'search_query' => $languageName,
                        'published_at' => $publishedAt,
                    ]);

                    $offer->languages()->syncWithoutDetaching([$languageId]);

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

            $this->info("✔ {$languageName}: {$totalNew} nuevas / {$totalFound}");
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
