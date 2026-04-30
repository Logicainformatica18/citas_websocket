<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\LanguageMetric;
use App\Models\JobOffer;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;


class SelectaByLanguagesCommand extends Command
{
    protected $signature = 'selecta:languages';
    protected $description = '🟢 Scraping HTML real HiringRoom (Selecta) SOLO consola';

    const URL = 'https://selecta-pe.hiringroom.com/jobs';

 public function handle()
{
    $run = ScraperRunService::start(
        $this->signature,
        'Selecta',
        'languages'
    );

    try {

        $languages = Language::orderBy('id')->get();

        $this->info("🟢 Selecta → {$languages->count()} lenguajes");

        /* ===========================
           SCRAPE LISTADO
        ============================ */
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0'
        ])->get(self::URL);

        if ($response->failed()) {
            throw new \Exception('Error HTTP Selecta');
        }

        $crawler = new Crawler($response->body());

        $jobsHtml = $crawler->filter('.vacancyDataContainer a');

        if (!$jobsHtml->count()) {
            throw new \Exception('No jobs found');
        }

        $parsedJobs = [];

        foreach ($jobsHtml as $node) {
            $parsedJobs[] = $this->parseJob(new Crawler($node));
        }

        /* ===========================
           🔥 TRAER DETALLE SOLO UNA VEZ
        ============================ */
        foreach ($parsedJobs as &$job) {
            $job['full_desc'] = $this->fetchJobDetail($job['url']);
        }
        unset($job);

        $totalFoundAll = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll = 0;

        /* ===========================
           LOOP LENGUAJES
        ============================ */
        foreach ($languages as $language) {

            $languageId   = $language->id;
            $languageName = strtolower($language->name);

            $this->warn("\n🔎 {$languageName}");

            $totalFound = 0;
            $totalNew   = 0;

            foreach ($parsedJobs as $job) {

                $text = $job['full_desc'];

                // 🔥 MATCH MÁS FLEXIBLE
                if (!$this->matchLanguage($text, $languageName)) {
                    continue;
                }

                $totalFound++;
                $totalFoundAll++;

                $externalId = md5($job['url']);

                $existing = JobOffer::where('external_id', $externalId)
                    ->where('source', 'selecta')
                    ->first();

                if ($existing) {
                    $existing->languages()->syncWithoutDetaching([$languageId]);
                    $totalSkippedAll++;
                    continue;
                }

                [$city, $lat, $lng] = $this->resolveLocation($job['location']);

                $offer = JobOffer::create([
                    'title'       => $job['title'],
                    'company'     => 'Selecta',
                    'country'     => 'PE',
                    'city'        => $city,
                    'latitude'    => $lat,
                    'longitude'   => $lng,
                    'modality'    => $job['modality'],
                    'source'      => 'selecta',
                    'external_id' => $externalId,
                    'url'         => $job['url'],
                    'search_query'=> $languageName,
                    'published_at'=> now(),
                    'region'      => RegionHelper::fromCountry('PE'),
                ]);

                $offer->languages()->syncWithoutDetaching([$languageId]);

                $totalNew++;
                $totalInsertedAll++;
            }

            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $languageId,
                    'run_date'    => now()->toDateString(),
                    'source'      => 'selecta',
                ],
                [
                    'language_name'    => $languageName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count'   => $totalNew,
                ]
            );

            $this->info("✔ {$languageName}: {$totalNew} nuevas / {$totalFound}");
        }

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

    } catch (\Throwable $e) {
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}

    /* ===============================
       PARSER PRINCIPAL
    =============================== */

    private function parseJobs(Crawler $crawler): array
    {
        $jobs = [];

        $nodes = $crawler->filter('.vacancyDataContainer a');

        if (!$nodes->count()) {
            return [];
        }

        $nodes->each(function (Crawler $node) use (&$jobs) {
            $jobs[] = $this->extractJobData($node);
        });

        return $jobs;
    }

    private function extractJobData(Crawler $node): array
    {
        $href = $node->attr('href');

        $url = $href
            ? "https://selecta-pe.hiringroom.com{$href}"
            : null;

        $card = $node->filter('.card-body');

        return [
            'title'     => $this->extractTitle($card),
            'location'  => $this->extractLocation($card),
            'area'      => $this->extractArea($card),
            'modality'  => $this->extractModality($node),
            'url'       => $url,
        ];
    }
        /* ===============================
       EXTRACTORES
    =============================== */

    private function extractTitle(Crawler $card): string
    {
        return $this->safeText($card, 'h4.name__vacancy');
    }

    private function extractLocation(Crawler $card): string
    {
        return $this->safeText($card, 'i.hr-Location-pin', true);
    }

    private function extractArea(Crawler $card): string
    {
        return $this->safeText($card, 'i.hr-Work-area', true);
    }

    private function extractModality(Crawler $node): string
    {
        $tags = $node->filter('.tag-vacancy');

        if ($tags->count() >= 2) {
            return trim($tags->eq(1)->text());
        }

        return 'N/A';
    }

    /* ===============================
       HELPERS
    =============================== */

    private function safeText(Crawler $node, string $selector, bool $useParent = false): string
    {
        try {
            if (!$node->filter($selector)->count()) {
                return 'N/A';
            }

            $target = $node->filter($selector);

            if ($useParent) {
                return trim($target->first()->ancestors()->first()->text());
            }

            return trim($target->first()->text());

        } catch (\Throwable $e) {
            return 'N/A';
        }
    }
    private function matchLanguage(string $text, string $language): bool
{
    $text = strtolower($text);

    $map = [
        'javascript' => ['javascript', 'js'],
        'python'     => ['python', 'python3'],
        'java'       => ['java'],
        'php'        => ['php'],
        'c#'         => ['c#', 'csharp'],
    ];

    if (isset($map[$language])) {
        foreach ($map[$language] as $alias) {
            if (str_contains($text, $alias)) return true;
        }
        return false;
    }

    return str_contains($text, $language);
}
private function fetchJobDetail(string $url): string
{
    try {
        $res = Http::timeout(15)->get($url);

        if ($res->failed()) return '';

        $crawler = new Crawler($res->body());

        return strtolower(
            trim($crawler->filter('body')->text())
        );

    } catch (\Throwable $e) {
        return '';
    }
}
}
