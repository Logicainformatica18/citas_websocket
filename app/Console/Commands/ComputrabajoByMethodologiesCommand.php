<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;
use App\Console\Commands\Traits\JobFilterTrait; // 👈 importa el trait
use App\Helpers\RegionHelper;


class ComputrabajoByMethodologiesCommand extends Command
{
     use JobFilterTrait; // 👈 usa el trait aquí
    protected $signature = 'computrabajo:methodologies {--pages=3}';
    protected $description = '🧩 Scrapea Computrabajo por cada metodología (ej: scrum, agile, kanban) y guarda métricas.';

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

    const DEFAULT_LAT = -12.046374;
    const DEFAULT_LNG = -77.042793;

    public function handle()
    {
        $methodologies = Methodology::pluck('name', 'id');
        $pages = (int) $this->option('pages');

        $this->info("🌐 Scrapeando Computrabajo para {$methodologies->count()} metodologías ({$pages} páginas por país)...");

        foreach ($methodologies as $methodId => $methodName) {
            $this->warn("\n💡 Metodología actual: {$methodName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

           $context = Methodology::find($methodId)->search_context;
$slugMethod = $this->makeSearchSlug($context ? "{$context} {$methodName}" : $methodName);


            foreach ($this->countryMap as $code => $country) {
                $this->line("🌍 País: {$country}");

                for ($i = 1; $i <= $pages; $i++) {
                    $url = "https://{$code}.computrabajo.com/trabajo-de-{$slugMethod}?p={$i}";
                    $this->line("🔗 Página {$i}: {$url}");

                    try {
                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                        ])->timeout(25)->get($url);

                        if ($response->failed()) {
                            $this->warn("❌ Falló la página {$i} en {$country}");
                            continue;
                        }

                        $crawler = new Crawler($response->body());
                        $offers = $crawler->filter('article[class*="box_offer"]');

                        if ($offers->count() === 0) {
                            $this->warn("⚠️ Sin ofertas para {$methodName} en {$country} (página {$i})");
                            continue;
                        }

                        $offers->each(function (Crawler $offer) use (&$totalNew, &$totalFound, &$countries, &$modalities, $country, $methodName, $code,$methodId) {
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
                                [$lat, $lng] = $this->getCoords($city, $country);

                                $modality = $this->mapModality($title . ' ' . $city);
                                $published = now();

                                $totalFound++;
                                $countries[$country] = ($countries[$country] ?? 0) + 1;
                                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                              $existingOffer = JobOffer::where('source', 'Computrabajo')
    ->whereRaw('LOWER(title) = ?', [strtolower($title)])
    ->whereRaw('LOWER(IFNULL(company, "")) = ?', [strtolower($company ?? '')])
    ->where('country', $country)
    ->where('search_query', $methodName)
    ->first();

if ($existingOffer) {
    // Solo vincula la metodología sin alterar los datos existentes
    $existingOffer->methodologies()->syncWithoutDetaching([$methodId]);
    return;
}

$country = match (strtolower($country)) {
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

                           $offer = JobOffer::create([
    'title'        => $title,
    'company'      => $company,
    'country'      => $country,
    'region'       => RegionHelper::fromCountry($country),
    'state_code'   => strtoupper($code),
    'city'         => $city,
    'latitude'     => $lat,
    'longitude'    => $lng,
    'modality'     => $modality,
    'source'       => 'Computrabajo',
    'search_query' => $methodName,
    'url'          => $urlJob,
    'published_at' => $published,
    'created_at'   => now(),
    'updated_at'   => now(),
]);

$offer->methodologies()->syncWithoutDetaching([$methodId]);


                                $totalNew++;
                                $this->line("✅ {$title} ({$country} - {$city})");

                            } catch (\Throwable $th) {
                                Log::warning("⚠️ Error oferta {$methodName}: " . $th->getMessage());
                            }
                        });

                   usleep(random_int(500000, 1500000)); // 0.5 a 1.5 seg
                    } catch (\Throwable $th) {
                        $this->warn("💥 Error en {$country} (página {$i}): " . $th->getMessage());
                    }
                }

                sleep(4);
            }

            // 🧾 Registrar métricas del día
            MethodologyMetric::updateOrCreate(
                [
                    'methodology_id' => $methodId,
                    'run_date' => Carbon::today(),
                    'source' => 'Computrabajo',
                ],
                [
                    'methodology_name' => $methodName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count' => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown' => $modalities,
                    'updated_at' => now(),
                ]
            );

            $this->info("📊 {$methodName}: {$totalNew} nuevas / {$totalFound} totales");
        }

        $this->info("\n🎯 Scraping + métricas completado exitosamente (metodologías).");
    }

    protected function makeSearchSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        // Quita caracteres raros y adapta casos comunes
        $slug = str_replace(
            ['#', '++', '+', ' ', '.', '/', '\\'],
            ['sharp', 'plus', 'plus', '-', '', '-', '-'],
            $slug
        );
        return $slug; // 🔸 sin "programador-" porque las metodologías se buscan solas
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
            (str_contains($t, 'híbrido')) || str_contains($t, 'mixto')
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
            str_contains($t, 'oficina')
        ) {
            return 'no_remote';
        }

        return 'no_remote';
    }

    protected function getCoords($city, $country)
    {
        if (!$city || strtolower($city) === 'remote') {
            return [self::DEFAULT_LAT, self::DEFAULT_LNG];
        }

        try {
            $res = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
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
}
