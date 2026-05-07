<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\MarketEntity;
use App\Models\MarketEntityMetric;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Intl\Countries;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;
 use App\Services\SourceStatusService;
use Illuminate\Support\Str;

class AdzunaByCertificationsCommand extends Command
{
    protected $signature = 'adzuna:certifications {--country=us} {--pages=1}';

    protected $description = '🏅 Importa ofertas laborales desde Adzuna por certificación, con geolocalización, modalidad y métricas diarias.';

    protected $stats = [
        'api_hits'  => 0,
        'fallback'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    protected $capitalMap = [
        'au' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093],
        'nz' => ['city' => 'Wellington', 'lat' => -41.2865, 'lng' => 174.7762],

        // 🌎 América
        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],

        // 🌍 Europa
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041],
        'be' => ['city' => 'Bruselas', 'lat' => 50.8503, 'lng' => 4.3517],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122],

        // 🌏 Asia
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],

        // 🌍 África
        'za' => ['city' => 'Pretoria', 'lat' => -25.7461, 'lng' => 28.1881],
    ];

 public function handle()
{
    $baseUrl = config(
        'services.adzuna.base_url',
        'https://api.adzuna.com/v1/api/jobs'
    );

    $run = ScraperRunService::start(
        $this->signature,
        'Adzuna',
        'market_entities'
    );

    $source = 'adzuna_certifications';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [
            'country' => $this->option('country'),
            'pages'   => $this->option('pages'),
        ],
        apiUrl: $baseUrl
    );

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    $connectionOk = false;
    $startedAt = now();

    try {

        $country = strtolower($this->option('country'));
        $pages   = (int) $this->option('pages');

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
        $lastEntityId = MarketEntityMetric::where('source', 'Adzuna')
            ->orderByDesc('created_at')
            ->value('market_entity_id');

        $entitiesQuery = clone $baseQuery;

        if ($lastEntityId) {
            $entitiesQuery->where('id', '>', $lastEntityId);
        }

        $entities = $entitiesQuery->pluck('name', 'id');

        // 🔁 reinicio completo
        if ($entities->isEmpty()) {
            $entities = $baseQuery->pluck('name', 'id');
        }

        $appId  = config('services.adzuna.app_id');
        $appKey = config('services.adzuna.app_key');

        $this->info(
            "🏅 Iniciando Adzuna para {$entities->count()} certifications"
        );

        foreach ($entities as $entityId => $entityName) {

            $this->warn("\n💡 Certification: {$entityName}");

            $totalFound     = 0;
            $totalNew       = 0;
            $totalDuplicate = 0;

            $countries  = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {

                $url = "{$baseUrl}/{$country}/search/{$page}"
                    . "?app_id={$appId}&app_key={$appKey}"
                    . "&results_per_page=100"
                    . "&what=" . urlencode($entityName);

                try {

                    $response = Http::retry(3, 2000)
                        ->timeout(30)
                        ->get($url);

                } catch (\Throwable $e) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "Timeout/API error: {$entityName} page {$page}"
                    );

                    Log::error($e);

                    continue;
                }

                if ($response->failed()) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "HTTP failed: {$entityName} page {$page}"
                    );

                    $this->error(
                        "❌ API error {$entityName} page {$page}"
                    );

                    continue;
                }

                $connectionOk = true;

                $results = $response->json('results') ?? [];

                if (empty($results)) {
                    break;
                }

                $totalFound += count($results);

                // ✅ evitar N+1
                $existingIds = JobOffer::whereIn(
                    'external_id',
                    collect($results)->pluck('id')->filter()
                )->pluck('id', 'external_id');

                foreach ($results as $job) {

                    $desc = strtolower($job['description'] ?? '');

                    // ✅ validación exacta
                    if (!preg_match(
                        '/\b' . preg_quote($entityName, '/') . '\b/i',
                        $desc
                    )) {
                        continue;
                    }

                    $externalId = $job['id'] ?? null;

                    if (!$externalId) {
                        continue;
                    }

                    // ✅ duplicado
                    if (isset($existingIds[$externalId])) {

                        $existing = JobOffer::find(
                            $existingIds[$externalId]
                        );

                        if ($existing) {

                            $existing->marketCertifications()
                                ->syncWithoutDetaching([$entityId]);
                        }

                        $totalDuplicate++;

                        continue;
                    }

                    /* ===============================
                       MODALIDAD
                    =============================== */

                    $title = strtolower($job['title'] ?? '');

                    $modality = $this->detectModality(
                        $title,
                        $desc
                    );

                    /* ===============================
                       UBICACIÓN
                    =============================== */

                    $area = $job['location']['area'] ?? [];

                    $city = $area[1] ?? ($area[0] ?? null);

                    $countryCode = strtoupper($country);

                    [$city, $lat, $lng] =
                        $this->getCoordsFromCountry(
                            $city,
                            strtolower($countryCode)
                        );

                    // fallback capital
                    if (!$lat || !$lng) {

                        if (isset(
                            $this->capitalMap[strtolower($countryCode)]
                        )) {

                            $cap = $this->capitalMap[
                                strtolower($countryCode)
                            ];

                            $city = $cap['city'];
                            $lat  = $cap['lat'];
                            $lng  = $cap['lng'];

                            $this->stats['fallback']++;

                        } else {

                            $totalSkippedAll++;
                            continue;
                        }
                    }

                    $offer = JobOffer::create([

                        'title' =>
                            $job['title'] ?? 'N/A',

                        'company' =>
                            $job['company']['display_name'] ?? null,

                        'country' =>
                            $countryCode,

                        'city' =>
                            $city,

                        'latitude' =>
                            $lat,

                        'longitude' =>
                            $lng,

                        'modality' =>
                            $modality,

                        'requirements' =>
                            strip_tags(
                                $job['description'] ?? null
                            ),

                        'source' =>
                            'Adzuna',

                        'external_id' =>
                            $externalId,

                        'url' =>
                            $job['redirect_url'] ?? null,

                        'search_query' =>
                            $entityName,

                        'published_at' =>
                            isset($job['created'])
                                ? Carbon::parse($job['created'])
                                : now(),

                        'region' =>
                            RegionHelper::fromCountry($countryCode),
                    ]);

                    // ✅ attach certification
                    $offer->marketCertifications()
                        ->syncWithoutDetaching([$entityId]);

                    $totalNew++;

                    $countries[$countryCode] =
                        ($countries[$countryCode] ?? 0) + 1;

                    $modalities[$modality] =
                        ($modalities[$modality] ?? 0) + 1;
                }

                sleep(1);
            }

            /* ===============================
               ACUMULADOS
            =============================== */

            $totalFoundAll    += $totalFound;
            $totalInsertedAll += $totalNew;
            $totalSkippedAll  += $totalDuplicate;

            /* ===============================
               STATUS PROGRESS
            =============================== */

            SourceStatusService::progress(
                $source,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );

            /* ===============================
               MÉTRICAS
            =============================== */

            MarketEntityMetric::updateOrCreate(
                [
                    'market_entity_id' => $entityId,
                    'run_date'         => now()->toDateString(),
                    'source'           => 'Adzuna',
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
                "✅ {$entityName}: {$totalNew} nuevas | {$totalFound} encontradas"
            );
        }

        /* ===============================
           SUCCESS
        =============================== */

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

        $this->info("🎯 Scraper finalizado");

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

  protected function detectModality(string $title, string $description): string
{
    $text = strtolower($title . ' ' . $description);

    // 🌐 REMOTO
    if (
        str_contains($text, 'remote') ||
        str_contains($text, 'work from home') ||
        str_contains($text, 'home office') ||
        str_contains($text, 'teletrabajo') ||
        str_contains($text, 'anywhere') ||
        str_contains($text, 'fully remote')
    ) {
        return 'remote';
    }

    // 🔀 HÍBRIDO
    if (
        str_contains($text, 'hybrid') ||
        str_contains($text, 'híbrido') ||
        str_contains($text, 'mixto')
    ) {
        return 'hybrid';
    }

    // 🏢 PRESENCIAL EXPLÍCITO
    if (
        str_contains($text, 'on-site') ||
        str_contains($text, 'onsite') ||
        str_contains($text, 'presencial') ||
        str_contains($text, 'in office') ||
        str_contains($text, 'office based') ||
        str_contains($text, 'en oficina')
    ) {
        return 'presencial';
    }

    return 'no_precisa';
}


    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {
            $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn ($q) =>
                    $q->whereRaw('LOWER(iso2) = ?', [$countryCode])
                )
                ->first();

            if ($foundCity) {
                $this->stats['mapped']++;
                return [$foundCity->city, $foundCity->lat, $foundCity->lng];
            }
        }

        return [$city, null, null];
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
            str_contains($text, 'bachelor') => 'bachelor',
            str_contains($text, 'master')   => 'master',
            str_contains($text, 'phd')      => 'phd',
            default => null,
        };
    }
}
