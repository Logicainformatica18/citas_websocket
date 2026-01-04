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

class ArbeitnowByCertificationsCommand extends Command
{
    protected $signature = 'arbeitnow:certifications';

    protected $description = '🏅 Importa ofertas desde Arbeitnow por certificación, con geolocalización y métricas diarias.';

    protected $stats = [
        'api_hits' => 0,
        'fallback' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
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
        $certifications = Certification::where('enabled', 1)
            ->pluck('name', 'id');

        $this->info("🏅 Iniciando Arbeitnow para {$certifications->count()} certificaciones");

        foreach ($certifications as $certId => $certName) {
            $this->warn("\n💡 Procesando certificación: {$certName}");

            $totalFound = 0;
            $totalNew  = 0;
            $countries = [];
            $modalities = [];

            try {
                // 🔎 Búsqueda directa
                $response = Http::timeout(25)->get(
                    'https://www.arbeitnow.com/api/job-board-api',
                    ['search' => $certName]
                );

                $jobs = $response->json('data') ?? [];

                // 🔁 Fallback: búsqueda global + filtro texto
                if (count($jobs) === 0) {
                    $fallback = Http::timeout(25)->get(
                        'https://www.arbeitnow.com/api/job-board-api'
                    );

                    $jobs = collect($fallback->json('data') ?? [])
                        ->filter(function ($job) use ($certName) {
                            $text = strtolower(
                                strip_tags(
                                    ($job['title'] ?? '') . ' ' .
                                    ($job['description'] ?? '')
                                )
                            );

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
                        // 🔗 Asociar certificación si no existe
                        $existing->certifications()
                            ->syncWithoutDetaching([$certId]);
                        continue;
                    }

                    // 📍 Ubicación
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
                        } else {
                            $this->stats['skipped']++;
                            continue;
                        }
                    }

                    $offer = JobOffer::create([
                        'title'        => $job['title'] ?? 'N/A',
                        'company'      => $job['company_name'] ?? null,
                        'country'      => $country,
                        'city'         => $city,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'modality'     => $this->detectModality(
                            $location,
                            $job['remote'] ?? false
                        ),
                        'requirements' => strip_tags($job['description'] ?? null),
                        'source'       => 'Arbeitnow',
                        'external_id'  => $externalId,
                        'url'          => $job['url'] ?? null,
                        'published_at' => Carbon::parse($job['created_at'] ?? now()),
                        'region'       => RegionHelper::fromCountry($country),
                    ]);

                    // 🔗 Pivot certification
                    $offer->certifications()
                        ->syncWithoutDetaching([$certId]);

                    $totalNew++;

                    $countries[$country] =
                        ($countries[$country] ?? 0) + 1;

                    $modalities[$offer->modality] =
                        ($modalities[$offer->modality] ?? 0) + 1;
                }

                // 📊 Métrica diaria (MISMO patrón Adzuna)
                CertificationMetric::firstOrCreate(
                    [
                        'certification_id' => $certId,
                        'run_date'         => now()->toDateString(),
                        'source'           => 'Arbeitnow',
                    ],
                    [
                        'certification_name' => $certName,
                        'jobs_found_count'   => $totalFound,
                        'jobs_new_count'     => $totalNew,
                        'countries_breakdown'=> $countries,
                        'modality_breakdown' => $modalities,
                    ]
                );

                $this->info("✅ {$certName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");

            } catch (\Throwable $e) {
                Log::error("Arbeitnow Cert {$certName}: {$e->getMessage()}");
            }

            sleep(1);
        }

        $this->info("\n🎯 Proceso Arbeitnow por certificaciones completado");
    }

    /* ================= HELPERS ================= */

    protected function extractCity(?string $location): ?string
    {
        if (!$location) return null;
        return trim(explode(',', $location)[0]);
    }

    protected function detectCountryCode(string $location, bool $remote): ?string
    {
        if ($remote) return 'remote';

        $loc = strtolower($location);

        return match (true) {
            str_contains($loc, 'germany') => 'de',
            str_contains($loc, 'spain')   => 'es',
            str_contains($loc, 'france')  => 'fr',
            str_contains($loc, 'italy')   => 'it',
            str_contains($loc, 'netherlands') => 'nl',
            str_contains($loc, 'poland')  => 'pl',
            str_contains($loc, 'uk'),
            str_contains($loc, 'london')  => 'gb',
            str_contains($loc, 'ireland') => 'ie',
            str_contains($loc, 'switzerland') => 'ch',
            default => null,
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city) {
            $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn ($q) =>
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
        $loc = strtolower($location);

        return match (true) {
            $remote,
            str_contains($loc, 'remote'),
            str_contains($loc, 'home office') => 'remote',

            str_contains($loc, 'hybrid') => 'hybrid',

            default => 'no_remote',
        };
    }
}
