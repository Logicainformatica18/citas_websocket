<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;

class GetOnBoardByTechnologiesCommand extends Command
{
    protected $signature = 'getonboard:technologies {--pages=1}';
    protected $description = '🌐 Scrapea GetOnBoard por tecnología y registra métricas laborales.';

    protected $capitalMap = [
        'Argentina' => ['city' => 'Buenos Aires', 'lat' => -34.6037, 'lng' => -58.3816],
        'Bolivia'   => ['city' => 'La Paz', 'lat' => -16.5, 'lng' => -68.15],
        'Chile'     => ['city' => 'Santiago', 'lat' => -33.4489, 'lng' => -70.6693],
        'Colombia'  => ['city' => 'Bogotá', 'lat' => 4.711, 'lng' => -74.0721],
        'Ecuador'   => ['city' => 'Quito', 'lat' => -0.1807, 'lng' => -78.4678],
        'México'    => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'Perú'      => ['city' => 'Lima', 'lat' => -12.0464, 'lng' => -77.0428],
        'Uruguay'   => ['city' => 'Montevideo', 'lat' => -34.9011, 'lng' => -56.1645],
        'Venezuela' => ['city' => 'Caracas', 'lat' => 10.4806, 'lng' => -66.9036],
    ];

    public function handle()
    {
        // ▶️ Iniciar RUN del scraper
        $run = ScraperRunService::start(
            $this->signature,
            'GetOnBoard',
            'technologies'
        );

        try {

            $pages = (int) $this->option('pages');

            // 🔢 Contadores globales
            $totalFoundAll    = 0;
            $totalInsertedAll = 0;
            $totalSkippedAll  = 0;

            $lastTechnologyId = TechnologyMetric::where('source', 'GetOnBoard')
    ->orderByDesc('created_at')
    ->value('technology_id');


          $baseQuery = Technology::whereIn('technologies.id', function ($q) {
        $q->select('course_technology.technology_id')
          ->from('course_technology')
          ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
    })
    ->orderBy('technologies.id');

$technologiesQuery = clone $baseQuery;

if ($lastTechnologyId) {
    $technologiesQuery->where('technologies.id', '>', $lastTechnologyId);
}

$technologies = $technologiesQuery->get();

if ($technologies->isEmpty()) {
    // 🔁 ciclo completo → volver al inicio
    $technologies = $baseQuery->get();
}


            $this->info("🔎 Iniciando scraping de GetOnBoard para {$technologies->count()} tecnologías...");

         foreach ($technologies as $technology) {

    $technologyId   = $technology->id;
    $technologyName = $technology->name;


                $this->warn("\n💡 Procesando tecnología: {$technologyName}");

                $totalFound    = 0;
                $totalNew      = 0;
                $totalUnmapped = 0;
                $countries     = [];
                $modalities    = [];

                for ($page = 1; $page <= $pages; $page++) {

                    $url = "https://www.getonbrd.com/api/v0/search/jobs?query="
                        . urlencode($technologyName)
                        . "&page={$page}&per_page=100";

                    try {
                        $response = Http::timeout(25)->get($url);

                        if ($response->failed()) {
                            $this->warn("❌ Falló la API en {$technologyName} (página {$page})");
                            $totalSkippedAll++;
                            continue;
                        }

                        $data = $response->json('data') ?? [];
                        $totalFound += count($data);

                        foreach ($data as $job) {

                            $attr       = $job['attributes'] ?? [];
                            $title      = $attr['title'] ?? 'N/A';
                            $company    = $attr['company']['data']['attributes']['name'] ?? null;
                            $country    = $attr['countries'][0] ?? 'Desconocido';
                            $city       = $attr['city'] ?? null;

                            // ✅ Normalización limpia de modalidad GetOnBoard
                            $rawModality = $attr['remote_modality'] ?? null;
                            $modality    = $this->normalizeGetOnBoardModality($rawModality);

                            $urlJob     = $job['links']['public_url'] ?? null;
                            $externalId = $job['id'] ?? null;

                            // 📊 Métricas
                            $countries[$country]   = ($countries[$country] ?? 0) + 1;
                            $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                            // 🧭 Coordenadas
                            [$city, $lat, $lng] = $this->getCoordsFromCountry($city, $country);

                            if (!$lat || !$lng) {
                                $totalUnmapped++;
                                $totalSkippedAll++;
                                continue;
                            }

                            // 🔍 Duplicado
                            $existingOffer = JobOffer::where('source', 'GetOnBoard')
                                ->where('external_id', $externalId)
                                ->first();

                            if ($existingOffer) {
                                $existingOffer->technologies()
                                    ->syncWithoutDetaching([$technologyId]);
                                $totalSkippedAll++;
                                continue;
                            }

                            // 🌍 Normalizar país
                            $country = match (strtolower($country)) {
                                'peru' => 'Perú',
                                'mexico' => 'México',
                                'colombia' => 'Colombia',
                                'argentina' => 'Argentina',
                                'uruguay' => 'Uruguay',
                                'ecuador' => 'Ecuador',
                                'venezuela' => 'Venezuela',
                                'bolivia' => 'Bolivia',
                                'chile' => 'Chile',
                                default => ucfirst($country),
                            };

                            // 📄 Textos
                            $desc         = strip_tags($attr['description'] ?? '');
                            $benefitsText = strip_tags($attr['benefits'] ?? '');

                            // 📊 Campos adicionales
                            $seniority = $attr['seniority']['data']['id'] ?? null;
                            $minSalary = $attr['min_salary'] ?? null;
                            $maxSalary = $attr['max_salary'] ?? null;
                            $compType  = $attr['compensation_type'] ?? null;
                            $currency  = null;

                            if (preg_match('/(USD|MXN|CLP|PEN|COP|ARS|BOB)/i', $benefitsText, $m)) {
                                $currency = strtoupper($m[1]);
                            }

                            // 💾 Crear oferta
                            $offer = JobOffer::create([
                                'title'             => $title,
                                'company'           => $company,
                                'country'           => $country,
                                'region'            => RegionHelper::fromCountry($country),
                                'city'              => $city,
                                'latitude'          => $lat,
                                'longitude'         => $lng,
                                'modality'          => $modality,
                                'experience_level'  => $seniority,
                                'description'       => $desc,
                                'benefits'          => $benefitsText,
                                'salary_min'        => $minSalary,
                                'salary_max'        => $maxSalary,
                                'currency'          => $currency,
                                'compensation_type' => $compType,
                                'source'            => 'GetOnBoard',
                                'search_query'      => $technologyName,
                                'external_id'       => $externalId,
                                'url'               => $urlJob,
                                'published_at'      => isset($attr['published_at'])
                                                        ? Carbon::createFromTimestamp($attr['published_at'])
                                                        : now(),
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ]);

                            $offer->technologies()
                                  ->syncWithoutDetaching([$technologyId]);

                            $totalNew++;
                        }

                        usleep(random_int(600000, 1200000));

                    } catch (\Throwable $th) {
                        Log::error("⚠️ Error en {$technologyName} (página {$page}): {$th->getMessage()}");
                        $totalSkippedAll++;
                    }
                }

                // 📊 Métrica diaria por tecnología
                TechnologyMetric::updateOrCreate(
                    [
                        'technology_id' => $technologyId,
                        'run_date'      => Carbon::today(),
                        'source'        => 'GetOnBoard',
                    ],
                    [
                        'technology_name'    => $technologyName,
                        'jobs_found_count'   => $totalFound,
                        'jobs_new_count'     => $totalNew,
                        'countries_breakdown'=> $countries,
                        'modality_breakdown' => $modalities,
                        'updated_at'         => now(),
                    ]
                );

                $totalFoundAll    += $totalFound;
                $totalInsertedAll += $totalNew;
            }

            ScraperRunService::success(
                $run,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );

            $this->info("\n🎯 Scraping + métricas completado exitosamente (GetOnBoard Technologies).");

        } catch (\Throwable $e) {
            ScraperRunService::failed($run, $e);
            throw $e;
        }
    }
    // ---------------------------------------------------------------------
    // 🔧 Helpers
    // ---------------------------------------------------------------------

    protected function getCoordsFromCountry(?string $city, ?string $country)
    {
        if ($city && strtolower($city) !== 'remoto') {
            [$lat, $lng] = $this->getCoords($city, $country);
            if ($lat && $lng) {
                return [$city, $lat, $lng];
            }
        }

        if (!$country || !isset($this->capitalMap[$country])) {
            return [$city ?? 'Desconocido', -12.0464, -77.0428];
        }

        $capital = $this->capitalMap[$country];
        return [$capital['city'], $capital['lat'], $capital['lng']];
    }

    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
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

    protected function normalizeGetOnBoardModality(?string $raw): string
    {
        return match (strtolower($raw ?? '')) {
            'fully_remote', 'remote_local' => 'remote',
            'hybrid'                      => 'hybrid',
            'no_remote'                   => 'presencial',
            default                       => 'no_precisa',
        };
    }
}
 