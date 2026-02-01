<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use App\Models\TechnologyTrend;
use App\Models\TechnologyTrendJob;
use App\Models\TrendMarketSignal;
use App\Models\JobOffer;

use App\Helpers\RegionHelper;

class AdzunaByLanguageTrendsCommand extends Command
{
    protected $signature = 'adzuna:trends-languages
        {--country=us}
        {--pages=1}
        {--year=}
        {--quarter=}';

    protected $description = '📡 Rastrea ofertas laborales desde Adzuna usando Language Trends como semilla y vincula jobs reales';

    protected $stats = [
        'api_hits'      => 0,
        'jobs_created'  => 0,
        'links_created' => 0,
        'fallback'      => 0,
        'mapped'        => 0,
        'skipped'       => 0,
    ];

    protected $capitalMap = [
        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
    ];

    public function handle()
    {
        $country = strtolower($this->option('country'));
        $pages   = (int) $this->option('pages');
        $year    = $this->option('year') ?? now()->year;
        $quarter = $this->option('quarter') ?? ceil(now()->month / 3);

        /* =====================================================
           1️⃣ LANGUAGE TRENDS (MISMA LÓGICA QUE TECNOLOGÍAS)
        ===================================================== */
        $topics = TechnologyTrend::where('year', $year)
            ->where('quarter', $quarter)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.intent')) = 'technology_trend'")
            ->where(function ($q) {
                $q->whereRaw("LOWER(topic_category) LIKE '%lenguaj%'")
                  ->orWhereRaw("LOWER(topic_category) LIKE '%language%'");
            })
            ->get();

        $this->info("📡 Tendencias de lenguajes a procesar: {$topics->count()}");

        $appId   = config('services.adzuna.app_id');
        $appKey  = config('services.adzuna.app_key');
        $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

        foreach ($topics as $topic) {

            $this->warn("\n🔍 Lenguaje en tendencia: {$topic->topic_name}");

            $regions = [];

            for ($page = 1; $page <= $pages; $page++) {

                /* =====================================================
                   2️⃣ KEYWORDS (IDÉNTICO)
                ===================================================== */
                $keywords = [];

                if (!empty($topic->scanned_keywords)) {
                    if (is_array($topic->scanned_keywords)) {
                        $keywords = $topic->scanned_keywords;
                    } elseif (is_string($topic->scanned_keywords)) {
                        $decoded = json_decode($topic->scanned_keywords, true);
                        if (is_array($decoded)) {
                            $keywords = $decoded;
                        }
                    }
                }

                if (empty($keywords)) {
                    $keywords = [$topic->topic_name];
                    $this->stats['fallback']++;
                }

                foreach ($keywords as $keyword) {

                    $url = "{$baseUrl}/{$country}/search/{$page}"
                        . "?app_id={$appId}&app_key={$appKey}"
                        . "&results_per_page=100"
                        . "&what=" . urlencode($keyword);

                    try {
                        $response = Http::timeout(30)->get($url);

                        if ($response->failed()) {
                            $this->stats['skipped']++;
                            continue;
                        }

                        $this->stats['api_hits']++;

                        $results = $response->json('results') ?? [];

                        foreach ($results as $job) {

                            /* =============================
                               3️⃣ JOB OFFER (IDÉNTICO)
                            ============================== */
                            $jobOffer = JobOffer::updateOrCreate(
                                [
                                    'external_id' => $job['id'] ?? null,
                                    'source'      => 'Adzuna',
                                ],
                                [
                                    'title'        => $job['title'] ?? '',
                                    'company'      => $job['company']['display_name'] ?? null,
                                    'country'      => $job['location']['area'][0] ?? null,
                                    'city'         => $job['location']['area'][1] ?? null,
                                    'location'     => $job['location']['display_name'] ?? null,
                                    'description'  => $job['description'] ?? null,
                                    'salary_min'   => $job['salary_min'] ?? null,
                                    'salary_max'   => $job['salary_max'] ?? null,
                                    'currency'     => $job['salary_currency'] ?? null,
                                    'url'          => $job['redirect_url'] ?? null,
                                    'published_at' => isset($job['created'])
                                        ? Carbon::parse($job['created'])
                                        : null,
                                ]
                            );

                            $this->stats['jobs_created']++;

                            /* =============================
                               4️⃣ LINK TREND ↔ JOB
                            ============================== */
                            TechnologyTrendJob::updateOrCreate(
                                [
                                    'technology_trend_id' => $topic->id,
                                    'job_offer_id'        => $jobOffer->id,
                                ],
                                [
                                    'match_type'       => 'keyword',
                                    'confidence_score' => 0.70,
                                ]
                            );

                            $this->stats['links_created']++;

                            /* =============================
                               5️⃣ REGIÓN
                            ============================== */
                            $countryName = $job['location']['area'][0] ?? null;
                            if ($countryName) {
                                $region = RegionHelper::fromCountry(
                                    ucfirst(strtolower($countryName))
                                );
                                if ($region) {
                                    $regions[$region] = ($regions[$region] ?? 0) + 1;
                                }
                            }
                        }

                        sleep(1);

                    } catch (\Throwable $e) {
                        Log::error(
                            "TrendLanguage {$topic->topic_name} / {$keyword}: {$e->getMessage()}"
                        );
                    }
                }
            }

            /* =====================================================
               6️⃣ AGREGADO FINAL REAL
            ===================================================== */
            $jobsCount = TechnologyTrendJob::where(
                'technology_trend_id',
                $topic->id
            )->count();

            TrendMarketSignal::updateOrCreate(
                [
                    'topic_id'   => $topic->id,
                    'topic_type' => 'language',
                    'year'       => $year,
                    'quarter'    => $quarter,
                ],
                [
                    'topic_name'      => $topic->topic_name,
                    'topic_category'  => $topic->topic_category,
                    'job_offer_count' => $jobsCount,
                    'regions'         => array_keys($regions),
                    'signal_strength' => $this->signalScore(
                        $jobsCount,
                        (int) $topic->trend_score
                    ),
                    'last_scanned_at' => now(),
                ]
            );

            $this->info("✅ {$topic->topic_name}: {$jobsCount} ofertas vinculadas");
        }

        $this->info("\n🎯 Scan de Language Trends completado");
        $this->info(json_encode($this->stats, JSON_PRETTY_PRINT));
    }

    /* ================= HELPERS ================= */

    protected function signalScore(int $jobs, int $trendScore): float
    {
        return round(
            ($trendScore * 0.6) + (log($jobs + 1) * 10 * 0.4),
            2
        );
    }
}
