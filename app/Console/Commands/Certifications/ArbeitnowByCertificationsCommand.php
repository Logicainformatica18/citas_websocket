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
            'certifications'
        );

        $totalFoundAll = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll = 0;

        try {

            /* =====================================================
               🔁 BASE QUERY
            ===================================================== */
            $baseQuery = Certification::where('enabled', 1)
                ->orderBy('id');

            /* =====================================================
               ▶️ REANUDAR DESDE ÚLTIMA CERTIFICACIÓN
            ===================================================== */
            $lastCertificationId = CertificationMetric::where('source', 'Arbeitnow')
                ->orderByDesc('created_at')
                ->value('certification_id');

            $certificationsQuery = clone $baseQuery;

            if ($lastCertificationId) {
                $certificationsQuery->where('id', '>', $lastCertificationId);
            }

            $certifications = $certificationsQuery->pluck('name', 'id');

            if ($certifications->isEmpty()) {
                // 🔁 ciclo completo → reiniciar
                $certifications = $baseQuery->pluck('name', 'id');
            }

            $this->info("🏅 Iniciando Arbeitnow para {$certifications->count()} certificaciones");

            foreach ($certifications as $certId => $certName) {

                $this->warn("\n💡 Certificación: {$certName}");

                $totalFound = 0;
                $totalNew = 0;

                $countries = [];
                $modalities = [];

                /* =====================================================
                   🔎 BÚSQUEDA PRINCIPAL
                ===================================================== */
                $response = Http::timeout(25)->get(
                    'https://www.arbeitnow.com/api/job-board-api',
                    ['search' => $certName]
                );

                $jobs = $response->json('data') ?? [];

                /* =====================================================
                   🔁 FALLBACK GLOBAL + FILTRO TEXTO
                ===================================================== */
                if (count($jobs) === 0) {
                    $fallback = Http::timeout(25)->get(
                        'https://www.arbeitnow.com/api/job-board-api'
                    );

                    $jobs = collect($fallback->json('data') ?? [])
                        ->filter(function ($job) use ($certName) {
                            $text = strtolower(strip_tags(
                                ($job['title'] ?? '') . ' ' .
                                ($job['description'] ?? '')
                            ));
                            return str_contains($text, strtolower($certName));
                        })
                        ->values()
                        ->all();

                    $this->stats['fallback'] += count($jobs);
                }

                $totalFound = count($jobs);

                if ($totalFound === 0) {
                    $this->warn("⚠️ Sin resultados para {$certName}");
                    continue;
                }

                foreach ($jobs as $job) {

                    $externalId = $job['slug']
                        ?? md5($job['url'] ?? uniqid('arbeitnow_'));

                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'Arbeitnow')
                        ->first();

                    if ($existing) {
                        $existing->certifications()
                            ->syncWithoutDetaching([$certId]);
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
                            $lat = $cap['lat'];
                            $lng = $cap['lng'];
                        } else {
                            $totalSkippedAll++;
                            continue;
                        }
                    }

                    $offer = JobOffer::create([
                        'title' => $job['title'] ?? 'N/A',
                        'company' => $job['company_name'] ?? null,
                        'country' => $country,
                        'city' => $city,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'modality' => $modality,
                        'requirements' => strip_tags($job['description'] ?? null),
                        'source' => 'Arbeitnow',
                        'external_id' => $externalId,
                        'url' => $job['url'] ?? null,
                        'published_at' => Carbon::parse($job['created_at'] ?? now()),
                        'region' => RegionHelper::fromCountry($country),
                    ]);

                    $offer->certifications()
                        ->syncWithoutDetaching([$certId]);

                    $totalNew++;

                    $countries[$country] =
                        ($countries[$country] ?? 0) + 1;

                    $modalities[$modality] =
                        ($modalities[$modality] ?? 0) + 1;
                }

                /* =====================================================
                   📊 MÉTRICA DIARIA
                ===================================================== */
                CertificationMetric::firstOrCreate(
                    [
                        'certification_id' => $certId,
                        'run_date' => now()->toDateString(),
                        'source' => 'Arbeitnow',
                    ],
                    [
                        'certification_name' => $certName,
                        'jobs_found_count' => $totalFound,
                        'jobs_new_count' => $totalNew,
                        'countries_breakdown' => $countries,
                        'modality_breakdown' => $modalities,
                    ]
                );

                $totalFoundAll += $totalFound;
                $totalInsertedAll += $totalNew;

                $this->info("✅ {$certName}: {$totalNew} nuevas | {$totalFound} encontradas");

                sleep(1);
            }

            ScraperRunService::success(
                $run,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );

            $this->info("🎯 Arbeitnow certificaciones finalizado");

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
