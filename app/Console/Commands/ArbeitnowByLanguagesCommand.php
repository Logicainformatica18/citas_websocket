<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;
use App\Models\MarketEntity;
use App\Models\MarketEntityMetric;
 use App\Services\SourceStatusService;
use App\Services\Matching\OfferEntityLinker;

class ArbeitnowByLanguagesCommand extends Command
{
    protected $signature = 'arbeitnow:languages {--pages=5 : páginas del feed (unas 100 ofertas cada una)}';
    protected $description = '🌍 Recorre todos los lenguajes y registra métricas de demanda laboral desde Arbeitnow (Europa/Asia), con geolocalización y modalidades estandarizadas.';

    protected $stats = ['api_hits' => 0, 'fallback' => 0, 'mapped' => 0];

    protected $capitalMap = [
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'pt' => ['city' => 'Lisboa', 'lat' => 38.7169, 'lng' => -9.1399],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122],
        'at' => ['city' => 'Viena', 'lat' => 48.2082, 'lng' => 16.3738],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'ie' => ['city' => 'Dublín', 'lat' => 53.3498, 'lng' => -6.2603],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474],
        'se' => ['city' => 'Estocolmo', 'lat' => 59.3293, 'lng' => 18.0686],
        'dk' => ['city' => 'Copenhague', 'lat' => 55.6761, 'lng' => 12.5683],
        'no' => ['city' => 'Oslo', 'lat' => 59.9139, 'lng' => 10.7522],
        'fi' => ['city' => 'Helsinki', 'lat' => 60.1699, 'lng' => 24.9384],
        // Asia
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'jp' => ['city' => 'Tokio', 'lat' => 35.6895, 'lng' => 139.6917],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],
        'kr' => ['city' => 'Seúl', 'lat' => 37.5665, 'lng' => 126.9780],
        'cn' => ['city' => 'Pekín', 'lat' => 39.9042, 'lng' => 116.4074],
        'ph' => ['city' => 'Manila', 'lat' => 14.5995, 'lng' => 120.9842],
        'vn' => ['city' => 'Hanói', 'lat' => 21.0285, 'lng' => 105.8542],
        'th' => ['city' => 'Bangkok', 'lat' => 13.7563, 'lng' => 100.5018],
        'id' => ['city' => 'Yakarta', 'lat' => -6.2088, 'lng' => 106.8456],
        'my' => ['city' => 'Kuala Lumpur', 'lat' => 3.1390, 'lng' => 101.6869],
        'tw' => ['city' => 'Taipéi', 'lat' => 25.0330, 'lng' => 121.5654],
        'hk' => ['city' => 'Hong Kong', 'lat' => 22.3193, 'lng' => 114.1694],
        // Remoto
        'remote' => ['city' => 'Remoto', 'lat' => 0.0000, 'lng' => 0.0000],
    ];

    protected $geoCache = [];

    public function handle()
    {
        /* =========================================
           1️⃣ START SCRAPER RUN
        ========================================= */
        $run = ScraperRunService::start(
            $this->signature,
            'Arbeitnow',
            'languages'
        );
$source = 'arbeitnow_languages';

SourceStatusService::start(
    source: $source,
    runId: $run->id,
    config: [], // este comando no tiene params, puedes dejar vacío
    apiUrl: 'https://www.arbeitnow.com/api/job-board-api'
);
        // 🔢 contadores GLOBALES del run
        $foundAll = 0;
        $insertedAll = 0;
        $skippedAll = 0;
$startedAt = now();
        try {

            // Fase B (matcher): la API de Arbeitnow ignora "search" y devuelve siempre el mismo feed.
            // Se descarga el feed UNA vez (paginado) y cada oferta se clasifica por su texto.
            $linker = OfferEntityLinker::for('languages');
            $pages = max(1, (int) $this->option('pages'));
            $perEntity = [];
            $connectionOk = false;

            $this->info("🌐 Arbeitnow: {$pages} página(s) del feed, clasificadas con el matcher de lenguajes...");

            for ($page = 1; $page <= $pages; $page++) {
                $response = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api', ['page' => $page]);
                $this->stats['api_hits']++;

                if ($response->failed()) {
                    SourceStatusService::connectionFailed($source, "Error API Arbeitnow (página {$page})");
                    break;
                }
                $connectionOk = true;

                $jobs = $response->json()['data'] ?? [];
                if (empty($jobs)) {
                    break;
                }

                foreach ($jobs as $job) {
                    $title = $job['title'] ?? 'N/A';
                    $company = $job['company_name'] ?? null;
                    $location = $job['location'] ?? '';
                    $description = $job['description'] ?? '';
                    $urlJob = $job['url'] ?? null;
                    $isRemote = $job['remote'] ?? false;

                    $matched = $linker->match($title, OfferEntityLinker::body(
                        OfferEntityLinker::plain($description), $job['tags'] ?? [], $job['job_types'] ?? []
                    ));
                    if (empty($matched)) {
                        $skippedAll++;
                        continue;
                    }

                    $modality = $this->extractModality($location, $isRemote);
                    $countryCode = $this->detectCountryCode($location, $isRemote);
                    [$city, $lat, $lng, $country] = $this->getCoordsFromCountry(
                        $this->extractCity($location),
                        $countryCode
                    );

                    if (empty($country)) {
                        $skippedAll++;
                        continue;
                    }

                    $foundAll++;
                    foreach ($matched as $entityId) {
                        $perEntity[$entityId]['found'] = ($perEntity[$entityId]['found'] ?? 0) + 1;
                    }

                    $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));
                    $existing = JobOffer::where('source', 'Arbeitnow')
                        ->where('external_id', $externalId)
                        ->first();

                    if ($existing) {
                        $linker->attach($existing, array_keys($matched));
                        $skippedAll++;
                        continue;
                    }

                    $offer = JobOffer::create([
                        'title' => $title,
                        'company' => $company,
                        'country' => $country,
                        'city' => $city,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'modality' => $modality,
                        'currency' => 'EUR',
                        'requirements' => OfferEntityLinker::plain($description),
                        'source' => 'Arbeitnow',
                        'external_id' => $externalId,
                        'url' => $urlJob,
                        'search_query' => 'feed',
                        'published_at' => isset($job['created_at'])
                            ? Carbon::parse($job['created_at'])
                            : now(),
                        'region' => RegionHelper::fromCountry($country),
                    ]);
                    $linker->attach($offer, array_keys($matched));
                    $insertedAll++;

                    foreach ($matched as $entityId) {
                        $perEntity[$entityId]['new'] = ($perEntity[$entityId]['new'] ?? 0) + 1;
                        $perEntity[$entityId]['countries'][$country] = ($perEntity[$entityId]['countries'][$country] ?? 0) + 1;
                        $perEntity[$entityId]['modalities'][$modality] = ($perEntity[$entityId]['modalities'][$modality] ?? 0) + 1;
                    }
                }

                SourceStatusService::progress($source, $foundAll, $insertedAll, $skippedAll);
            }

            /* ================= MÉTRICA DIARIA POR LENGUAJE ================= */
            $names = MarketEntity::whereIn('id', array_keys($perEntity))->pluck('name', 'id');
            foreach ($perEntity as $entityId => $m) {
                MarketEntityMetric::updateOrCreate(
                    ['market_entity_id' => $entityId, 'run_date' => now()->toDateString(), 'source' => 'Arbeitnow'],
                    [
                        'entity_name' => $names[$entityId] ?? '',
                        'jobs_found_count' => $m['found'] ?? 0,
                        'jobs_new_count' => $m['new'] ?? 0,
                        'countries_breakdown' => $m['countries'] ?? [],
                        'modality_breakdown' => $m['modalities'] ?? [],
                    ]
                );
            }

            $this->info("✅ Arbeitnow: {$foundAll} ofertas con lenguajes, {$insertedAll} nuevas, {$skippedAll} omitidas (sin lenguaje, sin país o ya existentes)");
         if ($connectionOk) {
    SourceStatusService::connectionOk($source);
} 
            /* =========================================
               2️⃣ SUCCESS
            ========================================= */
            ScraperRunService::success(
                $run,
                $foundAll,
                $insertedAll,
                $skippedAll
            );

  
SourceStatusService::success(
    source: $source,
    runId: $run->id,
    found: $foundAll,
    inserted: $insertedAll,
    skipped: $skippedAll,
    durationSeconds: now()->diffInSeconds($startedAt)
);
            $this->info("🎯 Proceso Arbeitnow finalizado correctamente");
        } catch (\Throwable $e) {
            /* =========================================
               3️⃣ FAILED
            ========================================= */
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

    protected function extractCity(?string $location): ?string
    {
        if (empty($location))
            return null;
        $parts = explode(',', $location);
        return trim($parts[0]); // Devuelve solo la primera parte (ej: "Berlin" de "Berlin, Germany")
    }

    protected function detectCountryCode($location, $isRemote)
    {
        $loc = strtolower($location ?? '');
        if ($isRemote)
            return 'remote';

        return match (true) {
            str_contains($loc, 'germany') || str_contains($loc, 'deutschland') || str_contains($loc, 'berlin') => 'de',
            str_contains($loc, 'spain') || str_contains($loc, 'espa') || str_contains($loc, 'madrid') => 'es',
            str_contains($loc, 'france') || str_contains($loc, 'paris') => 'fr',
            str_contains($loc, 'portugal') || str_contains($loc, 'lisbon') => 'pt',
            str_contains($loc, 'italy') || str_contains($loc, 'rome') => 'it',
            str_contains($loc, 'netherlands') || str_contains($loc, 'amsterdam') => 'nl',
            str_contains($loc, 'austria') || str_contains($loc, 'vienna') => 'at',
            str_contains($loc, 'poland') || str_contains($loc, 'warsaw') => 'pl',
            str_contains($loc, 'uk') || str_contains($loc, 'united kingdom') || str_contains($loc, 'london') => 'gb',
            str_contains($loc, 'ireland') || str_contains($loc, 'dublin') => 'ie',
            str_contains($loc, 'switzerland') || str_contains($loc, 'zurich') => 'ch',
            str_contains($loc, 'sweden') => 'se',
            str_contains($loc, 'norway') => 'no',
            str_contains($loc, 'denmark') => 'dk',
            str_contains($loc, 'finland') => 'fi',
            str_contains($loc, 'japan') => 'jp',
            str_contains($loc, 'india') => 'in',
            str_contains($loc, 'singapore') => 'sg',
            str_contains($loc, 'korea') => 'kr',
            str_contains($loc, 'china') => 'cn',
            str_contains($loc, 'philippines') => 'ph',
            str_contains($loc, 'vietnam') => 'vn',
            str_contains($loc, 'thailand') => 'th',
            str_contains($loc, 'indonesia') => 'id',
            str_contains($loc, 'malaysia') => 'my',
            str_contains($loc, 'taiwan') => 'tw',
            str_contains($loc, 'hong kong') => 'hk',
            default => null,
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryHint)
    {
        if ($city && strtolower($city) !== 'remote') {
            $cityNorm = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $city));

            // 🗺️ 1️⃣ Buscar primero en tu tabla `cities`
            $found = City::whereRaw('LOWER(city_ascii) = ?', [$cityNorm])
                ->when($countryHint, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryHint)]))
                ->first();

            if ($found) {
                $this->stats['mapped']++;
                return [
                    $found->city,
                    $found->lat,
                    $found->lng,
                    $found->country ?? strtoupper($found->iso2), // 👈 usa el nombre del país completo
                ];
            }

            // 🌍 2️⃣ Si no está en DB, usar Nominatim
            [$lat, $lng, $countryName] = $this->getCoords($city, $countryHint);
            if ($lat && $lng) {
                $this->stats['api_hits']++;
                return [$city, $lat, $lng, $countryName];
            }
        }

        return [$city, null, null, $countryHint];
    }



    protected function getCoords($city, $country)
    {
        $key = strtolower(trim("{$city},{$country}"));
        if (isset($this->geoCache[$key]))
            return $this->geoCache[$key];

        try {
            $res = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$city}" . ($country ? ", {$country}" : ''),
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 1,
            ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                $countryName = $data['address']['country'] ?? null; // 👈 nombre completo
                return $this->geoCache[$key] = [
                    (float) $data['lat'],
                    (float) $data['lon'],
                    $countryName,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $e->getMessage());
        }

        return $this->geoCache[$key] = [null, null, null];
    }



    protected function extractModality(string $location, bool $isRemote): string
    {
        $loc = strtolower($location);

        return match (true) {

            /* ============================
               1️⃣ REMOTO EXPLÍCITO
            ============================ */
            $isRemote,
            str_contains($loc, 'remote'),
            str_contains($loc, 'anywhere'),
            str_contains($loc, 'work from home'),
            str_contains($loc, 'home office'),
            str_contains($loc, 'teletrabajo') => 'remote',

            /* ============================
               2️⃣ HÍBRIDO
            ============================ */
            str_contains($loc, 'hybrid'),
            str_contains($loc, 'híbrido'),
            str_contains($loc, 'mixed'),
            str_contains($loc, 'flexible') => 'hybrid',

            /* ============================
               3️⃣ PRESENCIAL EXPLÍCITO
            ============================ */
            str_contains($loc, 'on-site'),
            str_contains($loc, 'onsite'),
            str_contains($loc, 'presencial'),
            str_contains($loc, 'in office'),
            str_contains($loc, 'office'),
            str_contains($loc, 'based in'),
            str_contains($loc, 'ubicado en'),
            str_contains($loc, 'located in') => 'presencial',

            /* ============================
               4️⃣ NO PRECISA
            ============================ */
            default => 'no_precisa',
        };
    }



}
