<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use App\Console\Commands\Traits\JobFilterTrait;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;
use App\Services\SourceStatusService;
use App\Models\MarketEntity;
use App\Models\MarketEntityMetric;



class ComputrabajoByCertificationsCommand extends Command
{
    use JobFilterTrait;

    protected $signature = 'computrabajo:certifications {--pages=3}';

    protected $description = '🏅 Scrapea Computrabajo por keyword de certificaciones y registra métricas diarias.';

    protected $countryMap = [
        'pe' => 'Peru',
        'bo' => 'Bolivia',
        'ar' => 'Argentina',
        'uy' => 'Uruguay',
        'mx' => 'Mexico',
        'co' => 'Colombia',
        'ec' => 'Ecuador',
        've' => 'Venezuela',
    ];

    protected $currencyMap = [
        'pe' => 'PEN',
        'bo' => 'BOB',
        'ar' => 'ARS',
        'uy' => 'UYU',
        'mx' => 'MXN',
        'co' => 'COP',
        'ec' => 'USD',
        've' => 'VES',
    ];

    const DEFAULT_LAT = -12.046374;
    const DEFAULT_LNG = -77.042793;

   public function handle()
{
    $baseUrl = 'https://www.computrabajo.com';

    $run = ScraperRunService::start(
        $this->signature,
        'Computrabajo',
        'market_entities'
    );

    $source = 'computrabajo_certifications';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [
            'pages' => $this->option('pages'),
        ],
        apiUrl: $baseUrl
    );

    $pages = (int) $this->option('pages');

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    $connectionOk = false;
    $startedAt = now();

    try {

        /* =====================================================
           🔁 BASE QUERY
        ===================================================== */

        $baseQuery = MarketEntity::where(
            'entity_type',
            'certification'
        )->orderBy('id');

        /* =====================================================
           ▶️ REANUDAR DESDE ÚLTIMA ENTIDAD
        ===================================================== */

        $lastEntityId = MarketEntityMetric::where('source', 'Computrabajo')
            ->orderByDesc('created_at')
            ->value('market_entity_id');

        $entitiesQuery = clone $baseQuery;

        if ($lastEntityId) {

            $entitiesQuery->where('id', '>', $lastEntityId);
        }

        $entities = $entitiesQuery->get();

        // 🔁 reiniciar ciclo completo
        if ($entities->isEmpty()) {

            $entities = $baseQuery->get();
        }

        $this->info(
            "🏅 Computrabajo | {$entities->count()} certifications | {$pages} páginas"
        );

        foreach ($entities as $entity) {

            $entityId   = $entity->id;
            $entityName = $entity->name;

            // 🔎 keyword
            $keyword = strtolower(
                $entity->vendor
                    ? "{$entity->vendor} certification"
                    : $entityName
            );

            $this->warn("\n💡 Certification: {$entityName}");
            $this->line("🔎 Keyword: {$keyword}");

            $slug = $this->makeSearchSlug($keyword);

            $totalFound = 0;
            $totalNew   = 0;

            $countries  = [];
            $modalities = [];

            foreach ($this->countryMap as $code => $country) {

                $this->line("🌍 {$country}");

                for ($page = 1; $page <= $pages; $page++) {

                    $url =
                        "https://{$code}.computrabajo.com/trabajo-de-{$slug}?p={$page}";

                    $this->line("🔗 {$url}");

                    try {

                        $response = Http::retry(3, 2000)
                            ->withHeaders([
                                'User-Agent' => 'Mozilla/5.0',
                                'Accept-Language' => 'es-ES,es;q=0.9',
                            ])
                            ->timeout(25)
                            ->get($url);

                    } catch (\Throwable $e) {

                        SourceStatusService::connectionFailed(
                            $source,
                            "Connection exception: {$country} page {$page}"
                        );

                        Log::error($e);

                        continue;
                    }

                    if ($response->failed()) {

                        SourceStatusService::connectionFailed(
                            $source,
                            "HTTP failed: {$country} page {$page}"
                        );

                        continue;
                    }

                    $connectionOk = true;

                    try {

                        $crawler = new Crawler($response->body());

                        $offers = $crawler->filter(
                            'article[class*="box_offer"]'
                        );

                    } catch (\Throwable $e) {

                        Log::warning(
                            "Crawler error {$country}: {$e->getMessage()}"
                        );

                        continue;
                    }

                    if ($offers->count() === 0) {
                        continue;
                    }

                    $offers->each(function (Crawler $offer) use (
                        $entityId,
                        $entityName,
                        $country,
                        $code,
                        &$totalFound,
                        &$totalNew,
                        &$countries,
                        &$modalities,
                        &$totalSkippedAll
                    ) {

                        try {

                            $title = trim(
                                $offer->filter('h2 a')->text()
                            );

                            // 🚫 no tech
                            if (!$this->isTechRelated($title)) {
                                return;
                            }

                            // 🔍 validación exacta
                            if (!preg_match(
                                '/\b' . preg_quote($entityName, '/') . '\b/i',
                                strtolower($title)
                            )) {
                                return;
                            }

                            $company =
                                $offer->filter('p.fc_base a')->count()
                                    ? trim(
                                        $offer
                                            ->filter('p.fc_base a')
                                            ->text()
                                    )
                                    : null;

                            $href = $offer
                                ->filter('h2 a')
                                ->attr('href');

                            $urlJob =
                                "https://{$code}.computrabajo.com{$href}";

                            $city = $this->extractCityFromUrl(
                                $urlJob
                            );

                            [$lat, $lng] =
                                $this->getCoords(
                                    $city,
                                    $country
                                );

                            $modality =
                                $this->mapModality(
                                    $title . ' ' . $city
                                );

                            // 💰 salario
                            $salaryText = null;

                            $offer->filter('p.fc_aux')->each(
                                function ($node) use (&$salaryText) {

                                    $text = trim($node->text());

                                    if (preg_match(
                                        '/(\$|S\/|US\$)/',
                                        $text
                                    )) {

                                        $salaryText = $text;
                                    }
                                }
                            );

                            [$salaryMin, $salaryMax, $currency] =
                                $this->parseSalary(
                                    $salaryText,
                                    $code
                                );

                            $totalFound++;

                            // ✅ duplicado
                            $existing = JobOffer::where(
                                'source',
                                'Computrabajo'
                            )
                            ->where('url', $urlJob)
                            ->first();

                            if ($existing) {

                                $existing->marketCertifications()
                                    ->syncWithoutDetaching([
                                        $entityId
                                    ]);

                                return;
                            }

                            $countryNorm = match (
                                strtolower($country)
                            ) {

                                'peru'      => 'Perú',
                                'mexico'    => 'México',
                                'colombia'  => 'Colombia',
                                'argentina' => 'Argentina',
                                'uruguay'   => 'Uruguay',
                                'ecuador'   => 'Ecuador',
                                'venezuela' => 'Venezuela',
                                'bolivia'   => 'Bolivia',

                                default => ucfirst($country),
                            };

                            $job = JobOffer::create([

                                'title' =>
                                    $title,

                                'company' =>
                                    $company,

                                'country' =>
                                    $countryNorm,

                                'region' =>
                                    RegionHelper::fromCountry(
                                        $countryNorm
                                    ),

                                'state_code' =>
                                    strtoupper($code),

                                'city' =>
                                    $city,

                                'latitude' =>
                                    $lat,

                                'longitude' =>
                                    $lng,

                                'modality' =>
                                    $modality,

                                'source' =>
                                    'Computrabajo',

                                'url' =>
                                    $urlJob,

                                'salary_min' =>
                                    $salaryMin,

                                'salary_max' =>
                                    $salaryMax,

                                'currency' =>
                                    $currency,

                                'published_at' =>
                                    now(),
                            ]);

                            // ✅ attach market entity
                            $job->marketCertifications()
                                ->syncWithoutDetaching([
                                    $entityId
                                ]);

                            $totalNew++;

                            $countries[$countryNorm] =
                                ($countries[$countryNorm] ?? 0) + 1;

                            $modalities[$modality] =
                                ($modalities[$modality] ?? 0) + 1;

                            $this->line(
                                "✅ {$title} ({$countryNorm} - {$city})"
                            );

                        } catch (\Throwable $e) {

                            $totalSkippedAll++;

                            Log::warning(
                                "⚠️ {$entityName}: {$e->getMessage()}"
                            );
                        }
                    });

                    usleep(
                        random_int(500000, 1500000)
                    );
                }

                sleep(3);
            }

            /* =====================================================
               📊 ACUMULADOS
            ===================================================== */

            $totalFoundAll    += $totalFound;
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

            MarketEntityMetric::updateOrCreate(
                [
                    'market_entity_id' => $entityId,
                    'run_date'         => Carbon::today(),
                    'source'           => 'Computrabajo',
                ],
                [
                    'entity_name'         => $entityName,
                    'jobs_found_count'    => $totalFound,
                    'jobs_new_count'      => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown'  => $modalities,
                ]
            );

            $this->info(
                "📊 {$entityName}: {$totalNew} nuevas / {$totalFound} totales"
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
            "\n🎯 Computrabajo market certifications COMPLETADO"
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

    protected function makeSearchSlug(string $value): string
    {
        $slug = strtolower(trim($value));

        return str_replace(
            ['c#', 'c++', '.net', '#', '+', '.', ' ', '/', '\\'],
            ['c-sharp', 'c-plus-plus', 'dotnet', 'sharp', 'plus', '', '-', '-', '-'],
            $slug
        );
    }

    protected function extractCityFromUrl(string $url): string
    {
        if (preg_match('/-en-([a-z-]+)-[A-Z0-9]+/', $url, $m)) {
            return ucwords(str_replace('-', ' ', $m[1]));
        }
        return 'Remote';
    }

    protected function getCoords(string $city, string $country): array
    {
        return [self::DEFAULT_LAT, self::DEFAULT_LNG];
    }

    protected function parseSalary(?string $text, string $countryCode): array
    {
        if (!$text) {
            return [null, null, $this->currencyMap[$countryCode] ?? null];
        }

        $currency = match (true) {
            str_contains($text, 'US$') => 'USD',
            str_contains($text, 'S/')  => 'PEN',
            str_contains($text, '$')   => $this->currencyMap[$countryCode] ?? 'USD',
            default                    => $this->currencyMap[$countryCode] ?? null,
        };

        preg_match_all('/[\d.,]+/', $text, $matches);
        if (empty($matches[0])) return [null, null, $currency];

        $values = array_map(fn ($v) => floatval(str_replace(',', '', $v)), $matches[0]);

        return [$values[0] ?? null, $values[1] ?? $values[0] ?? null, $currency];
    }
}
