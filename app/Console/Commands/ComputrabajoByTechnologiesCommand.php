<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Models\City;
use App\Console\Commands\Traits\JobFilterTrait; // 👈 importa el trait
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;


class ComputrabajoByTechnologiesCommand extends Command
{
     use JobFilterTrait; // 👈 usa el trait aquí
    protected $signature = 'computrabajo:technologies {--country=} {--pages=3}';
    protected $description = '🌎 Scrapea Computrabajo por tecnología y registra métricas en technology_metrics con geolocalización.';

    protected $countryMap = [
       'pe' => 'Peru',
        'bo' => 'Bolivia',
        'ar' => 'Argentina',
        'uy' => 'Uruguay',
        'mx' => 'Mexico',
        'co' => 'Colombia',
        'ec' => 'Ecuador',
        've' => 'Venezuela',
          'cl' => 'Chile',
    ];

    const DEFAULT_LAT = -12.046374;
    const DEFAULT_LNG = -77.042793;

  public function handle()
{
    // ▶️ Iniciar RUN del scraper
    $run = ScraperRunService::start(
        $this->signature,
        'Computrabajo',
        'technologies'
    );

    try {

        // 🔹 Parámetros
        $countryOption = $this->option('country');
        $pages = (int) $this->option('pages');

        // 🔹 Acumuladores GLOBALES del run
        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        // 🔹 Selección de país o todos
        $activeCountries = $countryOption
            ? array_intersect_key($this->countryMap, [$countryOption => true])
            : $this->countryMap;

        if (empty($activeCountries)) {
            $this->error("❌ País no válido. Usa uno de: " . implode(', ', array_keys($this->countryMap)));
            return;
        }

        
        // 🔹 Tecnologías reales (vinculadas a carreras)
        $technologies = Technology::whereIn('technologies.id', function ($q) {
            $q->select('course_technology.technology_id')
              ->from('course_technology')
              ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
        })->pluck('name', 'id');

        $this->info(
            "🌎 Scrapeando " . count($technologies) .
            " tecnologías para: " . implode(', ', $activeCountries)
        );

        // 🔁 Loop principal por tecnología
        foreach ($technologies as $techId => $techName) {

            $this->warn("\n💡 Procesando tecnología: {$techName}");

            $totalFound = 0;
            $totalNew   = 0;
            $countries  = [];
            $modalities = [];

            $slugTech = $this->makeSearchSlug($techName);

            foreach ($activeCountries as $code => $country) {

                $this->line("🌍 País: {$country}");

                for ($i = 1; $i <= $pages; $i++) {

                    $url = "https://{$code}.computrabajo.com/trabajo-de-{$slugTech}?p={$i}";
                    $this->line("🔗 Página {$i}: {$url}");

                    try {
                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                        ])->timeout(12)->retry(2, 1500)->get($url);

                        if ($response->failed()) {
                            $this->warn("❌ Falló la página {$i} en {$country}");
                            continue;
                        }

                        $crawler = new Crawler($response->body());
                        $offers  = $crawler->filter('article[class*="box_offer"]');

                        if ($offers->count() === 0) {
                            continue;
                        }

                        $offers->each(function (Crawler $offer) use (
                            &$totalFound,
                            &$totalNew,
                            &$countries,
                            &$modalities,
                            &$totalSkippedAll,
                            $country,
                            $techName,
                            $techId,
                            $code
                        ) {
                            try {
                                $title = trim($offer->filter('h2 a')->text());

                                // 🚫 filtro no-tech
                                if (!$this->isTechRelated($title)) {
                                    $totalSkippedAll++;
                                    return;
                                }

                                $company = $offer->filter('p.fc_base a')->count()
                                    ? trim($offer->filter('p.fc_base a')->text())
                                    : null;

                                $href   = $offer->filter('h2 a')->attr('href');
                                $urlJob = "https://{$code}.computrabajo.com{$href}";

                                $city = $this->extractCityFromUrl($urlJob);
                                [$city, $lat, $lng, $countryName] = $this->getCoords($city, $country);

                                $modality = $this->mapModality($title . ' ' . $city);

                                $totalFound++;
                                $countries[$countryName] = ($countries[$countryName] ?? 0) + 1;
                                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                                // 🔍 Duplicado
                                $existingOffer = JobOffer::where('source', 'Computrabajo')
                                    ->whereRaw('LOWER(title) = ?', [strtolower($title)])
                                    ->whereRaw('LOWER(IFNULL(company, "")) = ?', [strtolower($company ?? '')])
                                    ->where('country', $countryName)
                                    ->where('search_query', $techName)
                                    ->first();

                                if ($existingOffer) {
                                    $existingOffer->technologies()
                                        ->syncWithoutDetaching([$techId]);
                                    $totalSkippedAll++;
                                    return;
                                }

                                // 🌎 Normaliza país
                                $countryName = match (strtolower($countryName)) {
                                    'peru' => 'Perú',
                                    'mexico' => 'México',
                                    'colombia' => 'Colombia',
                                    'argentina' => 'Argentina',
                                    'uruguay' => 'Uruguay',
                                    'ecuador' => 'Ecuador',
                                    'venezuela' => 'Venezuela',
                                    'bolivia' => 'Bolivia',
                                    'chile' => 'Chile',
                                    default => ucfirst($countryName),
                                };

                                // 💾 Crear oferta
                                $offerModel = JobOffer::create([
                                    'title'        => $title,
                                    'company'      => $company,
                                    'country'      => $countryName,
                                    'region'       => RegionHelper::fromCountry($countryName),
                                    'state_code'   => strtoupper($code),
                                    'city'         => $city,
                                    'latitude'     => $lat,
                                    'longitude'    => $lng,
                                    'modality'     => $modality,
                                    'source'       => 'Computrabajo',
                                    'search_query' => $techName,
                                    'url'          => $urlJob,
                                    'published_at' => now(),
                                    'created_at'   => now(),
                                    'updated_at'   => now(),
                                ]);

                                $offerModel->technologies()
                                    ->syncWithoutDetaching([$techId]);

                                $totalNew++;
                                $this->line("✅ {$title} ({$countryName} - {$city})");

                            } catch (\Throwable $th) {
                                Log::warning("⚠️ Error oferta {$techName}: {$th->getMessage()}");
                                $totalSkippedAll++;
                            }
                        });

                        usleep(random_int(500000, 1500000));

                    } catch (\Throwable $th) {
                        $this->warn("💥 Error en {$country} (página {$i}): {$th->getMessage()}");
                        $totalSkippedAll++;
                    }
                }

                sleep(2);
            }

            // 📊 Métrica diaria
            TechnologyMetric::updateOrCreate(
                [
                    'technology_id' => $techId,
                    'run_date'      => Carbon::today(),
                    'source'        => 'Computrabajo',
                ],
                [
                    'technology_name'     => $techName,
                    'jobs_found_count'    => $totalFound,
                    'jobs_new_count'      => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown'  => $modalities,
                    'updated_at'          => now(),
                ]
            );

            $this->info("📊 {$techName}: {$totalNew} nuevas / {$totalFound} totales");

            // 🔢 acumular al run global
            $totalFoundAll    += $totalFound;
            $totalInsertedAll += $totalNew;
        }

        // ✅ Final exitoso
        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("\n🎯 Scraping completado exitosamente y métricas guardadas.");

    } catch (\Throwable $e) {
        // ❌ Fallo crítico
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}


    // ---------------------------------------------------------------------
    // 🔧 Helpers
    // ---------------------------------------------------------------------

    protected function makeSearchSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = str_replace(['#', '++', '+', ' ', '.', '/', '\\'], ['-sharp', '-plus', '-plus', '-', '', '-', '-'], $slug);
        return "programador-{$slug}";
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
            return [$city, self::DEFAULT_LAT, self::DEFAULT_LNG, $country];
        }

        try {
            $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])->first();

            if ($found) {
                return [$found->city, $found->lat, $found->lng, $found->country];
            }

            $res = Http::withHeaders(['User-Agent' => 'LaravelJobScraper/1.0'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => "$city, $country",
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                return [$city, (float) $data['lat'], (float) $data['lon'], $country];
            }
        } catch (\Throwable $th) {
            Log::warning("⚠️ Error Nominatim {$city}: " . $th->getMessage());
        }

        return [$city, self::DEFAULT_LAT, self::DEFAULT_LNG, $country];
    }
}
