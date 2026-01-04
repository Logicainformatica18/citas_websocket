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
        $certifications = Certification::where('enabled', 1)
            ->select('id', 'name', 'keyword')
            ->get();

        $pages = (int) $this->option('pages');

        $this->info("🏅 Computrabajo | {$certifications->count()} certificaciones | {$pages} páginas");

        foreach ($certifications as $cert) {

            $certId   = $cert->id;
            $certName = $cert->name;
            $keyword  = strtolower($cert->keyword ?? $cert->name);

            $this->warn("\n💡 Certificación: {$certName} | keyword: {$keyword}");

            $slug = $this->makeSearchSlug($keyword);

            $totalFound = 0;
            $totalNew   = 0;
            $countries  = [];
            $modalities = [];

            foreach ($this->countryMap as $code => $country) {

                $this->line("🌍 País: {$country}");

                for ($page = 1; $page <= $pages; $page++) {

                    $url = "https://{$code}.computrabajo.com/trabajo-de-{$slug}?p={$page}";
                    $this->line("🔗 {$url}");

                    try {
                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0',
                            'Accept-Language' => 'es-ES,es;q=0.9',
                        ])->timeout(25)->get($url);

                        if ($response->failed()) continue;

                        $crawler = new Crawler($response->body());
                        $offers  = $crawler->filter('article[class*="box_offer"]');

                        if ($offers->count() === 0) continue;

                        $offers->each(function (Crawler $offer) use (
                            $certId,
                            $certName,
                            $keyword,
                            $country,
                            $code,
                            &$totalFound,
                            &$totalNew,
                            &$countries,
                            &$modalities
                        ) {
                            try {
                                $title = trim($offer->filter('h2 a')->text());

                                // 🚫 Filtrar no-tech
                                if (!$this->isTechRelated($title)) return;

                                // 🔎 Validación por keyword (flexible)
                                if (!str_contains(strtolower($title), $keyword)) return;

                                $company = $offer->filter('p.fc_base a')->count()
                                    ? trim($offer->filter('p.fc_base a')->text())
                                    : null;

                                $href   = $offer->filter('h2 a')->attr('href');
                                $urlJob = "https://{$code}.computrabajo.com{$href}";

                                $city = $this->extractCityFromUrl($urlJob);
                                [$lat, $lng] = $this->getCoords($city, $country);

                                $modality = $this->mapModality($title . ' ' . $city);

                                // 💰 Salario
                                $salaryText = null;
                                $offer->filter('p.fc_aux')->each(function ($node) use (&$salaryText) {
                                    $text = trim($node->text());
                                    if (preg_match('/(\$|S\/|US\$)/', $text)) {
                                        $salaryText = $text;
                                    }
                                });

                                [$salaryMin, $salaryMax, $currency] =
                                    $this->parseSalary($salaryText, $code);

                                $totalFound++;

                                $existing = JobOffer::where('source', 'Computrabajo')
                                    ->where('url', $urlJob)
                                    ->first();

                                if ($existing) {
                                    $existing->certifications()
                                        ->syncWithoutDetaching([$certId]);
                                    return;
                                }

                                $countryNorm = match (strtolower($country)) {
                                    'peru' => 'Perú',
                                    'mexico' => 'México',
                                    'colombia' => 'Colombia',
                                    'argentina' => 'Argentina',
                                    'uruguay' => 'Uruguay',
                                    'ecuador' => 'Ecuador',
                                    'venezuela' => 'Venezuela',
                                    'bolivia' => 'Bolivia',
                                    default => ucfirst($country),
                                };

                                $job = JobOffer::create([
                                    'title'        => $title,
                                    'company'      => $company,
                                    'country'      => $countryNorm,
                                    'region'       => RegionHelper::fromCountry($countryNorm),
                                    'state_code'   => strtoupper($code),
                                    'city'         => $city,
                                    'latitude'     => $lat,
                                    'longitude'    => $lng,
                                    'modality'     => $modality,
                                    'source'       => 'Computrabajo',
                                    'url'          => $urlJob,
                                    'salary_min'   => $salaryMin,
                                    'salary_max'   => $salaryMax,
                                    'currency'     => $currency,
                                    'published_at' => now(),
                                ]);

                                $job->certifications()
                                    ->syncWithoutDetaching([$certId]);

                                $totalNew++;

                                $countries[$countryNorm] =
                                    ($countries[$countryNorm] ?? 0) + 1;

                                $modalities[$modality] =
                                    ($modalities[$modality] ?? 0) + 1;

                                $this->line("✅ {$title} ({$countryNorm} - {$city})");

                            } catch (\Throwable $e) {
                                Log::warning("⚠️ {$certName}: {$e->getMessage()}");
                            }
                        });

                        usleep(random_int(500000, 1500000));

                    } catch (\Throwable $e) {
                        Log::error("💥 {$country}: {$e->getMessage()}");
                    }
                }

                sleep(3);
            }

            CertificationMetric::updateOrCreate(
                [
                    'certification_id' => $certId,
                    'run_date'         => Carbon::today(),
                    'source'           => 'Computrabajo',
                ],
                [
                    'certification_name' => $certName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $this->info("📊 {$certName}: {$totalNew} nuevas / {$totalFound} totales");
        }

        $this->info("\n🎯 Computrabajo por keyword COMPLETADO");
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
