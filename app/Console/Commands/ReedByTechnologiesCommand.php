<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;

class ReedByTechnologiesCommand extends Command
{
    protected $signature = 'reed:technologies {--pages=1}';
    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por tecnología.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    public function handle()
    {
        // Solo tecnologías que están vinculadas a carreras
        $technologies = Technology::whereIn('technologies.id', function ($q) {
            $q->select('course_technology.technology_id')
              ->from('course_technology')
              ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
        })->pluck('name', 'id');

        $this->info("🇬🇧 Importando desde Reed para {$technologies->count()} tecnologías…");

        foreach ($technologies as $technologyId => $technologyName) {

            $this->warn("\n🔎 Buscando tecnología: {$technologyName}");

            for ($page = 0; $page < (int)$this->option('pages'); $page++) {

                // API REED
                $response = Http::withBasicAuth(env('REED_API_KEY'), '')
                    ->get('https://www.reed.co.uk/api/1.0/search', [
                        'keywords'         => $technologyName,
                        'resultsToTake'    => 100,
                        'resultsToSkip'    => $page * 100,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("❌ Error API en {$technologyName}, página {$page}");
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
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'reed')
                        ->first();

                    if ($existing) {
                        // Asociar tecnología en pivot
                        $existing->technologies()->syncWithoutDetaching([$technologyId]);
                        $this->stats['skipped']++;
                        continue;
                    }

                    // 🌍 Reed = Reino Unido
                    $countryIso = "GB";
                    $country = CountryNormalizer::normalize("GB");

                    // 🏙️ Ubicación
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
                        $fallback = $this->fallbackCapital($countryIso);
                        $lat = $fallback['lat'];
                        $lng = $fallback['lng'];
                        $city = $fallback['city'];
                        $country = $fallback['country'];
                    }

                    // 📅 FECHA segura
                    $publishedAt = $this->parseReedDate($job['date'] ?? null);

                    // 💾 Crear oferta
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
                        'url'            => $job['jobUrl'] ?? null,
                        'search_query'   => $technologyName,
                        'published_at'   => $publishedAt,
                    ]);

                    // 🔗 Asociar tecnología
                    $offer->technologies()->syncWithoutDetaching([$technologyId]);

                    // 📊 Métrica
                    TechnologyMetric::create([
                        'technology_id' => $technologyId,
                        'total'         => 1,
                        'country'       => $country,
                        'source'        => 'reed',
                        'run_date'      => now()->toDateString(),
                    ]);

                    $this->stats['mapped']++;
                }
            }
        }

        $this->info("\n🟢 REED (TECNOLOGÍAS) COMPLETADO");
        $this->info("API Hits: {$this->stats['api_hits']}");
        $this->info("Ofertas nuevas: {$this->stats['mapped']}");
        $this->info("Saltadas: {$this->stats['skipped']}");
    }



    // ---- Helpers ----

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
