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
 use App\Services\SourceStatusService;
 use App\Services\ScraperRunService;
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
    $baseUrl = 'https://www.getonbrd.com/api/v0/search/jobs';

    $run = ScraperRunService::start(
        $this->signature,
        'GetOnBoard',
        'certifications'
    );

    $source = 'getonboard_certifications';

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

        $certifications = Certification::where('enabled', 1)
            ->whereNotNull('keywords')
            ->get(['id', 'name', 'keywords']);

        $this->info(
            "🏅 GetOnBoard | {$certifications->count()} certificaciones | {$pages} páginas"
        );

        foreach ($certifications as $cert) {

            $certId   = $cert->id;
            $certName = $cert->name;

            // 🔑 keywords
            $keywords = collect($cert->keywords)
                ->map(fn ($k) => strtolower(trim($k)))
                ->filter()
                ->values();

            if ($keywords->isEmpty()) {

                $this->warn(
                    "⚠️ {$certName} sin keywords válidos"
                );

                continue;
            }

            $this->warn("\n💡 Certificación: {$certName}");

            $this->line(
                "🔑 Keywords: " .
                implode(', ', $keywords->toArray())
            );

            $totalFound   = 0;
            $totalNew     = 0;
            $totalSkipped = 0;

            $countries  = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {

                $url =
                    "{$baseUrl}?page={$page}&per_page=100";

                try {

                    $response = Http::retry(3, 2000)
                        ->withHeaders([
                            'User-Agent' =>
                                'Mozilla/5.0 (compatible; ObservatorioISIL/1.0)',

                            'Accept' =>
                                'application/json',
                        ])
                        ->timeout(25)
                        ->get($url);

                } catch (\Throwable $e) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "Connection exception page {$page}"
                    );

                    Log::error($e);

                    continue;
                }

                if ($response->failed()) {

                    SourceStatusService::connectionFailed(
                        $source,
                        "HTTP failed page {$page}"
                    );

                    $this->warn(
                        "❌ API falló página {$page} | status {$response->status()}"
                    );

                    Log::warning('GetOnBoard API error', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);

                    continue;
                }

                $connectionOk = true;

                $jobs = $response->json('data') ?? [];

                if (empty($jobs)) {

                    $this->line(
                        "ℹ️ Sin resultados página {$page}"
                    );

                    continue;
                }

                // ✅ evitar N+1
                $existingIds = JobOffer::whereIn(
                    'external_id',
                    collect($jobs)->pluck('id')->filter()
                )
                ->where('source', 'GetOnBoard')
                ->pluck('id', 'external_id');

                foreach ($jobs as $job) {

                    try {

                        $attr = $job['attributes'] ?? [];

                        $title =
                            $attr['title'] ?? '';

                        $company =
                            $attr['company']['data']['attributes']['name']
                            ?? null;

                        $country =
                            $attr['countries'][0] ?? null;

                        $city =
                            $attr['city'] ?? null;

                        $modality =
                            $attr['remote_modality']
                            ?? 'unknown';

                        $urlJob =
                            $job['links']['public_url']
                            ?? null;

                        $externalId =
                            $job['id'] ?? null;

                        $text = strtolower(
                            strip_tags(
                                $title . ' ' .
                                ($attr['description'] ?? '') . ' ' .
                                ($attr['benefits'] ?? '')
                            )
                        );

                        // 🔎 matching keywords
                        $matches = $keywords->filter(
                            fn ($k) =>
                                str_contains($text, $k)
                        );

                        if ($matches->isEmpty()) {
                            continue;
                        }

                        $totalFound++;

                        $countries[$country] =
                            ($countries[$country] ?? 0) + 1;

                        $modalities[$modality] =
                            ($modalities[$modality] ?? 0) + 1;

                        // 📍 coords
                        [$finalCity, $lat, $lng] =
                            $this->getCoordsFromCountry(
                                $city,
                                $country
                            );

                        if (!$lat || !$lng) {

                            $totalSkipped++;
                            $totalSkippedAll++;

                            continue;
                        }

                        // 🔁 duplicado
                        if (
                            $externalId &&
                            isset($existingIds[$externalId])
                        ) {

                            $existing = JobOffer::find(
                                $existingIds[$externalId]
                            );

                            if ($existing) {

                                $existing->certifications()
                                    ->syncWithoutDetaching([
                                        $certId
                                    ]);
                            }

                            continue;
                        }

                        $countryNorm =
                            $this->normalizeCountry($country);

                        $offer = JobOffer::create([

                            'title' =>
                                $title ?: 'N/A',

                            'company' =>
                                $company,

                            'country' =>
                                $countryNorm,

                            'region' =>
                                RegionHelper::fromCountry(
                                    $countryNorm
                                ),

                            'city' =>
                                $finalCity,

                            'latitude' =>
                                $lat,

                            'longitude' =>
                                $lng,

                            'modality' =>
                                $modality,

                            'source' =>
                                'GetOnBoard',

                            'external_id' =>
                                $externalId,

                            'url' =>
                                $urlJob,

                            'published_at' =>
                                isset($attr['published_at'])
                                    ? Carbon::createFromTimestamp(
                                        $attr['published_at']
                                    )
                                    : now(),
                        ]);

                        $offer->certifications()
                            ->syncWithoutDetaching([
                                $certId
                            ]);

                        $totalNew++;

                        $this->line(
                            "✅ {$title} ({$countryNorm}) → [" .
                            implode(', ', $matches->toArray()) .
                            "]"
                        );

                    } catch (\Throwable $e) {

                        $totalSkipped++;
                        $totalSkippedAll++;

                        Log::error(
                            "💥 {$certName}: {$e->getMessage()}"
                        );
                    }
                }

                sleep(random_int(2, 4));
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
            "\n🎯 GetOnBoard por certificaciones COMPLETADO"
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
