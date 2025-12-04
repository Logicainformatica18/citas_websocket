<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Console\Commands\Traits\JobFilterTrait; // 👈 IMPORTANTE

class ComputrabajoByCompetenciesCommand extends Command
{
    use JobFilterTrait; // 👈 ACTIVA EL FILTRO TECH

    protected $signature = 'computrabajo:competencies {--pages=2}';
    protected $description = '🌎 Scrapea Computrabajo buscando ofertas por competencias (skills) con geolocalización y métricas.';

    protected $countryMap = [
        'pe' => 'Peru',
        'mx' => 'Mexico',
        'co' => 'Colombia',
        'ar' => 'Argentina',
        'uy' => 'Uruguay',
        'ec' => 'Ecuador',
        'bo' => 'Bolivia',
        've' => 'Venezuela',
    ];

    protected $currencyMap = [
        'pe' => 'PEN',
        'mx' => 'MXN',
        'co' => 'COP',
        'ar' => 'ARS',
        'uy' => 'UYU',
        'ec' => 'USD',
        'bo' => 'BOB',
        've' => 'VES',
    ];

    const DEFAULT_LAT = -12.046374;
    const DEFAULT_LNG = -77.042793;

    public function handle()
    {
        $pages = (int) $this->option('pages');

      $competencies = Competency::select('id', 'name', 'description_en')
    ->whereNotNull('career_id')
    ->get();


        $this->info("🔍 Scrapeando Computrabajo para {$competencies->count()} competencias...");

        foreach ($competencies as $comp) {

            $this->warn("\n💡 Competencia actual: {$comp->name}");

            $searchString = strtolower(trim($comp->name));
            $slug = str_replace([' ', '/', '+', '#'], '-', $searchString);

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

            foreach ($this->countryMap as $code => $country) {

                $this->line("🌍 País: {$country}");

                for ($p = 1; $p <= $pages; $p++) {

                    $url = "https://{$code}.computrabajo.com/trabajo-de-{$slug}?p={$p}";
                    $this->line("🔗 {$url}");

                    try {
                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0',
                            'Accept-Language' => 'es-ES,es;q=0.9',
                        ])->timeout(25)->get($url);

                        if ($response->failed()) continue;

                        $crawler = new Crawler($response->body());
                        $offers = $crawler->filter('article[class*="box_offer"]');

                        if ($offers->count() === 0) {
                            $this->warn("⚠️ Sin ofertas para {$comp->name} en {$country}");
                            continue;
                        }

                        $offers->each(function (Crawler $offer) use (&$totalFound, &$totalNew, &$countries, &$modalities, $country, $code, $comp) {

                            try {
                                $title = trim($offer->filter('h2 a')->text());

                                // ⚠️ FILTRO TECNOLÓGICO OBLIGATORIO
                                if (!$this->isTechRelated($title)) {
                                    $this->warn("⛔ Ignorado (no tech): {$title}");
                                    return;
                                }

                                $company = $offer->filter('p.fc_base a')->count()
                                    ? trim($offer->filter('p.fc_base a')->text())
                                    : null;

                                $href = $offer->filter('h2 a')->attr('href');
                                $jobUrl = "https://{$code}.computrabajo.com" . $href;

                                $city = $this->extractCityFromUrl($jobUrl);
                                [$lat, $lng] = $this->getCoords($city, $country);

                                $modality = $this->detectModality($title . ' ' . $city);

                                // EXTRAER SALARIO
                                $salaryText = null;
                                $offer->filter('p.fc_aux')->each(function ($node) use (&$salaryText) {
                                    $txt = trim($node->text());
                                    if (preg_match('/(\$|S\/|US\$)/', $txt)) $salaryText = $txt;
                                });

                                [$salaryMin, $salaryMax, $currency] = $this->parseSalary($salaryText, $code);

                                $totalFound++;
                                $countries[$country] = ($countries[$country] ?? 0) + 1;
                                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                                // DUPLICADOS
                                $existing = JobOffer::where('source', 'Computrabajo')
                                    ->where('title', $title)
                                    ->where('company', $company)
                                    ->where('search_query', $comp->name)
                                    ->first();

                                if ($existing) {
                                    $existing->competencies()->syncWithoutDetaching([$comp->id]);
                                    return;
                                }

                                // NORMALIZAR PAÍS
                                $countryFormatted = match (strtolower($country)) {
                                    'peru' => 'Perú',
                                    'mexico' => 'México',
                                    default => ucfirst($country),
                                };

                                // CREAR OFERTA
                                $job = JobOffer::create([
                                    'title'        => $title,
                                    'company'      => $company,
                                    'country'      => $countryFormatted,
                                    'region'       => RegionHelper::fromCountry($countryFormatted),
                                    'state_code'   => strtoupper($code),
                                    'city'         => $city,
                                    'latitude'     => $lat,
                                    'longitude'    => $lng,
                                    'modality'     => $modality,
                                    'salary_min'   => $salaryMin,
                                    'salary_max'   => $salaryMax,
                                    'currency'     => $currency,
                                    'source'       => 'Computrabajo',
                                    'search_query' => $comp->name,
                                    'url'          => $jobUrl,
                                    'published_at' => now(),
                                ]);

                                // RELACIÓN PIVOT
                                $job->competencies()->syncWithoutDetaching([$comp->id]);

                                $totalNew++;
                                $this->line("✅ {$title} ({$city})");

                            } catch (\Throwable $e) {
                                Log::warning("⚠️ Error oferta {$comp->name}: " . $e->getMessage());
                            }
                        });

                        usleep(random_int(500000, 1500000));

                    } catch (\Throwable $e) {
                        $this->warn("💥 Error página {$p} en {$country}: " . $e->getMessage());
                    }
                }

                sleep(3);
            }

            // MÉTRICAS
            CompetencyMetric::updateOrCreate(
                [
                    'competency_id' => $comp->id,
                    'run_date'      => Carbon::today(),
                    'source'        => 'Computrabajo',
                ],
                [
                    'competency_name'    => $comp->name,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $this->info("📊 {$comp->name}: {$totalNew} nuevas / {$totalFound} totales");
        }

        $this->info("\n🎯 Scraping Computrabajo + Competencias COMPLETADO.");
    }

    // ───────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────

    protected function extractCityFromUrl($url)
    {
        if (preg_match('/-en-([a-z-]+)-[A-Z0-9]+/', $url, $m)) {
            return ucwords(str_replace('-', ' ', $m[1]));
        }
        return 'Remote';
    }

    protected function detectModality(string $text)
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'remoto'),
            str_contains($t, 'teletrabajo'),
            str_contains($t, 'home office') => 'fully_remote',

            str_contains($t, 'híbrido'),
            str_contains($t, 'mixto'),
            str_contains($t, 'presencial y remoto') => 'hybrid',

            str_contains($t, 'presencial'),
            str_contains($t, 'oficina') => 'no_remote',

            default => 'no_remote'
        };
    }

    protected function getCoords($city, $country)
    {
        try {
            $resp = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$city}, {$country}",
                'format' => 'json',
                'limit' => 1,
            ]);

            if ($resp->ok() && count($resp->json()) > 0) {
                $geo = $resp->json()[0];
                return [(float)$geo['lat'], (float)$geo['lon']];
            }
        } catch (\Throwable $e) {}

        return [self::DEFAULT_LAT, self::DEFAULT_LNG];
    }

    protected function parseSalary($text, $code)
    {
        if (!$text) return [null, null, $this->currencyMap[$code] ?? null];

        $currency = match (true) {
            str_contains($text, 'US$') => 'USD',
            str_contains($text, 'S/')  => 'PEN',
            str_contains($text, '$')   => $this->currencyMap[$code] ?? 'USD',
            default                    => $this->currencyMap[$code] ?? null,
        };

        preg_match_all('/[\d.,]+/', $text, $m);

        if (empty($m[0])) return [null, null, $currency];

        $vals = array_map(fn($v) => floatval(str_replace(',', '', $v)), $m[0]);

        return [
            $vals[0] ?? null,
            $vals[1] ?? ($vals[0] ?? null),
            $currency,
        ];
    }
}
