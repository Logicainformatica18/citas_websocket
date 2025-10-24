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
// 🌎 Paises soportados por Computrabajo
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
  protected $signature = 'scrape:computrabajo {--pages=3} {--query=programador}';

    protected $description = '🕷️ Scrapea ofertas de Computrabajo Perú y las guarda en job_offers';

    const DEFAULT_LAT = -12.046374;
    const DEFAULT_LNG = -77.042793;

    // 🔹 Contadores de origen
    protected $stats = [
        'local_hits' => 0,
        'api_hits' => 0,
        'fallback' => 0,
    ];
    // 🔹 Normaliza un nombre eliminando tildes, espacios dobles, etc.
    protected function normalizeName($name)
    {
        $name = strtolower(trim($name));
        $replacements = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ä' => 'a',
            'ë' => 'e',
            'ï' => 'i',
            'ö' => 'o',
            'ü' => 'u',
            'ñ' => 'n'
        ];
        $name = strtr($name, $replacements);
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }

    public function handle()
    {
        $searchQuery = strtolower($this->option('query'));


        $pages = (int) $this->option('pages');
      $this->info("🔎 Iniciando scraping multipaís de {$pages} páginas por país para '{$searchQuery}'...");


      foreach ($this->countryMap as $code => $country) {
    $this->newLine();
    $this->warn("🌐 Procesando {$country} ({$code}.computrabajo.com)");

    $baseUrl = "https://{$code}.computrabajo.com";

    for ($i = 1; $i <= $pages; $i++) {
        $url = "{$baseUrl}/trabajo-de-{$searchQuery}?p={$i}";
        $this->info("📄 Página {$i}: {$url}");

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            ])->timeout(25)->get($url);

            if ($response->failed()) {
                $this->warn("❌ No se pudo cargar la página {$i} de {$country}");
                continue;
            }

            $crawler = new Crawler($response->body());
            $offers = $crawler->filter('article[class*="box_offer"]');

            if ($offers->count() === 0) {
                $this->warn("⚠️ Sin ofertas en {$country} (página {$i})");
                continue;
            }

            $offers->each(function (Crawler $offer) use ($country, $baseUrl, $searchQuery) {
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
                    [$lat, $lng] = $this->getCoords($city, $country);

                    // Verifica si ya existe (por título + empresa + país)
                    $exists = JobOffer::query()
                        ->whereRaw('LOWER(TRIM(title)) = ?', [strtolower(trim($title))])
                        ->whereRaw('LOWER(TRIM(IFNULL(company, ""))) = ?', [strtolower(trim($company ?? ''))])
                        ->where('country', $country)
                        ->exists();

                    if ($exists) {
                        $this->warn("⚠️ Ya existe: {$title} ({$company}) en {$country}");
                        return;
                    }

                    JobOffer::create([
                        'title' => $title,
                        'company' => $company,
                        'country' => $country,
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

                    $this->line("✅ Guardado: {$title} ({$country} - {$city}) [{$modality}]");

                } catch (\Throwable $th) {
                    Log::warning("⚠️ Error procesando oferta en {$country}: " . $th->getMessage());
                }
            });

            usleep(random_int(500000, 1500000)); // delay entre requests

        } catch (\Throwable $th) {
            $this->error("💥 Error en página {$i} de {$country}: " . $th->getMessage());
        }
    }

    sleep(5); // evita baneos entre países
}


        // 📊 Mostrar resumen final
     $this->info("\n🎯 Scraping multipaís completado con éxito.");
$this->line("📊 Local: {$this->stats['local_hits']} | API: {$this->stats['api_hits']} | Fallback: {$this->stats['fallback']}");

        $this->info("📊 Estadísticas del scraping:");
        $this->line("   • Local (dataset peru_districts): {$this->stats['local_hits']}");
        $this->line("   • Nominatim (API): {$this->stats['api_hits']}");
        $this->line("   • Fallback (Lima): {$this->stats['fallback']}");
    }

    // 🔹 Extraer ciudad desde URL
    protected function extractCityFromUrl($url)
    {
        if (preg_match('/-en-([a-z-]+)-[A-Z0-9]+/', $url, $match)) {
            return ucwords(str_replace('-', ' ', $match[1]));
        }
        return 'Remote';
    }

    // 🔹 Convertir texto “Hace X …” a fecha real
    protected function parseRelativeDate($text)
    {
        $now = Carbon::now();
        $text = strtolower($text);

        if (preg_match('/(\d+)\s*minuto/', $text, $m)) {
            return $now->subMinutes($m[1]);
        } elseif (preg_match('/(\d+)\s*hora/', $text, $m)) {
            return $now->subHours($m[1]);
        } elseif (preg_match('/(\d+)\s*d[ií]a/', $text, $m)) {
            return $now->subDays($m[1]);
        }
        return $now;
    }

    // 🔹 Mapear modalidad
    protected function mapModality($text, $city, $title)
    {
        $t = strtolower("$text $city $title");

        if (
            str_contains($t, 'híbrido') ||
            str_contains($t, 'mixto') ||
            str_contains($t, 'presencial y remoto') ||
            str_contains($t, 'remoto y presencial') ||
            str_contains($t, 'parcial') ||
            str_contains($t, 'alternado') ||
            str_contains($t, 'combinado')
        ) {
            return 'hybrid';
        }

        if (
            str_contains($t, 'remoto') ||
            str_contains($t, 'teletrabajo') ||
            str_contains($t, 'home office') ||
            str_contains($t, 'desde casa') ||
            str_contains($t, 'remote')
        ) {
            return 'remote';
        }

        if (
            str_contains($t, 'presencial') ||
            str_contains($t, 'oficina') ||
            str_contains($t, 'en sitio') ||
            str_contains($t, 'en oficina')
        ) {
            return 'no_remote';
        }

        if ($city && strtolower($city) !== 'remote') {
            return 'no_remote';
        }

        return 'fully_remote';
    }

    // 🔹 Geolocalizar ciudad (base local + fallback API)
    protected function getCoords($city, $country = 'Peru')
    {
        if (!$city) {
            $this->stats['fallback']++;
            return [self::DEFAULT_LAT, self::DEFAULT_LNG];
        }

        $normalized = $this->normalizeName($city);

        // 🔹 Buscar en tabla local
        $district = DB::table('peru_districts')
            ->select('latitude', 'longitude', 'distrito', 'provincia', 'departamento')
            ->whereRaw('LOWER(REPLACE(REPLACE(REPLACE(distrito, "á", "a"), "é", "e"), "í", "i")) LIKE ?', ["%{$normalized}%"])
            ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(provincia, "á", "a"), "é", "e"), "í", "i")) LIKE ?', ["%{$normalized}%"])
            ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(departamento, "á", "a"), "é", "e"), "í", "i")) LIKE ?', ["%{$normalized}%"])
            ->first();

        if ($district && $district->latitude && $district->longitude) {
            $this->stats['local_hits']++;
            $this->line("📍 {$city} → Coordenadas locales ({$district->latitude}, {$district->longitude})");
            return [(float) $district->latitude, (float) $district->longitude];
        }

        // 🔹 Consultar Nominatim si no hay match local
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
                $this->stats['api_hits']++;
                $this->line("🌍 {$city} → Coordenadas obtenidas desde Nominatim");

                // Cachear para la próxima
                DB::table('peru_districts')->insertOrIgnore([
                    'distrito' => ucfirst($city),
                    'departamento' => $country,
                    'latitude' => (float) $data['lat'],
                    'longitude' => (float) $data['lon'],
                ]);

                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("⚠️ Error Nominatim {$city}: " . $th->getMessage());
        }

        // 🔹 Fallback
        $this->stats['fallback']++;
        $this->warn("❌ {$city} → No encontrado (fallback Lima)");
        return [self::DEFAULT_LAT, self::DEFAULT_LNG];
    }

}
