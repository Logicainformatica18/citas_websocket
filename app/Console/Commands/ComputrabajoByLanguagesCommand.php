<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use App\Console\Commands\Traits\JobFilterTrait; // 👈 importa el trait
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;
use App\Models\MarketEntity;
use App\Models\MarketEntityMetric;

class ComputrabajoByLanguagesCommand extends Command
{
    use JobFilterTrait; // 👈 usa el trait aquí
    protected $signature = 'computrabajo:languages {--pages=3}';
    protected $description = '🌎 Scrapea Computrabajo por cada lenguaje (ej: programador-python) y guarda métricas con geolocalización.';

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

protected function normalizeUrl(string $url): string
{
    // quitar hash (#)
    $url = explode('#', $url)[0];

    // quitar query params (por si acaso)
    $url = explode('?', $url)[0];

    // limpiar slash final
    return rtrim($url, '/');
}
    protected function scrapeJobDetail(string $url): array
{
    try {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->timeout(20)->get($url);

        if (!$response->ok()) {
            return [];
        }

        $crawler = new Crawler($response->body());

        $description = null;
        $salaryText = null;

        // 📄 Descripción
       if ($crawler->filter('.description_offer .t_word_wrap')->count()) {
    $description = trim(
        $crawler->filter('.description_offer .t_word_wrap')->text()
    );
}

        // 💰 Buscar salario más preciso
        $crawler->filter('span')->each(function ($node) use (&$salaryText) {
            $text = strtolower($node->text());

            if (
                str_contains($text, 's/.') ||
                str_contains($text, '$') ||
                str_contains($text, 'usd')
            ) {
                if (str_contains($text, 'mensual') || str_contains($text, 'salario')) {
                    $salaryText = trim($node->text());
                }
            }
        });

        return [
            'description' => $description,
            'salary_text' => $salaryText,
        ];

    } catch (\Throwable $e) {
        Log::warning("⚠️ Error scraping detalle: " . $e->getMessage());
        return [];
    }
}
    protected function getLanguageFromMarketEntity($marketEntityId, $languageName)
    {
        return Language::firstOrCreate(
            ['market_entity_id' => $marketEntityId],
            ['name' => $languageName]
        );
    }
 public function handle()
{
    $run = ScraperRunService::start(
        $this->signature,
        'Computrabajo',
        'languages'
    );

    $totalFoundAll = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll = 0;

    try {

        $lastLanguageId = MarketEntityMetric::where('source', 'Computrabajo')
            ->orderByDesc('created_at')
            ->value('market_entity_id');

        $baseQuery = MarketEntity::where('entity_type', 'language')
            ->orderBy('id');

        $languagesQuery = clone $baseQuery;

        if ($lastLanguageId) {
            $languagesQuery->where('id', '>', $lastLanguageId);
        }

        $languages = $languagesQuery->get();

        if ($languages->isEmpty()) {
            $languages = $baseQuery->get();
        }

        $pages = (int) $this->option('pages');

        $this->info("🌐 Scrapeando Computrabajo para {$languages->count()} lenguajes ({$pages} páginas por país)...");

        foreach ($languages as $marketEntity) {

            $marketEntityId = $marketEntity->id;
            $langName = $marketEntity->name;

            $language = $this->getLanguageFromMarketEntity($marketEntityId, $langName);
            $langId = $language->id;

            $this->warn("\n💡 Lenguaje actual: {$langName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

            $slugLang = $this->makeSearchSlug($langName, null);

            foreach ($this->countryMap as $code => $country) {

                $this->line("🌍 País: {$country}");

                for ($i = 1; $i <= $pages; $i++) {

                    $url = "https://{$code}.computrabajo.com/trabajo-de-programador-{$slugLang}?p={$i}";
                    $this->line("🔗 Página {$i}: {$url}");

                    try {

                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0',
                            'Accept-Language' => 'es-ES,es;q=0.9',
                        ])->timeout(25)->get($url);

                        if ($response->failed()) {
                            $this->warn("❌ Falló la página {$i} en {$country}");
                            continue;
                        }

                        $crawler = new Crawler($response->body());
                        $offers = $crawler->filter('article[class*="box_offer"]');

                        if ($offers->count() === 0) {
                            continue;
                        }

                        $offers->each(function (Crawler $offer) use (
                            &$totalFound,
                            &$totalNew,
                            &$countries,
                            &$modalities,
                            &$totalInsertedAll,
                            &$totalSkippedAll,
                            $country,
                            $langName,
                            $code,
                            $langId,
                            $marketEntityId
                        ) {

                            try {

                                // ✅ Validación
                                if (!$offer->filter('h2 a')->count()) {
                                    return;
                                }

                                $title = trim($offer->filter('h2 a')->text());

                                // 🚫 FILTRO
                                if (!$this->isTechRelated($title)) {
                                    $totalSkippedAll++;
                                    return;
                                }

                                // 💰 salario listado
                                $salaryText = null;

                                $offer->filter('div.fs13.mt15 span.dIB')->each(function ($node) use (&$salaryText) {
                                    if ($node->filter('.i_salary')->count()) {
                                        $salaryText = trim($node->text());
                                    }
                                });

                                // 🔗 URL
                             $href = $offer->filter('h2 a')->attr('href');
$urlJob = "https://{$code}.computrabajo.com{$href}";
$urlJob = $this->normalizeUrl($urlJob);

                                // 🏢 empresa
                                $company = $offer->filter('p.fc_base a')->count()
                                    ? trim($offer->filter('p.fc_base a')->text())
                                    : null;

                                // 📍 ubicación
                                $city = $this->extractCityFromUrl($urlJob);
                                [$lat, $lng] = $this->getCoords($city, $country);

                                // 🧭 modalidad
                                $text = strtolower($title . ' ' . $city);

                                $modality = match (true) {
                                    str_contains($text, 'remote'),
                                    str_contains($text, 'remoto'),
                                    str_contains($text, 'teletrabajo'),
                                    str_contains($text, 'home office') => 'remote',

                                    str_contains($text, 'hybrid'),
                                    str_contains($text, 'híbrido'),
                                    str_contains($text, 'mixto') => 'hybrid',

                                    str_contains($text, 'presencial'),
                                    str_contains($text, 'oficina'),
                                    str_contains($text, 'onsite'),
                                    str_contains($text, 'on site') => 'presencial',

                                    default => 'no_precisa',
                                };

                                $totalFound++;
                                $countries[$country] = ($countries[$country] ?? 0) + 1;
                                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                                // 🔍 duplicados
                                $existingOffer = JobOffer::where('source', 'Computrabajo')
                                    ->where('url', $urlJob)
                                    ->first();

                                if ($existingOffer) {
                                    $existingOffer->languages()->syncWithoutDetaching([
                                        $langId => [
                                            'market_entity_id' => $marketEntityId
                                        ]
                                    ]);
                                    $totalSkippedAll++;
                                    return;
                                }




                              [$salaryMin, $salaryMax, $currency] = $this->parseSalary($salaryText, $code);

                                $countryNorm = match (strtolower($country)) {
                                    'peru' => 'Perú',
                                    'mexico' => 'México',
                                    default => ucfirst($country),
                                };

                                // 💾 guardar
                                $offerModel = JobOffer::create([
                                    'title' => $title,
                                    'company' => $company,
                                    'country' => $countryNorm,
                                    'region' => RegionHelper::fromCountry($countryNorm),
                                    'state_code' => strtoupper($code),
                                    'city' => $city,
                                    'latitude' => $lat,
                                    'longitude' => $lng,
                                    'modality' => $modality,
                                    'source' => 'Computrabajo',
                                    'search_query' => $langName,
                                    'url' => $urlJob,
                                    'salary_min' => $salaryMin,
                                    'salary_max' => $salaryMax,
                                    'currency' => $currency,
                                //    'description' => $detailData['description'] ?? null,
                                    'published_at' => now(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                $offerModel->languages()->syncWithoutDetaching([
                                    $langId => [
                                        'market_entity_id' => $marketEntityId
                                    ]
                                ]);

                                $totalNew++;
                                $totalInsertedAll++;

                            } catch (\Throwable $e) {
                                $totalSkippedAll++;
                                Log::warning("⚠️ Error oferta {$langName}: " . $e->getMessage());
                            }
                        });

                        usleep(random_int(500000, 1500000));

                    } catch (\Throwable $e) {
                        Log::warning("💥 Error página {$country}: " . $e->getMessage());
                    }
                }

                sleep(4);
            }

            MarketEntityMetric::updateOrCreate(
                [
                    'market_entity_id' => $marketEntityId,
                    'run_date' => Carbon::today(),
                    'source' => 'Computrabajo',
                ],
                [
                    'entity_name' => $langName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count' => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $totalFoundAll += $totalFound;
            $this->info("📊 {$langName}: {$totalNew} nuevas / {$totalFound} totales");
        }

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("\n🎯 Scraping + métricas completado exitosamente.");

    } catch (\Throwable $e) {
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}


    protected function makeSearchSlug(string $langName, ?string $context = null): string
    {
        $slug = strtolower(trim($langName));

        // 🧠 Casos especiales
        $slug = str_replace(['c#', 'c++', '.net'], ['c-sharp', 'c-plus-plus', 'dotnet'], $slug);

        // Limpieza general
        $slug = str_replace(['#', '+', '.', ' ', '/', '\\'], ['sharp', 'plus', '', '-', '-', '-'], $slug);

        // Agregar contexto si existe
        if ($context) {
            $contextSlug = str_replace(' ', '-', strtolower($context));
            return "{$contextSlug}-{$slug}";
        }

        return $slug;
    }



    protected function extractCityFromUrl($url)
    {
        if (preg_match('/-en-([a-z-]+)-[A-Z0-9]+/', $url, $match)) {
            return ucwords(str_replace('-', ' ', $match[1]));
        }
        return 'Remote';
    }

    protected function mapModality(string $text): string
    {
        $t = strtolower($text);

        // 🟡 1. HÍBRIDO (combinaciones claras)
        if (
            (str_contains($t, 'presencial') && str_contains($t, 'remoto')) ||
            (str_contains($t, 'presencial') && str_contains($t, 'teletrabajo')) ||
            (str_contains($t, 'presencial') && str_contains($t, 'home office')) ||
            str_contains($t, 'híbrido') ||
            str_contains($t, 'hybrid') ||
            str_contains($t, 'mixto') ||
            str_contains($t, 'parcial')
        ) {
            return 'hybrid';
        }

        // 🔵 2. REMOTO PURO
        if (
            str_contains($t, 'remoto') ||
            str_contains($t, 'remote') ||
            str_contains($t, 'teletrabajo') ||
            str_contains($t, 'home office') ||
            str_contains($t, 'work from home') ||
            str_contains($t, 'anywhere')
        ) {
            return 'remote';
        }

        // 🟢 3. PRESENCIAL EXPLÍCITO
        if (
            str_contains($t, 'presencial') ||
            str_contains($t, 'oficina') ||
            str_contains($t, 'onsite') ||
            str_contains($t, 'on site') ||
            str_contains($t, 'en sede') ||
            str_contains($t, 'in situ')
        ) {
            return 'presencial';
        }

        // ⚪ 4. NO PRECISA (no hay señal clara)
        return 'no_precisa';
    }


    protected function getCoords($city, $country)
    {
        if (!$city || strtolower($city) === 'remote') {
            return [self::DEFAULT_LAT, self::DEFAULT_LNG];
        }

        try {
            $res = Http::withHeaders([
                'User-Agent' => 'LaravelJobScraper/1.0'
            ])->timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                        'q' => "$city, $country",
                        'format' => 'json',
                        'limit' => 1,
                    ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("⚠️ Error Nominatim {$city}: " . $th->getMessage());
        }

        return [self::DEFAULT_LAT, self::DEFAULT_LNG];
    }
    protected function parseSalary(?string $text, ?string $countryCode): array
    {
        if (!$text)
            return [null, null, $this->currencyMap[$countryCode] ?? null];

        $currency = match (true) {
            str_contains($text, 'US$') => 'USD',
            str_contains($text, 'S/') => 'PEN',
            str_contains($text, '$') => $this->currencyMap[$countryCode] ?? 'USD',
            default => $this->currencyMap[$countryCode] ?? null,
        };

        preg_match_all('/[\d.,]+/', $text, $matches);
        if (empty($matches[0]))
            return [null, null, $currency];

        $values = array_map(
            fn($v) => floatval(str_replace(',', '', preg_replace('/[^\d,\.]/', '', $v))),
            $matches[0]
        );

        $min = $values[0] ?? null;
        $max = $values[1] ?? $min;

        return [$min, $max, $currency];
    }

}
