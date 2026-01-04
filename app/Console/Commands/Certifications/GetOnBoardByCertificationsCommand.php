<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;

class GetOnBoardByCertificationsCommand extends Command
{
    protected $signature = 'getonboard:certifications {--pages=1}';

    protected $description = '🏅 Scrapea GetOnBoard usando keywords por certificación (API-safe).';

    protected $capitalMap = [
        'Argentina' => ['city' => 'Buenos Aires', 'lat' => -34.6037, 'lng' => -58.3816],
        'Bolivia'   => ['city' => 'La Paz', 'lat' => -16.5, 'lng' => -68.15],
        'Chile'     => ['city' => 'Santiago', 'lat' => -33.4489, 'lng' => -70.6693],
        'Colombia'  => ['city' => 'Bogotá', 'lat' => 4.711, 'lng' => -74.0721],
        'Ecuador'   => ['city' => 'Quito', 'lat' => -0.1807, 'lng' => -78.4678],
        'México'    => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'Perú'      => ['city' => 'Lima', 'lat' => -12.0464, 'lng' => -77.0428],
        'Uruguay'   => ['city' => 'Montevideo', 'lat' => -34.9011, 'lng' => -56.1645],
        'Venezuela' => ['city' => 'Caracas', 'lat' => 10.4806, 'lng' => -66.9036],
    ];

    public function handle()
    {
        $pages = (int) $this->option('pages');

        $certifications = Certification::where('enabled', 1)
            ->whereNotNull('keywords')
            ->get(['id', 'name', 'keywords']);

        $this->info("🏅 GetOnBoard | {$certifications->count()} certificaciones | {$pages} páginas");

        foreach ($certifications as $cert) {

            $certId   = $cert->id;
            $certName = $cert->name;

            // 🔑 keywords como array (cast seguro)
            $keywords = collect($cert->keywords)
                ->map(fn ($k) => strtolower(trim($k)))
                ->filter()
                ->values();

            if ($keywords->isEmpty()) {
                $this->warn("⚠️ {$certName} sin keywords válidos");
                continue;
            }

            $this->warn("\n💡 Certificación: {$certName}");
            $this->line("🔑 Keywords: " . implode(', ', $keywords->toArray()));

            $totalFound   = 0;
            $totalNew     = 0;
            $totalSkipped = 0;
            $countries    = [];
            $modalities   = [];

            // 👉 keyword principal para query API
            $primaryKeyword = urlencode($keywords->first());

            for ($page = 1; $page <= $pages; $page++) {

             $url = "https://www.getonbrd.com/api/v0/search/jobs?page={$page}&per_page=100";


                try {
                    $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (compatible; ObservatorioISIL/1.0)',
                            'Accept'     => 'application/json',
                        ])
                        ->timeout(25)
                        ->get($url);

                    if ($response->failed()) {
                        $this->warn("❌ API falló página {$page} | status {$response->status()}");
                        Log::warning('GetOnBoard API error', [
                            'status' => $response->status(),
                            'body'   => $response->body(),
                        ]);
                        continue;
                    }

                    $jobs = $response->json('data') ?? [];
                    if (empty($jobs)) {
                        $this->line("ℹ️ Sin resultados página {$page}");
                        continue;
                    }

                    foreach ($jobs as $job) {

                        $attr = $job['attributes'] ?? [];

                        $title   = $attr['title'] ?? '';
                        $company = $attr['company']['data']['attributes']['name'] ?? null;
                        $country = $attr['countries'][0] ?? null;
                        $city    = $attr['city'] ?? null;
                        $modality = $attr['remote_modality'] ?? 'unknown';
                        $urlJob  = $job['links']['public_url'] ?? null;
                        $externalId = $job['id'] ?? null;

                        $text = strtolower(
                            strip_tags(
                                $title . ' ' .
                                ($attr['description'] ?? '') . ' ' .
                                ($attr['benefits'] ?? '')
                            )
                        );

                        // 🔎 matching real (mínimo 1 keyword)
                        $matches = $keywords->filter(
                            fn ($k) => str_contains($text, $k)
                        );

                        if ($matches->isEmpty()) {
                            continue;
                        }

                        $totalFound++;

                        // 📊 métricas
                        $countries[$country] = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                        // 📍 coords
                        [$finalCity, $lat, $lng] =
                            $this->getCoordsFromCountry($city, $country);

                        if (!$lat || !$lng) {
                            $totalSkipped++;
                            continue;
                        }

                        // 🔁 duplicado
                        $existing = JobOffer::where('source', 'GetOnBoard')
                            ->where('external_id', $externalId)
                            ->first();

                        if ($existing) {
                            $existing->certifications()
                                ->syncWithoutDetaching([$certId]);
                            continue;
                        }

                        $countryNorm = $this->normalizeCountry($country);

                        $offer = JobOffer::create([
                            'title'        => $title ?: 'N/A',
                            'company'      => $company,
                            'country'      => $countryNorm,
                            'region'       => RegionHelper::fromCountry($countryNorm),
                            'city'         => $finalCity,
                            'latitude'     => $lat,
                            'longitude'    => $lng,
                            'modality'     => $modality,
                            'source'       => 'GetOnBoard',
                            'external_id'  => $externalId,
                            'url'          => $urlJob,
                            'published_at' => isset($attr['published_at'])
                                ? Carbon::createFromTimestamp($attr['published_at'])
                                : now(),
                        ]);

                        $offer->certifications()
                            ->syncWithoutDetaching([$certId]);

                        $totalNew++;

                        $this->line(
                            "✅ {$title} ({$countryNorm}) → [" .
                            implode(', ', $matches->toArray()) . "]"
                        );
                    }

                    sleep(random_int(2, 4));

                } catch (\Throwable $e) {
                    Log::error("💥 {$certName} página {$page}: {$e->getMessage()}");
                }
            }

            CertificationMetric::updateOrCreate(
                [
                    'certification_id' => $certId,
                    'run_date'         => Carbon::today(),
                    'source'           => 'GetOnBoard',
                ],
                [
                    'certification_name' => $certName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $this->info(
                "📊 {$certName}: {$totalNew} nuevas | {$totalSkipped} omitidas"
            );
        }

        $this->info("\n🎯 GetOnBoard por certificaciones COMPLETADO");
    }

    /* ================= HELPERS ================= */

    protected function normalizeCountry(?string $country): ?string
    {
        if (!$country) return null;

        return match (strtolower($country)) {
            'peru' => 'Perú',
            'mexico' => 'México',
            'colombia' => 'Colombia',
            'argentina' => 'Argentina',
            'uruguay' => 'Uruguay',
            'ecuador' => 'Ecuador',
            'venezuela' => 'Venezuela',
            'bolivia' => 'Bolivia',
            'chile' => 'Chile',
            default => ucfirst($country),
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $country)
    {
        if ($city && strtolower($city) !== 'remoto') {
            [$lat, $lng] = $this->getCoords($city, $country);
            if ($lat && $lng) {
                return [$city, $lat, $lng];
            }
        }

        if ($country && isset($this->capitalMap[$country])) {
            $cap = $this->capitalMap[$country];
            return [$cap['city'], $cap['lat'], $cap['lng']];
        }

        return [$city ?? 'Desconocido', null, null];
    }

    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::timeout(10)->get(
                'https://nominatim.openstreetmap.org/search',
                [
                    'q' => "{$city}, {$country}",
                    'format' => 'json',
                    'limit' => 1,
                ]
            );

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $e) {
            Log::warning("🌍 Geocode {$city}, {$country}: {$e->getMessage()}");
        }

        return [null, null];
    }
}
