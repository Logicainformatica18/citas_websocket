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

    public function handle()
    {
   $languages = Language::select(
        'languages.id',
        'languages.name',
        'semantic_contexts.search_context'
    )
    ->leftJoin('semantic_contexts', 'semantic_contexts.id', '=', 'languages.context_id')
    ->whereIn('languages.id', function ($q) {
        $q->select('course_language.language_id')
          ->from('course_language')
          ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
    })
    ->get();


        $pages = (int) $this->option('pages');

        $this->info("🌐 Scrapeando Computrabajo para {$languages->count()} lenguajes ({$pages} páginas por país)...");

        foreach ($languages as $lang) {
    $langId = $lang->id;
    $langName = $lang->name;
    $context = $lang->search_context;

    $this->warn("\n💡 Lenguaje actual: {$langName} ({$context})");


            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

         $slugLang = $this->makeSearchSlug($langName, $context);


            foreach ($this->countryMap as $code => $country) {
                $this->line("🌍 País: {$country}");

                for ($i = 1; $i <= $pages; $i++) {
                    $url = "https://{$code}.computrabajo.com/trabajo-de-{$slugLang}?p={$i}";
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
                            $this->warn("⚠️ Sin ofertas para {$langName} en {$country} (página {$i})");
                            continue;
                        }

                        $offers->each(function (Crawler $offer) use (&$totalNew, &$totalFound, &$countries, &$modalities, $country, $langName, $code,$langId) {
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

// 💰 Extraer texto de salario (si existe)
$salaryText = null;
$offer->filter('p.fc_aux')->each(function ($node) use (&$salaryText) {
    $text = trim($node->text());
    if (preg_match('/(\$|S\/|US\$)/', $text)) {
        $salaryText = $text;
    }
});

// 🧮 Parsear monto y moneda según código del país
[$salaryMin, $salaryMax, $currency] = $this->parseSalary($salaryText, $code);



                                $totalFound++;
                                $countries[$country] = ($countries[$country] ?? 0) + 1;
                                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                              $existingOffer = JobOffer::where('source', 'Computrabajo')
    ->where(function ($q) use ($title, $company, $country, $langName, $urlJob) {
        $q->whereRaw('LOWER(title) = ?', [strtolower($title)])
          ->whereRaw('LOWER(IFNULL(company, "")) = ?', [strtolower($company ?? '')])
          ->where('country', $country)
          ->where('search_query', $langName)
          ->where(function ($q2) use ($urlJob) {
              $q2->where('url', $urlJob)
                 ->orWhere('url', 'like', '%' . substr($urlJob, -25) . '%');
          });
    })
    ->first();

if ($existingOffer) {
    $existingOffer->languages()->syncWithoutDetaching([$langId]);
    return;
}

$offer = JobOffer::create([
    'title'        => $title,
    'company'      => $company,
    'country'      => $country,
    'region'       => strtolower($code), // 'pe', 'co', 'mx', etc.
    'state_code'   => strtoupper($code), // 'PE', 'CO', etc.
    'city'         => $city,
    'latitude'     => $lat,
    'longitude'    => $lng,
    'modality'     => $modality,
    'source'       => 'Computrabajo',
    'search_query' => $langName,
    'url'          => $urlJob,
    'salary_min'   => $salaryMin,
    'salary_max'   => $salaryMax,
    'currency'     => $currency,
    'published_at' => $published,
    'created_at'   => now(),
    'updated_at'   => now(),
]);


$offer->languages()->syncWithoutDetaching([$langId]);


                                $totalNew++;
                                $this->line("✅ {$title} ({$country} - {$city})");

                            } catch (\Throwable $th) {
                                Log::warning("⚠️ Error oferta {$langName}: " . $th->getMessage());
                            }
                        });

                        usleep(random_int(500000, 1500000));
                    } catch (\Throwable $th) {
                        $this->warn("💥 Error en {$country} (página {$i}): " . $th->getMessage());
                    }
                }

                sleep(4);
            }

            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $langId,
                    'run_date' => Carbon::today(),
                    'source' => 'Computrabajo',
                ],
                [
                    'language_name' => $langName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count' => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown' => $modalities,
                    'updated_at' => now(),
                ]
            );

            $this->info("📊 {$langName}: {$totalNew} nuevas / {$totalFound} totales");
        }

        $this->info("\n🎯 Scraping + métricas completado exitosamente con geolocalización.");
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

    // 🟢 1. Casos combinados ("presencial y remoto", "híbrido", etc.)
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

    // 🔵 2. Solo remoto
    if (
        str_contains($t, 'remoto') ||
        str_contains($t, 'teletrabajo') ||
        str_contains($t, 'home office')
    ) {
        return 'fully_remote';
    }

    // 🟣 3. Solo presencial
    if (
        str_contains($t, 'presencial') ||
        str_contains($t, 'oficina') ||
        str_contains($t, 'onsite')
    ) {
        return 'no_remote';
    }

    // ⚪ 4. Desconocido / local genérico
    return 'no_remote';

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
    if (!$text) return [null, null, $this->currencyMap[$countryCode] ?? null];

    $currency = match (true) {
        str_contains($text, 'US$') => 'USD',
        str_contains($text, 'S/')  => 'PEN',
        str_contains($text, '$')   => $this->currencyMap[$countryCode] ?? 'USD',
        default                    => $this->currencyMap[$countryCode] ?? null,
    };

    preg_match_all('/[\d.,]+/', $text, $matches);
    if (empty($matches[0])) return [null, null, $currency];

    $values = array_map(
        fn($v) => floatval(str_replace(',', '', preg_replace('/[^\d,\.]/', '', $v))),
        $matches[0]
    );

    $min = $values[0] ?? null;
    $max = $values[1] ?? $min;

    return [$min, $max, $currency];
}

}
