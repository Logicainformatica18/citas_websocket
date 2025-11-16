<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use App\Helpers\RegionHelper;
use Carbon\Carbon;

class ReedByLanguagesCommand extends Command
{
    protected $signature = 'reed:languages {--pages=1}';
    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por lenguaje.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    public function handle()
    {
        $languages = Language::whereIn('languages.id', function ($q) {
        $q->select('course_language.language_id')
            ->from('course_language')
            ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
    })
    ->pluck('name', 'id');

        $this->info("🇬🇧 Importando desde Reed para {$languages->count()} lenguajes…");

        foreach ($languages as $languageId => $languageName) {

            $this->warn("\n🔎 Buscando: {$languageName}");

            for ($page = 0; $page < (int)$this->option('pages'); $page++) {

                // PETICIÓN A REED
                $response = Http::withBasicAuth(env('REED_API_KEY'), '')
                    ->get('https://www.reed.co.uk/api/1.0/search', [
                        'keywords'         => $languageName,
                        'resultsToTake'    => 100,
                        'resultsToSkip'    => $page * 100,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("❌ Error API para {$languageName}, page {$page}");
                    continue;
                }

                $jobs = $response->json()['results'] ?? [];

                if (empty($jobs)) {
                    $this->info("⚠️ Sin resultados");
                    break;
                }

                foreach ($jobs as $job) {

                    $externalId = "reed-" . $job['jobId'];

                    // 🛑 DEDUPE
                    if (JobOffer::where('external_id', $externalId)
                        ->where('source', 'reed')
                        ->exists()) {

                        $this->stats['skipped']++;
                        continue;
                    }

                    // 🌍 Reed opera solo en Reino Unido
                    $countryIso = "GB";
                    $country = CountryNormalizer::normalize("GB");

                    // 🏙️ UBICACIÓN
                    $locationRaw = $job['locationName'] ?? null;

                    $cityMatch = City::where('city_ascii', $locationRaw)
                        ->orWhere('city', $locationRaw)
                        ->first();

                    if ($cityMatch) {

                        $lat = $cityMatch->lat;
                        $lng = $cityMatch->lng;
                        $city = $cityMatch->city;
                        $country = CountryNormalizer::normalize($cityMatch->country);

                    } else {
                        // 🌐 FALLBACK CAPITAL UK
                        $fallback = $this->fallbackCapital($countryIso);
                        $lat = $fallback['lat'];
                        $lng = $fallback['lng'];
                        $city = $fallback['city'];
                        $country = $fallback['country'];
                    }

                    // 📅 FECHA SEGURA
                    $publishedAt = $this->parseReedDate($job['date'] ?? null);

                    // 💾 GUARDAR OFERTA
                    JobOffer::create([
                        'title'          => $job['jobTitle'] ?? '',
                        'company'        => $job['employerName'] ?? '',
                        'country'        => $country,
                        'city'           => $city,
                        'latitude'       => $lat,
                        'longitude'      => $lng,
                       'modality' => 'no_remote',

                        'salary_min'     => $job['minimumSalary'] ?? null,
                        'salary_max'     => $job['maximumSalary'] ?? null,
                        'currency'       => null,
                        'compensation_type' => null,
                        'source'         => 'reed',
                        'external_id'    => $externalId,
                        'url'            => $job['jobUrl'] ?? null,    // ✔ URL correcta
                        'search_query'   => $languageName,
                        'published_at'   => $publishedAt,
                    ]);

                    // 📊 MÉTRICA DIARIA
                    LanguageMetric::create([
                        'language_id' => $languageId,
                        'total'       => 1,
                        'country'     => $country,
                        'source'      => 'reed',
                        'run_date'    => now()->toDateString(),
                    ]);

                    $this->stats['mapped']++;
                }
            }
        }

        $this->info("\n🟢 REED COMPLETADO");
        $this->info("API Hits: {$this->stats['api_hits']}");
        $this->info("Ofertas nuevas: {$this->stats['mapped']}");
        $this->info("Saltadas: {$this->stats['skipped']}");
    }


    /**
     * 📅 Convierte fecha DD/MM/YYYY a Carbon
     */
    private function parseReedDate(?string $date)
    {
        if (!$date) return now();

        $parts = explode('/', $date);

        if (count($parts) === 3) {
            [$day, $month, $year] = $parts;
            return Carbon::createFromFormat('Y-m-d', "$year-$month-$day");
        }

        return now();
    }

    /**
     * 🌐 Capital fallback por ISO2
     */
    public static function fallbackCapital(string $iso2): array
    {
        $iso2 = strtoupper($iso2);

        $capitals = [
            'GB' => ['city'=>'London','lat'=>51.5072,'lng'=>-0.1276,'country'=>'Reino Unido'],
            'US' => ['city'=>'Washington D.C.','lat'=>38.8951,'lng'=>-77.0364,'country'=>'Estados Unidos'],
            'CA' => ['city'=>'Ottawa','lat'=>45.4215,'lng'=>-75.6972,'country'=>'Canadá'],
            'MX' => ['city'=>'Ciudad de México','lat'=>19.4326,'lng'=>-99.1332,'country'=>'México'],
            'ES' => ['city'=>'Madrid','lat'=>40.4168,'lng'=>-3.7038,'country'=>'España'],
            'FR' => ['city'=>'Paris','lat'=>48.8566,'lng'=>2.3522,'country'=>'Francia'],
            'DE' => ['city'=>'Berlin','lat'=>52.52,'lng'=>13.405,'country'=>'Alemania'],
            'JP' => ['city'=>'Tokyo','lat'=>35.6895,'lng'=>139.6917,'country'=>'Japón'],
            'UNK'=> ['city'=>'Unknown','lat'=>0,'lng'=>0,'country'=>'Desconocido']
        ];

        return $capitals[$iso2] ?? $capitals['UNK'];
    }
}
