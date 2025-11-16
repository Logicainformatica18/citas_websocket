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

class BuiltInByLanguagesCommand extends Command
{
    protected $signature = 'builtin:languages';
    protected $description = '🌐 Importa ofertas laborales desde BuiltIn.com vía RSS por lenguaje.';

    protected $stats = [
        'mapped'  => 0,
        'skipped' => 0,
    ];

    public function handle()
    {
        $languages = Language::whereIn('languages.id', function ($q) {
                $q->select('course_language.language_id')
                ->from('course_language')
                ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
        })->pluck('name', 'id');

        $this->info("🌐 Importando desde BuiltIn (RSS) para {$languages->count()} lenguajes…");

        foreach ($languages as $languageId => $languageName) {

            $this->warn("\n🔎 Buscando: {$languageName}");

            $url = "https://builtin.com/jobs/rss?search=" . urlencode($languageName);

            $response = Http::timeout(20)->get($url);

            if ($response->failed()) {
                $this->error("❌ Error RSS para {$languageName}");
                continue;
            }

            // 📌 Parsear XML
            $xml = simplexml_load_string($response->body(), "SimpleXMLElement", LIBXML_NOCDATA);

            if (!$xml || !isset($xml->channel->item)) {
                $this->info("⚠️ Sin resultados para {$languageName}");
                continue;
            }

            foreach ($xml->channel->item as $item) {

                $title   = (string) $item->title;
                $company = $this->extractCompany($title);
                $link    = (string) $item->link;
                $pubDate = Carbon::parse((string) $item->pubDate);

                $location = (string) $item->category;

                $externalId = "builtin-" . md5($link);

                // DEDUPE
                $existing = JobOffer::where('external_id', $externalId)
                    ->where('source', 'builtin')
                    ->first();

                if ($existing) {
                    $existing->languages()->syncWithoutDetaching([$languageId]);
                    $this->stats['skipped']++;
                    continue;
                }

                // 🌍 Geolocalización básica
                $cityMatch = City::whereRaw("LOWER(city_ascii)=?", [strtolower($location)])
                    ->orWhereRaw("LOWER(city)=?", [strtolower($location)])
                    ->first();

                if ($cityMatch) {
                    $city = $cityMatch->city;
                    $lat  = $cityMatch->lat;
                    $lng  = $cityMatch->lng;
                    $country = CountryNormalizer::normalize($cityMatch->country);
                } else {
                    // Fallback USA
                    $city = "Washington D.C.";
                    $lat = 38.8951;
                    $lng = -77.0364;
                    $country = "Estados Unidos";
                }

                // Crear oferta
                $offer = JobOffer::create([
                    'title'             => $title,
                    'company'           => $company,
                    'country'           => $country,
                    'city'              => $city,
                    'latitude'          => $lat,
                    'longitude'         => $lng,
                    'modality'          => 'no_remote',
                    'salary_min'        => null,
                    'salary_max'        => null,
                    'currency'          => 'USD',
                    'compensation_type' => null,
                    'source'            => 'builtin',
                    'external_id'       => $externalId,
                    'url'               => $link,
                    'search_query'      => $languageName,
                    'published_at'      => $pubDate,
                ]);

                $offer->languages()->syncWithoutDetaching([$languageId]);

                LanguageMetric::create([
                    'language_id' => $languageId,
                    'total'       => 1,
                    'country'     => $country,
                    'source'      => 'builtin',
                    'run_date'    => now()->toDateString(),
                ]);

                $this->stats['mapped']++;
            }
        }

        $this->info("\n🟢 BUILTIN RSS COMPLETADO");
        $this->info("Nuevas: {$this->stats['mapped']}");
        $this->info("Saltadas: {$this->stats['skipped']}");
    }

    private function extractCompany(string $title): string
    {
        // BuiltIn titles often come like “Senior Backend Engineer (CompanyName)”
        if (preg_match('/\((.*?)\)$/', $title, $m)) {
            return $m[1];
        }
        return "N/A";
    }
}
