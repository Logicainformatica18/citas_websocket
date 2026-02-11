<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService; 
use App\Models\MarketEntity;
use App\Models\MarketEntityMetric;
class ArbeitnowByCertificationsCommand extends Command
{
    protected $signature = 'arbeitnow:certifications';

    protected $description = '🏅 Importa ofertas desde Arbeitnow por certificación, con geolocalización y métricas diarias.';

    protected $stats = [
        'api_hits' => 0,
        'fallback' => 0,
        'mapped' => 0,
        'skipped' => 0,
    ];

    protected $capitalMap = [
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'ie' => ['city' => 'Dublín', 'lat' => 53.3498, 'lng' => -6.2603],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474],
        'remote' => ['city' => 'Remoto', 'lat' => 0, 'lng' => 0],
    ];

    public function handle()
{
    $run = ScraperRunService::start(
        $this->signature,
        'Arbeitnow',
        'market_entities'
    );

    $totalFoundAll    = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    try {

        /* =====================================================
           🔁 BASE QUERY (Market Entities tipo certification)
        ===================================================== */
        $baseQuery = MarketEntity::where('entity_type', 'certification')
            ->orderBy('id');

        /* =====================================================
           ▶️ REANUDAR DESDE ÚLTIMA ENTIDAD PROCESADA
        ===================================================== */
        $lastEntityId = MarketEntityMetric::where('source', 'Arbeitnow')
            ->orderByDesc('created_at')
            ->value('market_entity_id');

        $entitiesQuery = clone $baseQuery;

        if ($lastEntityId) {
            $entitiesQuery->where('id', '>', $lastEntityId);
        }

        $entities = $entitiesQuery->get();

        if ($entities->isEmpty()) {
            // 🔁 ciclo completo → reiniciar
            $entities = $baseQuery->get();
        }

        $this->info("🏅 Iniciando Arbeitnow para {$entities->count()} market certifications");

        foreach ($entities as $entity) {

            $entityId   = $entity->id;
            $entityName = $entity->name;

            $this->warn("\n💡 Market certification: {$entityName}");

            $totalFound = 0;
            $totalNew   = 0;

            $countries  = [];
            $modalities = [];

            /* =====================================================
               🔎 SEARCH TERMS (nombre + keyword)
            ===================================================== */
            $searchTerms = array_unique(array_filter([
                $entityName,
                $entity->vendor ? "{$entity->vendor} certification" : null,
            ]));

            $jobsCollected = collect();

            foreach ($searchTerms as $term) {

                $response = Http::timeout(25)->get(
                    'https://www.arbeitnow.com/api/job-board-api',
                    ['search' => $term]
                );

                $results = $response->json('data') ?? [];

                $jobsCollected = $jobsCollected->merge($results);
            }

            /* =====================================================
               🔁 FALLBACK GLOBAL + FILTRO TEXTO ESTRICTO
            ===================================================== */
            if ($jobsCollected->isEmpty()) {
                $fallback = Http::timeout(25)->get(
                    'https://www.arbeitnow.com/api/job-board-api'
                );

                $jobsCollected = collect($fallback->json('data') ?? []);
                $this->stats['fallback'] += $jobsCollected->count();
            }

            /* =====================================================
               🔍 VALIDACIÓN FINAL POR NOMBRE
            ===================================================== */
            $jobs = $jobsCollected
                ->unique('slug')
                ->filter(function ($job) use ($entityName) {
                    $text = strtolower(strip_tags(
                        ($job['title'] ?? '') . ' ' .
                        ($job['description'] ?? '')
                    ));
                    return str_contains($text, strtolower($entityName));
                })
                ->values();

            $totalFound = $jobs->count();

            if ($totalFound === 0) {
                $this->warn("⚠️ Sin resultados válidos para {$entityName}");
                continue;
            }

            foreach ($jobs as $job) {

                $externalId = $job['slug']
                    ?? md5($job['url'] ?? uniqid('arbeitnow_'));

                $existing = JobOffer::where('external_id', $externalId)
                    ->where('source', 'Arbeitnow')
                    ->first();

                if ($existing) {
                    $existing->marketCertifications()
                        ->syncWithoutDetaching([$entityId]);
                    continue;
                }

                /* ===============================
                   MODALIDAD
                =============================== */
                $modality = $this->detectModality(
                    $job['location'] ?? '',
                    $job['remote'] ?? false
                );

                /* ===============================
                   UBICACIÓN
                =============================== */
                $location = $job['location'] ?? '';
                $countryCode = $this->detectCountryCode(
                    $location,
                    $job['remote'] ?? false
                );

                [$city, $lat, $lng, $country] =
                    $this->getCoordsFromCountry(
                        $this->extractCity($location),
                        $countryCode
                    );

                if (!$lat || !$lng) {
                    if (isset($this->capitalMap[$countryCode])) {
                        $cap = $this->capitalMap[$countryCode];
                        $city = $cap['city'];
                        $lat  = $cap['lat'];
                        $lng  = $cap['lng'];
                        $country = $countryCode;
                    } else {
                        $totalSkippedAll++;
                        continue;
                    }
                }

                $offer = JobOffer::create([
                    'title'        => $job['title'] ?? 'N/A',
                    'company'      => $job['company_name'] ?? null,
                    'country'      => ucfirst(strtolower($country)),
                    'city'         => $city,
                    'latitude'     => $lat,
                    'longitude'    => $lng,
                    'modality'     => $modality,
                    'requirements' => strip_tags($job['description'] ?? null),
                    'source'       => 'Arbeitnow',
                    'external_id'  => $externalId,
                    'url'          => $job['url'] ?? null,
                    'published_at' => Carbon::parse($job['created_at'] ?? now()),
                    'region'       => RegionHelper::fromCountry($country),
                ]);

                // 👉 asociar market entity
                $offer->marketCertifications()
                    ->syncWithoutDetaching([$entityId]);

                $totalNew++;

                $countries[$country] =
                    ($countries[$country] ?? 0) + 1;

                $modalities[$modality] =
                    ($modalities[$modality] ?? 0) + 1;
            }

            /* =====================================================
               📊 MÉTRICA DIARIA (Market Entity)
            ===================================================== */
            MarketEntityMetric::firstOrCreate(
                [
                    'market_entity_id' => $entityId,
                    'run_date'         => now()->toDateString(),
                    'source'           => 'Arbeitnow',
                ],
                [
                    'entity_name'        => $entityName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $totalFoundAll    += $totalFound;
            $totalInsertedAll += $totalNew;

            $this->info("✅ {$entityName}: {$totalNew} nuevas | {$totalFound} encontradas");

            sleep(1);
        }

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("🎯 Arbeitnow market certifications finalizado");

    } catch (\Throwable $e) {
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}


    /* ================= HELPERS ================= */

    protected function extractCity(?string $location): ?string
    {
        if (!$location)
            return null;
        return trim(explode(',', $location)[0]);
    }

    protected function detectCountryCode(string $location, bool $remote): ?string
    {
        if ($remote)
            return 'remote';

        $loc = strtolower($location);

        return match (true) {
            str_contains($loc, 'germany') => 'de',
            str_contains($loc, 'spain') => 'es',
            str_contains($loc, 'france') => 'fr',
            str_contains($loc, 'italy') => 'it',
            str_contains($loc, 'netherlands') => 'nl',
            str_contains($loc, 'poland') => 'pl',
            str_contains($loc, 'uk'),
            str_contains($loc, 'london') => 'gb',
            str_contains($loc, 'ireland') => 'ie',
            str_contains($loc, 'switzerland') => 'ch',
            default => null,
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city) {
            $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when(
                    $countryCode,
                    fn($q) =>
                    $q->whereRaw('LOWER(iso2) = ?', [$countryCode])
                )
                ->first();

            if ($found) {
                $this->stats['mapped']++;
                return [
                    $found->city,
                    $found->lat,
                    $found->lng,
                    $found->country,
                ];
            }
        }

        return [$city, null, null, $countryCode];
    }

    protected function detectModality(string $location, bool $remote): string
    {
        $text = strtolower($location);

        // 🌐 REMOTO
        if (
            $remote ||
            str_contains($text, 'remote') ||
            str_contains($text, 'home office') ||
            str_contains($text, 'anywhere')
        ) {
            return 'remote';
        }

        // 🔀 HÍBRIDO
        if (
            str_contains($text, 'hybrid') ||
            str_contains($text, 'híbrido')
        ) {
            return 'hybrid';
        }

        // 🏢 PRESENCIAL EXPLÍCITO
        if (
            str_contains($text, 'on-site') ||
            str_contains($text, 'onsite') ||
            str_contains($text, 'office')
        ) {
            return 'presencial';
        }

        return 'no_precisa';
    }
}
