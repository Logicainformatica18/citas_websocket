<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\CountryNormalizer;
use App\Helpers\RegionHelper;

class WantedlyByLanguagesCommand extends Command
{
    protected $signature = 'wantedly:languages {--pages=1} {--lang=es}';
    protected $description = '🇯🇵 Importa ofertas desde Wantedly (JP/SG) por lenguaje, con traducción, modalidad, país, región y geolocalización limpia.';

    protected $stats = [
        'api_hits'   => 0,
        'fallback'   => 0,
        'mapped'     => 0,
        'skipped'    => 0,
        'translated' => 0,
    ];

    protected static array $translationCache = [];

    protected $capitalMap = [
        'jp' => ['city' => 'Tokio', 'lat' => 35.6895, 'lng' => 139.6917],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],
    ];

    public function handle()
    {
        $pages = (int) $this->option('pages');
        $targetLang = strtolower($this->option('lang', 'es'));

        $languages = Language::whereIn('languages.id', function ($q) {
            $q->select('course_language.language_id')
                ->from('course_language')
                ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
        })->pluck('name', 'id');

        $this->info("🌏 Importando desde Wantedly (JP/SG) → traduciendo a [{$targetLang}] ...");

        foreach ($languages as $languageId => $languageName) {
            $this->warn("\n💡 Procesando lenguaje: {$languageName}");
            $totalFound = $totalNew = 0;
            $countries = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {
                $url = "https://www.wantedly.com/api/v1/projects?keyword=" . urlencode($languageName) . "&page={$page}";

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) {
                        $this->error("❌ Error en página {$page}");
                        continue;
                    }

                    $results = $response->json('data') ?? [];
                    $totalFound += count($results);

                    foreach ($results as $job) {
                        $externalId = $job['id'] ?? null;
                        if ($externalId && JobOffer::where('source', 'Wantedly')->where('external_id', $externalId)->exists()) {
                            continue;
                        }

                        $title = $job['title'] ?? 'N/A';
                        $company = $job['company']['name'] ?? null;
                        $desc = $job['description'] ?? '';
                        $city = $job['location'] ?? 'Tokyo';
                        $urlJob = "https://www.wantedly.com/projects/" . ($job['id'] ?? '');
                        $published = isset($job['published_at']) ? Carbon::parse($job['published_at']) : now();

                        // 🗺️ Normalización país/región
                        $countryRaw = $this->detectCountryFromCity($city);
                        $country = CountryNormalizer::normalize($countryRaw);
                        $region = RegionHelper::fromCountry($country) ?? 'ASIA';

                        // 🧭 Modalidad
                        $modality = $this->detectModality($title, $desc, $city);

                        // 🗺️ Coordenadas
                        [$city, $latitude, $longitude] = $this->getCoordsFromCountry($city, $countryRaw);

                        if (!$latitude || !$longitude) {
                            $capital = $this->capitalMap[$countryRaw] ?? null;
                            if ($capital) {
                                $city = $capital['city'];
                                $latitude = $capital['lat'];
                                $longitude = $capital['lng'];
                                $this->stats['fallback']++;
                            }
                        }

                        // ❌ No asignar lat/lng si es remoto
                        if ($modality === 'fully_remote') {
                            $latitude = null;
                            $longitude = null;
                        }

                        // 🌐 Traducción (con rate control)
                        if ($this->stats['translated'] % 5 === 0) usleep(800000);
                        $titleTranslated = $this->translateText($title, 'auto', $targetLang);
                        $descTranslated = $this->translateText($desc, 'auto', $targetLang);

                        // 💾 Guardar oferta
                        JobOffer::create([
                            'title'            => $title,
                            'title_es'         => $targetLang === 'es' ? $titleTranslated : null,
                            'title_en'         => $targetLang === 'en' ? $titleTranslated : null,
                            'company'          => $company,
                            'country'          => $country,
                            'region'           => $region,
                            'city'             => $city,
                            'latitude'         => $latitude,
                            'longitude'        => $longitude,
                            'modality'         => $modality,
                            'source'           => 'Wantedly',
                            'external_id'      => $externalId,
                            'url'              => $urlJob,
                            'search_query'     => $languageName,
                            'description'      => $desc,
                            'description_es'   => $targetLang === 'es' ? $descTranslated : null,
                            'description_en'   => $targetLang === 'en' ? $descTranslated : null,
                            'published_at'     => $published,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);

                        $totalNew++;
                        $countries[$country] = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
                        $this->stats['translated']++;
                    }

                    sleep(1);
                } catch (\Throwable $e) {
                    Log::error("⚠️ Error en {$languageName} (página {$page}): " . $e->getMessage());
                    $this->error("❌ {$languageName}: " . $e->getMessage());
                }
            }

            // 📊 Guardar métricas
            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $languageId,
                    'run_date'    => Carbon::today(),
                    'source'      => 'Wantedly',
                ],
                [
                    'language_name'      => $languageName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                    'updated_at'         => now(),
                ]
            );

            $this->info("✅ {$languageName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
        }

        $this->newLine();
        $this->info("🎯 Proceso completado:");
        $this->line("   🗺️ Mapeadas: {$this->stats['mapped']}");
        $this->line("   🛰️ Geocodificadas: {$this->stats['api_hits']}");
        $this->line("   🏙️ Fallbacks: {$this->stats['fallback']}");
        $this->line("   💬 Traducciones: {$this->stats['translated']}");
    }

    protected function detectCountryFromCity(?string $city): string
    {
        $city = strtolower(trim($city ?? ''));
        return match (true) {
            str_contains($city, 'singapore'), str_contains($city, 'singapur') => 'sg',
            str_contains($city, 'tokyo'), str_contains($city, 'osaka'),
            str_contains($city, 'nagoya'), str_contains($city, 'japan'),
            str_contains($city, 'japón') => 'jp',
            default => 'jp',
        };
    }

    protected function detectModality(string $title, string $description, ?string $city = null): string
    {
        $text = strtolower($title . ' ' . $description . ' ' . ($city ?? ''));
        return match (true) {
            str_contains($text, '完全リモート'),
            str_contains($text, 'full remote'),
            str_contains($text, 'work from anywhere'),
            str_contains($text, '100% remote'),
            str_contains($text, 'fully remote') => 'fully_remote',
            str_contains($text, 'リモート可'),
            str_contains($text, 'hybrid'),
            str_contains($text, 'partly remote') => 'hybrid',
            default => 'no_remote',
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {
            $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)]))
                ->first();

            if ($foundCity) {
                $this->stats['mapped']++;
                return [$foundCity->city, $foundCity->lat, $foundCity->lng];
            }

            [$lat, $lng] = $this->getCoords($city, $countryCode);
            if ($lat && $lng) {
                $this->stats['api_hits']++;
                return [$city, $lat, $lng];
            }
        }
        return [$city, null, null];
    }

    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::withHeaders(['User-Agent' => 'LaravelJobScraper/1.0'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => "{$city}, {$country}",
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $th->getMessage());
        }
        return [null, null];
    }

    public function translateText(string $text, string $from = 'auto', string $to = 'es'): string
    {
        if (empty(trim($text))) return '';

        $text = strip_tags($text);
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text);
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = mb_substr($text, 0, 500);

        $key = md5($text . $from . $to);
        if (isset(self::$translationCache[$key])) return self::$translationCache[$key];

        $mirrors = [
            'https://api.mymemory.translated.net/get',
            'https://translate.astian.org/translate',
            'https://libretranslate.com/translate',
        ];

        foreach ($mirrors as $endpoint) {
            try {
                if (str_contains($endpoint, 'mymemory')) {
                    $keyParam = env('MYMEMORY_API_KEY');
                    $url = "{$endpoint}?q=" . urlencode($text) . "&langpair={$from}|{$to}";
                    if ($keyParam) $url .= "&de={$keyParam}";
                    $res = Http::retry(2, 1000)->timeout(20)->get($url);
                    if ($res->ok()) {
                        $translated = html_entity_decode(
                            $res->json('responseData.translatedText') ?? '',
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        );
                        if (!empty($translated) && strtolower($translated) !== strtolower($text)) {
                            return self::$translationCache[$key] = trim($translated);
                        }
                    }
                } else {
                    $res = Http::retry(2, 1000)->timeout(20)->post($endpoint, [
                        'q' => $text,
                        'source' => $from,
                        'target' => $to,
                        'format' => 'text',
                    ]);
                    if ($res->ok() && isset($res->json()['translatedText'])) {
                        $translated = $res->json()['translatedText'];
                        if (!empty($translated) && strtolower($translated) !== strtolower($text)) {
                            return self::$translationCache[$key] = trim($translated);
                        }
                    }
                }
            } catch (\Throwable $th) {
                Log::warning("⚠️ Error traduciendo con {$endpoint}: " . $th->getMessage());
            }
        }

        return self::$translationCache[$key] = $text;
    }
}
