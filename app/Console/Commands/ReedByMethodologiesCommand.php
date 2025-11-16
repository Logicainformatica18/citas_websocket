<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;

class ReedByMethodologiesCommand extends Command
{
    protected $signature = 'reed:methodologies {--pages=1}';
    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por metodología.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    public function handle()
    {
        // 🔍 Solo metodologías que están vinculadas a carreras
        $methodologies = Methodology::whereIn('methodologies.id', function ($q) {
            $q->select('course_methodology.methodology_id')
              ->from('course_methodology')
              ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->pluck('name', 'id');

        $this->info("🇬🇧 Importando desde Reed para {$methodologies->count()} metodologías…");

        foreach ($methodologies as $methodologyId => $methodologyName) {

            $this->warn("\n🔎 Buscando metodología: {$methodologyName}");

            for ($page = 0; $page < (int)$this->option('pages'); $page++) {

                // API REED
                $response = Http::withBasicAuth(env('REED_API_KEY'), '')
                    ->get('https://www.reed.co.uk/api/1.0/search', [
                        'keywords'         => $methodologyName,
                        'resultsToTake'    => 100,
                        'resultsToSkip'    => $page * 100,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("❌ Error API en {$methodologyName}, página {$page}");
                    continue;
                }

                $jobs = $response->json()['results'] ?? [];

                if (empty($jobs)) {
                    $this->info("⚠️ Sin resultados");
                    break;
                }

                foreach ($jobs as $job) {

                    // ID único
                    $externalId = "reed-" . $job['jobId'];

                    // 🛑 DEDUPE
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'reed')
                        ->first();

                    if ($existing) {
                        // Asociar la metodología al pivot
                        $existing->methodologies()->syncWithoutDetaching([$methodologyId]);
                        $this->stats['skipped']++;
                        continue;
                    }

                    // 🌍 Reed = Reino Unido
                    $countryIso = "GB";
                    $country = CountryNormalizer::normalize("GB");

                    // 🏙️ City
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
                        // fallback a Londres
                        $fallback = $this->fallbackCapital($countryIso);
                        $lat = $fallback['lat'];
                        $lng = $fallback['lng'];
                        $city = $fallback['city'];
                        $country = $fallback['country'];
                    }

                    // Fecha DD/MM/YYYY → Carbon
                    $publishedAt = $this->parseReedDate($job['date'] ?? null);

                    // 💾 Crear OFERTA
                    $offer = JobOffer::create([
                        'title'          => $job['jobTitle'] ?? '',
                        'company'        => $job['employerName'] ?? '',
                        'country'        => $country,
                        'city'           => $city,
                        'latitude'       => $lat,
                        'longitude'      => $lng,
                        'modality'       => 'no_remote',
                        'salary_min'     => $job['minimumSalary'] ?? null,
                        'salary_max'     => $job['maximumSalary'] ?? null,
                        'currency'       => null,
                        'compensation_type' => null,
                        'source'         => 'reed',
                        'external_id'    => $externalId,
                        'url'            => $job['jobUrl'] ?? null, // ✔ URL correcta
                        'search_query'   => $methodologyName,
                        'published_at'   => $publishedAt,
                    ]);

                    // 🔗 Asociar metodología
                    $offer->methodologies()->syncWithoutDetaching([$methodologyId]);

                    // 📊 MÉTRICA
                    MethodologyMetric::create([
                        'methodology_id' => $methodologyId,
                        'total'          => 1,
                        'country'        => $country,
                        'source'         => 'reed',
                        'run_date'       => now()->toDateString(),
                    ]);

                    $this->stats['mapped']++;
                }
            }
        }

        $this->info("\n🟢 REED (METODOLOGÍAS) COMPLETADO");
        $this->info("API Hits: {$this->stats['api_hits']}");
        $this->info("Ofertas nuevas: {$this->stats['mapped']}");
        $this->info("Saltadas: {$this->stats['skipped']}");
    }


    // --- Helpers ---

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

    public static function fallbackCapital(string $iso2): array
    {
        $iso2 = strtoupper($iso2);

        $capitals = [
            'GB' => ['city'=>'London','lat'=>51.5072,'lng'=>-0.1276,'country'=>'Reino Unido'],
            'UNK'=> ['city'=>'Unknown','lat'=>0,'lng'=>0,'country'=>'Desconocido']
        ];

        return $capitals[$iso2] ?? $capitals['UNK'];
    }
}
