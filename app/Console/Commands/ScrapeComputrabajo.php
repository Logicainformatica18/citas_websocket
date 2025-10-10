<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\JobOffer;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ScrapeComputrabajo extends Command
{
    protected $signature = 'scrape:computrabajo {--pages=2} {--query=programador}';
    protected $description = '🕷️ Scrapea Computrabajo en todos los países latinoamericanos y guarda en job_offers';

    const DEFAULT_LAT = -12.046374;
    const DEFAULT_LNG = -77.042793;

    protected $stats = [
        'local_hits' => 0,
        'api_hits' => 0,
        'fallback' => 0,
    ];

    protected $countryMap = [
        'pe' => 'Peru',
        'mx' => 'Mexico',
        'cl' => 'Chile',
        'co' => 'Colombia',
        'ar' => 'Argentina',
        'ec' => 'Ecuador',
        've' => 'Venezuela',
        'cr' => 'Costa Rica',
        'gt' => 'Guatemala',
        'sv' => 'El Salvador',
        'uy' => 'Uruguay',
        'py' => 'Paraguay',
        'pa' => 'Panama',
        'hn' => 'Honduras',
        'ni' => 'Nicaragua',
        'do' => 'Republica Dominicana',
        'bo' => 'Bolivia',
        'cu' => 'Cuba',
        'pr' => 'Puerto Rico',
    ];

    public function handle()
    {
        $pages = (int) $this->option('pages');
        $searchQuery = strtolower($this->option('query'));

        $this->info("🌎 Scraping multipaís de Computrabajo iniciado...");
        $this->info("🔍 Palabra clave: {$searchQuery}");
        $this->info("📄 Páginas por país: {$pages}");

        foreach ($this->countryMap as $code => $countryName) {
            $baseUrl = "https://{$code}.computrabajo.com";
            $this->line("\n🌐 Procesando {$countryName} ({$baseUrl})...");

            for ($i = 1; $i <= $pages; $i++) {
                $url = "{$baseUrl}/trabajo-{$searchQuery}?p={$i}";
                $this->info("   🔸 Página {$i}: {$url}");

                try {
                    $response = Http::withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (LaravelJobScraper/1.0)',
                    ])->timeout(20)->get($url);

                    if ($response->failed()) {
                        $this->warn("   ❌ No se pudo cargar la página {$i} de {$countryName}");
                        continue;
                    }

                    $crawler = new Crawler($response->body());
                    $offers = $crawler->filter('article[class*="box_offer"]');

                    $offers->each(function (Crawler $offer) use ($searchQuery, $countryName, $baseUrl) {
                        try {
                            $title = trim($offer->filter('h2 a')->text());
                            $company = $offer->filter('p.fc_base a')->count()
                                ? trim($offer->filter('p.fc_base a')->text())
                                : null;

                            $href = $offer->filter('h2 a')->attr('href');
                            $urlJob = $baseUrl . $href;

                            $city = $this->extractCityFromUrl($urlJob);
                            $dateText = $offer->filter('p.fs13.fc_aux')->count()
                                ? trim($offer->filter('p.fs13.fc_aux')->text())
                                : 'hoy';

                            $modalityText = $offer->filter('p.fc_aux span')->count()
                                ? trim($offer->filter('p.fc_aux span')->text())
                                : '';

                            $modality = $this->mapModality($modalityText, $city, $title);
                            $date = $this->parseRelativeDate($dateText);
                            [$lat, $lng] = $this->getCoords($city, $countryName);

                            // 🔎 Evitar duplicados por título + empresa + país
                            $exists = JobOffer::query()
                                ->whereRaw('LOWER(TRIM(title)) = ?', [strtolower(trim($title))])
                                ->whereRaw('LOWER(TRIM(IFNULL(company, ""))) = ?', [strtolower(trim($company ?? ''))])
                                ->where('country', $countryName)
                                ->exists();

                            if ($exists) {
                                $this->warn("      ⚠️ Ya existe: {$title} ({$company})");
                                return;
                            }

                            JobOffer::create([
                                'title' => $title,
                                'company' => $company,
                                'country' => $countryName,
                                'city' => $city,
                                'modality' => $modality,
                                'source' => 'Computrabajo',
                                'search_query' => $searchQuery,
                                'latitude' => $lat,
                                'longitude' => $lng,
                                'published_at' => $date,
                                'url' => $urlJob,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $this->line("      ✅ {$title} ({$countryName} - {$city})");
                        } catch (\Throwable $th) {
                            Log::error("⚠️ Error procesando oferta en {$countryName}: " . $th->getMessage());
                        }
                    });

                    sleep(2); // evita bloqueo por IP
                } catch (\Throwable $th) {
                    $this->warn("   ⚠️ Error procesando página {$i} en {$countryName}: {$th->getMessage()}");
                }
            }

            // Espera entre países para no saturar
            sleep(5);
        }

        $this->info("\n🎯 Scraping multipaís completado.");
        $this->line("📊 Local: {$this->stats['local_hits']} | API: {$this->stats['api_hits']} | Fallback: {$this->stats['fallback']}");
    }

    // 🔹 Extraer ciudad desde URL
    protected function extractCityFromUrl($url)
    {
        if (preg_match('/-en-([a-z-]+)-[A-Z0-9]+/', $url, $match)) {
            return ucwords(str_replace('-', ' ', $match[1]));
        }
        return 'Remote';
    }

    // 🔹 Parsear fechas relativas
    protected function parseRelativeDate($text)
    {
        $now = Carbon::now();
        $text = strtolower($text);

        if (preg_match('/(\d+)\s*minuto/', $text, $m)) return $now->subMinutes($m[1]);
        if (preg_match('/(\d+)\s*hora/', $text, $m)) return $now->subHours($m[1]);
        if (preg_match('/(\d+)\s*d[ií]a/', $text, $m)) return $now->subDays($m[1]);
        return $now;
    }

    // 🔹 Detectar modalidad
    protected function mapModality($text, $city, $title)
    {
        $t = strtolower("$text $city $title");
        if (str_contains($t, 'híbrido') || str_contains($t, 'mixto')) return 'hybrid';
        if (str_contains($t, 'remoto') || str_contains($t, 'home office')) return 'remote_local';
        if (str_contains($t, 'presencial') || str_contains($t, 'oficina')) return 'no_remote';
        return 'fully_remote';
    }

    // 🔹 Obtener coordenadas
    protected function getCoords($city, $country)
    {
        if (!$city) {
            $this->stats['fallback']++;
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
                $this->stats['api_hits']++;
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("🌍 Error Nominatim {$city}: " . $th->getMessage());
        }

        $this->stats['fallback']++;
        return [self::DEFAULT_LAT, self::DEFAULT_LNG];
    }
}


    /*
ELIMINA DUPLICADOS
DELETE j1
FROM job_offers j1
JOIN job_offers j2
  ON j1.id > j2.id
  AND LOWER(TRIM(j1.title)) = LOWER(TRIM(j2.title))
  AND LOWER(TRIM(IFNULL(j1.company, ''))) = LOWER(TRIM(IFNULL(j2.company, '')))
  AND LOWER(TRIM(IFNULL(j1.modality, ''))) = LOWER(TRIM(IFNULL(j2.modality, '')))
  AND ROUND(j1.latitude, 4) = ROUND(j2.latitude, 4)
  AND ROUND(j1.longitude, 4) = ROUND(j2.longitude, 4)
  AND (
      LEFT(j1.url, LOCATE('#', j1.url) - 1) = LEFT(j2.url, LOCATE('#', j2.url) - 1)
      OR LEFT(j1.url, LOCATE('?', j1.url) - 1) = LEFT(j2.url, LOCATE('?', j2.url) - 1)
      OR LEFT(j1.url, 100) = LEFT(j2.url, 100)
  );

  ////////////////////// LISTAR DUPLICADOS
  SELECT
  j1.id AS to_delete,
  j2.id AS keep_id,
  j1.title, j1.company, j1.modality,
  j1.latitude, j1.longitude,
  j1.url AS url1, j2.url AS url2
FROM job_offers j1
JOIN job_offers j2
  ON j1.id > j2.id
  AND LOWER(TRIM(j1.title)) = LOWER(TRIM(j2.title))
  AND LOWER(TRIM(IFNULL(j1.company, ''))) = LOWER(TRIM(IFNULL(j2.company, '')))
  AND LOWER(TRIM(IFNULL(j1.modality, ''))) = LOWER(TRIM(IFNULL(j2.modality, '')))
  AND ROUND(j1.latitude, 4) = ROUND(j2.latitude, 4)
  AND ROUND(j1.longitude, 4) = ROUND(j2.longitude, 4)
  AND (
      LEFT(j1.url, LOCATE('#', j1.url) - 1) = LEFT(j2.url, LOCATE('#', j2.url) - 1)
      OR LEFT(j1.url, LOCATE('?', j1.url) - 1) = LEFT(j2.url, LOCATE('?', j2.url) - 1)
      OR LEFT(j1.url, 100) = LEFT(j2.url, 100)
  )
ORDER BY j1.title;


    */
