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
        // 🔹 Parámetros
        $countryOption = $this->option('country');
        $pages = (int) $this->option('pages');

        // 🔹 Selección de país o todos
        $activeCountries = $countryOption
            ? array_intersect_key($this->countryMap, [$countryOption => true])
            : $this->countryMap;

        if (empty($activeCountries)) {
            $this->error("❌ País no válido. Usa uno de: " . implode(', ', array_keys($this->countryMap)));
            return;
        }

        // 🔹 Cargar tecnologías
        $technologies = Technology::pluck('name', 'id');
        $this->info("🌎 Scrapeando " . count($technologies) . " tecnologías para: " . implode(', ', $activeCountries));

        // 🔹 Recorrido principal
        foreach ($technologies as $techId => $techName) {
            $this->warn("\n💡 Procesando tecnología: {$techName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
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
                        $offers = $crawler->filter('article[class*="box_offer"]');

                        if ($offers->count() === 0) {
                            $this->warn("⚠️ Sin ofertas para {$techName} en {$country} (página {$i})");
                            continue;
                        }
//WEB API
                        $offers->each(function (Crawler $offer) use (&$totalNew, &$totalFound, &$countries, &$modalities, $country, $techName, $code) {
                            try {
                                $title = trim($offer->filter('h2 a')->text());
                                    // 🚫 Nuevo filtro
                                if (!$this->isTechRelated($title)) {
                                    $this->warn("⛔ Ignorado (no tech): {$title}");
                                    return;
                                }
                                $company = $offer->filter('p.fc_base a')->count()
                                    ? trim($offer->filter('p.fc_base a')->text())
                                    : null;

                                $href = $offer->filter('h2 a')->attr('href');
                                $urlJob = "https://{$code}.computrabajo.com" . $href;

                                $city = $this->extractCityFromUrl($urlJob);
                                [$city, $lat, $lng, $countryName] = $this->getCoords($city, $country);
                                $modality = $this->mapModality($title . ' ' . $city);

                                $totalFound++;
                                $countries[$countryName] = ($countries[$countryName] ?? 0) + 1;
                                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                                $exists = JobOffer::where('source', 'Computrabajo')
                                    ->whereRaw('LOWER(title) = ?', [strtolower($title)])
                                    ->whereRaw('LOWER(IFNULL(company,"")) = ?', [strtolower($company ?? '')])
                                    ->where('country', $countryName)
                                    ->where('search_query', $techName)
                                    ->exists();

                                if ($exists) return;

                                JobOffer::create([
                                    'title'        => $title,
                                    'company'      => $company,
                                    'country'      => $countryName,
                                    'city'         => $city,
                                    'latitude'     => $lat,
                                    'longitude'    => $lng,
                                    'modality'     => $modality,
                                    'source'       => 'Computrabajo',
                                    'search_query' => $techName,
                                    'url'          => $urlJob,
                                    'published_at' => now(),
                                ]);

                                $totalNew++;
                                $this->line("✅ {$title} ({$countryName} - {$city})");
                            } catch (\Throwable $th) {
                                Log::warning("⚠️ Error oferta {$techName}: " . $th->getMessage());
                            }
                        });

                        usleep(random_int(500000, 1500000)); // Pausa aleatoria entre páginas
                    } catch (\Throwable $th) {
                        $this->warn("💥 Error en {$country} (página {$i}): " . $th->getMessage());
                    }
                }
                sleep(2); // Pequeña pausa entre países
            }

            // 📊 Guardar métricas de la tecnología
            TechnologyMetric::updateOrCreate(
                [
                    'technology_id' => $techId,
                    'run_date' => Carbon::today(),
                    'source' => 'Computrabajo',
                ],
                [
                    'technology_name' => $techName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count' => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown' => $modalities,
                    'updated_at' => now(),
                ]
            );

            $this->info("📊 {$techName}: {$totalNew} nuevas / {$totalFound} totales");
        }

        $this->info("\n🎯 Scraping completado exitosamente y métricas guardadas.");
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

        if (
            (str_contains($t, 'presencial') && str_contains($t, 'remoto')) ||
            (str_contains($t, 'presencial') && str_contains($t, 'teletrabajo')) ||
            (str_contains($t, 'presencial') && str_contains($t, 'home office')) ||
            str_contains($t, 'híbrido') ||
            str_contains($t, 'mixto') ||
            str_contains($t, 'parcial')
        ) {
            return 'hybrid';
        }

        if (
            str_contains($t, 'remoto') ||
            str_contains($t, 'teletrabajo') ||
            str_contains($t, 'home office')
        ) {
            return 'fully_remote';
        }

        if (
            str_contains($t, 'presencial') ||
            str_contains($t, 'oficina') ||
            str_contains($t, 'onsite')
        ) {
            return 'no_remote';
        }

        return 'no_remote';
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
