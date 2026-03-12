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

use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;

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
    $run = ScraperRunService::start(
        $this->signature,
        'Adzuna',
        'market_entities'
    );

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    try {

        $country = strtolower($this->option('country'));
        $pages   = (int) $this->option('pages');

        /* =====================================================
           🔁 BASE QUERY (Market Entities tipo certification)
        ===================================================== */
     $baseQuery = MarketEntity::where('entity_type', 'certification')->orderBy('id');


        /* =====================================================
           ▶️ REANUDAR DESDE ÚLTIMA ENTIDAD PROCESADA
        ===================================================== */
       $lastEntityId = MarketEntityMetric::where('source', 'Adzuna')
    ->orderByDesc('created_at')
    ->value('market_entity_id');
$entitiesQuery = clone $baseQuery;

if ($lastEntityId) {
    $entitiesQuery->where('id', '>', $lastEntityId);
}



$entities = $entitiesQuery->pluck('name', 'id');

if ($entities->isEmpty()) {
    // 🔁 ciclo completo → reiniciar
    $entities = $baseQuery->pluck('name', 'id');
}


        $appId   = config('services.adzuna.app_id');
        $appKey  = config('services.adzuna.app_key');
        $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

        $this->info("🏅 Iniciando Adzuna para {$entities->count()} market certifications");

        foreach ($entities as $entityId => $entityName) {

            $this->warn("\n💡 Market certification: {$entityName}");

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
    $response = Http::timeout(30)->get($url);
} catch (\Illuminate\Http\Client\ConnectionException $e) {
    $this->error("⏱️ Timeout Adzuna ({$entityName}) page {$page}");
    sleep(5); // backoff
    continue;
}


                if ($response->failed()) {
                    $this->error("❌ API error {$entityName} page {$page}");
                    continue;
                }

                $results = $response->json('results') ?? [];
                $totalFound += count($results);

                foreach ($results as $job) {

                    $desc = strtolower($job['description'] ?? '');

                    // 🔍 Validación real por texto
                    if (!str_contains($desc, strtolower($entityName))) {
                        continue;
                    }

                    $externalId = $job['id'] ?? null;

                    $existing = JobOffer::where('external_id', $externalId)->first();

                    if ($existing) {
                        // 👉 asociar entidad de mercado
                   $existing->marketCertifications()->syncWithoutDetaching([$entityId]);

                        $totalDuplicate++;
                        continue;
                    }

                    /* ===============================
                       MODALIDAD
                    =============================== */
                    $title = strtolower($job['title'] ?? '');
                    $modality = $this->detectModality($title, $desc);

                    /* ===============================
                       UBICACIÓN
                    =============================== */
                    $area = $job['location']['area'] ?? [];
                    $city = $area[1] ?? ($area[0] ?? null);

                    $countryCode = strtoupper($area[0] ?? $country);
                    $countryFull = ucfirst(strtolower($countryCode));

                    [$city, $lat, $lng] =
                        $this->getCoordsFromCountry($city, strtolower($countryCode));

                    if (!$lat || !$lng) {
                        if (isset($this->capitalMap[strtolower($countryCode)])) {
                            $cap = $this->capitalMap[strtolower($countryCode)];
                            $city = $cap['city'];
                            $lat  = $cap['lat'];
                            $lng  = $cap['lng'];
                        } else {
                            $totalSkippedAll++;
                            continue;
                        }
                    }

                    $offer = JobOffer::create([
                        'title'        => $job['title'] ?? 'N/A',
                        'company'      => $job['company']['display_name'] ?? null,
                        'country'      => $countryFull,
                        'city'         => $city,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'modality'     => $modality,
                        'requirements' => strip_tags($job['description'] ?? null),
                        'source'       => 'Adzuna',
                        'external_id'  => $externalId,
                        'url'          => $job['redirect_url'] ?? null,
                        'search_query' => $entityName,
                        'published_at' => isset($job['created'])
                            ? Carbon::parse($job['created'])
                            : now(),
                        'region'       => RegionHelper::fromCountry($countryFull),
                    ]);

                    // 👉 asociar market entity
                  $offer->marketCertifications()->syncWithoutDetaching([$entityId]);


                    $totalNew++;
                    $countries[$countryCode] = ($countries[$countryCode] ?? 0) + 1;
                    $modalities[$modality]   = ($modalities[$modality] ?? 0) + 1;
                }

                sleep(1);
            }

            /* ===============================
               ACUMULADOS GLOBALES
            =============================== */
            $totalFoundAll    += $totalFound;
            $totalInsertedAll += $totalNew;
            $totalSkippedAll  += $totalDuplicate;

            /* ===============================
               MÉTRICA DIARIA (Market Entity)
            =============================== */
            MarketEntityMetric::firstOrCreate(
                [
                    'market_entity_id' => $entityId,
                    'run_date'         => now()->toDateString(),
                    'source'           => 'Adzuna',
                ],
                [
                    'entity_name'        => $entityName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $this->info("✅ {$entityName}: {$totalNew} nuevas | {$totalFound} encontradas");
        }

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("🎯 Scraper de market certifications finalizado");

    } catch (\Throwable $e) {
        ScraperRunService::failed($run, $e);
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
