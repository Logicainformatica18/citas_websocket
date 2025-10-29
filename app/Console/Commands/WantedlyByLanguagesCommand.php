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

class WantedlyByLanguagesCommand extends Command
{
    protected $signature = 'wantedly:languages {--pages=1} {--lang=es}';
    protected $description = '🇯🇵 Importa ofertas laborales desde Wantedly (Japón/Singapur) por lenguaje, traduciendo al español o inglés, con detección de modalidad estandarizada.';

    protected $stats = [
        'api_hits'  => 0,
        'fallback'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
        'translated'=> 0,
    ];

    protected $capitalMap = [
        'jp' => ['city' => 'Tokio', 'lat' => 35.6895, 'lng' => 139.6917],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],
    ];
protected function detectCountryFromCity(?string $city): string
{
    $city = strtolower(trim($city ?? ''));

    return match (true) {
        str_contains($city, 'singapore'),
        str_contains($city, 'singapur') => 'singapur',

        str_contains($city, 'tokyo'),
        str_contains($city, 'osaka'),
        str_contains($city, 'nagoya'),
        str_contains($city, 'japan'),
        str_contains($city, 'japón') => 'japon',

        default => 'jp', // 🇯🇵 valor por defecto si no se reconoce la ciudad
    };
}

    public function handle()
    {
        $pages = (int) $this->option('pages');
        $targetLang = strtolower($this->option('lang'));

        $languages = Language::whereIn('languages.id', function ($q) {
            $q->select('course_language.language_id')
                ->from('course_language')
                ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
        })->pluck('name', 'id');

        $this->info("🌏 Iniciando importación desde Wantedly (JP/SG) con traducción a [{$targetLang}] y modalidades estandarizadas...");

        foreach ($languages as $languageId => $languageName) {
            $this->warn("\n💡 Procesando lenguaje: {$languageName}");
            $totalFound = $totalNew = 0;
            $countries = ['JP' => 0];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {
                $url = "https://www.wantedly.com/api/v1/projects?keyword=" . urlencode($languageName) . "&page={$page}";

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) {
                        $this->error("❌ Error al consultar API (página {$page})");
                        continue;
                    }

                    $results = $response->json('data') ?? [];
                    $totalFound += count($results);

                    foreach ($results as $job) {
                        $title   = $job['title'] ?? 'N/A';
                        $company = $job['company']['name'] ?? null;
                        $urlJob  = "https://www.wantedly.com/projects/" . ($job['id'] ?? '');
                        $desc    = $job['description'] ?? '';
                     $city = $job['location'] ?? 'Tokyo';
$countryCode = $this->detectCountryFromCity($city);

                        $published = isset($job['published_at']) ? Carbon::parse($job['published_at']) : now();

                        // 🧭 Detección de modalidad según reglas estándar
                        $modality = $this->detectModality($title, $desc, $city);

                        // ⚠️ Evitar duplicados
                        if (!empty($job['id']) && JobOffer::where('external_id', $job['id'])->exists()) continue;

                        // 🌍 Coordenadas
                        [$city, $latitude, $longitude] = $this->getCoordsFromCountry($city, $countryCode);
                        if (!$latitude || !$longitude) {
                            $capital = $this->capitalMap[$countryCode];
                            $city = $capital['city'];
                            $latitude = $capital['lat'];
                            $longitude = $capital['lng'];
                            $this->stats['fallback']++;
                        }

                        // 🌐 Traducción gratuita con LibreTranslate
        $titleTranslated = $this->translateText($title, 'auto', $targetLang);
$descTranslated = $this->translateText($desc, 'auto', $targetLang);


                        JobOffer::create([
                            'title'            => $title,
                            'title_es'         => $targetLang === 'es' ? $titleTranslated : null,
                            'title_en'         => $targetLang === 'en' ? $titleTranslated : null,
                            'company'          => $company,
                            'country'          => strtoupper($countryCode),
                            'city'             => $city,
                            'latitude'         => $latitude,
                            'longitude'        => $longitude,
                            'modality'         => $modality,
                            'source'           => 'Wantedly',
                            'external_id'      => $job['id'] ?? null,
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
                        $countries[$countryCode] = ($countries[$countryCode] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
                        $this->stats['translated']++;
                    }

                    sleep(1.5);
                } catch (\Throwable $e) {
                    Log::error("⚠️ Error en {$languageName} (página {$page}): " . $e->getMessage());
                    $this->error("❌ {$languageName}: " . $e->getMessage());
                }
            }

            // 📊 Registrar métricas diarias
            $today = now()->toDateString();
            $existsToday = LanguageMetric::whereDate('run_date', $today)
                ->where('language_id', $languageId)
                ->where('source', 'Wantedly')
                ->exists();

            if (!$existsToday) {
                LanguageMetric::create([
                    'language_id'        => $languageId,
                    'language_name'      => $languageName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                    'run_date'           => Carbon::today(),
                    'source'             => 'Wantedly',
                ]);
            }

            $this->info("✅ {$languageName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas | Modalidades: " . json_encode($modalities));
        }

        $this->newLine();
        $this->info("🎯 Proceso completado:");
        $this->line("   🗺️ Mapeadas: {$this->stats['mapped']}");
        $this->line("   🛰️ Geocodificadas API: {$this->stats['api_hits']}");
        $this->line("   🏙️ Fallbacks (capital): {$this->stats['fallback']}");
        $this->line("   💬 Traducciones: {$this->stats['translated']}");
    }

    /**
     * Detección estandarizada de modalidad
     */
    protected function detectModality(string $title, string $description, ?string $city = null): string
    {
        $text = strtolower($title . ' ' . $description . ' ' . ($city ?? ''));

        return match (true) {
            str_contains($text, '完全リモート'),
            str_contains($text, 'full remote'),
            str_contains($text, 'work from anywhere'),
            str_contains($text, '完全在宅'),
            str_contains($text, '100% remote'),
            str_contains($text, 'fully remote') => 'fully_remote',

            str_contains($text, 'リモート可'),
            str_contains($text, 'remote possible'),
            str_contains($text, 'partly remote'),
            str_contains($text, '一部リモート'),
            str_contains($text, 'hybrid') => 'hybrid',

            str_contains($text, 'local remote'),
            str_contains($text, 'within city'),
            str_contains($text, '地域限定リモート'),
            str_contains($text, 'remote local') => 'remote_local',

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

    /**
     * Traducción automática gratuita (LibreTranslate)
     */
public function translateText(string $text, string $from = 'auto', string $to = 'es'): string
{
    if (empty(trim($text))) return '';

    $text = mb_substr(strip_tags($text), 0, 2000);
    $text = str_replace(["\r", "\n", "\t"], ' ', $text);

    try {
        $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$from}|{$to}";
        $res = Http::timeout(15)->get($url);

        if ($res->ok()) {
            $translated = $res->json('responseData.translatedText') ?? '';
            if (!empty(trim($translated)) && $translated !== $text) {
                Log::info("✅ Traducción MyMemory correcta");
                return trim($translated);
            }
        }
    } catch (\Throwable $e) {
        Log::warning("⚠️ Falla MyMemory: " . $e->getMessage());
    }

    Log::warning("⚠️ No se pudo traducir (devolviendo original)");
    return $text;
}





}
