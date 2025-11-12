<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper; // ✅ importante: asegúrate de importar el helper

class GetOnBoardByMethodologiesCommand extends Command
{
    protected $signature = 'getonboard:methodologies {--pages=1}';
    protected $description = '📊 Recorre todas las metodologías y guarda métricas de empleos desde GetOnBoard.';

    protected $capitalMap = [
        'Argentina'  => ['city' => 'Buenos Aires', 'lat' => -34.6037, 'lng' => -58.3816],
        'Bolivia'    => ['city' => 'La Paz', 'lat' => -16.5, 'lng' => -68.15],
        'Chile'      => ['city' => 'Santiago', 'lat' => -33.4489, 'lng' => -70.6693],
        'Colombia'   => ['city' => 'Bogotá', 'lat' => 4.711, 'lng' => -74.0721],
        'Ecuador'    => ['city' => 'Quito', 'lat' => -0.1807, 'lng' => -78.4678],
        'México'     => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'Perú'       => ['city' => 'Lima', 'lat' => -12.0464, 'lng' => -77.0428],
        'Uruguay'    => ['city' => 'Montevideo', 'lat' => -34.9011, 'lng' => -56.1645],
        'Venezuela'  => ['city' => 'Caracas', 'lat' => 10.4806, 'lng' => -66.9036],
    ];

    protected $geoCache = [];

    public function handle()
    {
        $pages = (int) $this->option('pages');

        // ✅ Mejor versión: solo metodologías realmente asociadas a carreras
        $methodologies = Methodology::whereIn('methodologies.id', function ($q) {
            $q->select('course_methodology.methodology_id')
              ->from('course_methodology')
              ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->pluck('name', 'id');

        $this->info("🔎 Iniciando scraping de GetOnBoard por metodología ({$methodologies->count()} metodologías)...");

        foreach ($methodologies as $methodologyId => $methodologyName) {
            $this->warn("\n💡 Procesando metodología: {$methodologyName}");

            $totalFound = $totalNew = $totalUnmapped = 0;
            $countries = $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {
                $url = "https://www.getonbrd.com/api/v0/search/jobs?query=" . urlencode($methodologyName) . "&page={$page}&per_page=100";

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) continue;

                    $data = $response->json('data') ?? [];
                    $totalFound += count($data);

                    foreach ($data as $job) {
                        $attr = $job['attributes'] ?? [];

                        $title     = $attr['title'] ?? 'N/A';
                        $company   = $attr['company']['data']['attributes']['name'] ?? null;
                        $country   = $attr['countries'][0] ?? 'Desconocido';
                        $city      = $attr['city'] ?? null;
                        $modality  = $attr['remote_modality'] ?? 'unknown';
                        $urlJob    = $job['links']['public_url'] ?? null;
                        $externalId = $job['id'] ?? null;

                        $countries[$country]  = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                        [$city, $lat, $lng] = $this->getCoordsFromCountry($city, $country);
                        if (!$lat || !$lng) {
                            $totalUnmapped++;
                            continue;
                        }

                        // 🚫 Evitar duplicados
                        $exists = JobOffer::where('source', 'GetOnBoard')
                            ->where(function ($q) use ($externalId, $title, $company, $city, $country, $methodologyName, $urlJob) {
                                $q->where('external_id', $externalId)
                                  ->orWhere(function ($q2) use ($title, $company, $city, $country, $methodologyName, $urlJob) {
                                      $q2->whereRaw('LOWER(title) = ?', [strtolower($title)])
                                          ->whereRaw('LOWER(IFNULL(company, "")) = ?', [strtolower($company ?? '')])
                                          ->where('city', $city)
                                          ->where('country', $country)
                                          ->where('search_query', $methodologyName)
                                          ->where('url', $urlJob);
                                  });
                            })
                            ->exists();

                        if ($exists) continue;

                        // 💾 Extraer datos extendidos
                        $desc         = strip_tags($attr['description'] ?? '');
                        $benefitsText = strip_tags($attr['benefits'] ?? '');
                        $seniority    = $attr['seniority']['data']['id'] ?? null;
                        $category     = $attr['category_name'] ?? null;
                        $minSalary    = $attr['min_salary'] ?? null;
                        $maxSalary    = $attr['max_salary'] ?? null;
                        $compType     = $attr['compensation_type'] ?? null;
                        $currency     = null;

                        // 🪙 Detectar moneda si no viene explícita
                        if (preg_match('/(USD|MXN|CLP|PEN|COP|ARS|BOB|VEF)/i', $benefitsText, $m)) {
                            $currency = strtoupper($m[1]);
                        }

                        // 💼 Experiencia / certificaciones
                        $experienceYears = null;
                        $certifications  = [];

                        if (preg_match('/(\d+)[–\-–+]?\s*(?:\+)?\s*(years?|años?)\s+of\s+experience/i', $desc, $m)) {
                            $experienceYears = (int) $m[1];
                        }

                        if (preg_match_all('/(AWS|Azure|Scrum|PMP|Certification|Certified)/i', $desc, $matches)) {
                            $certifications = array_unique($matches[0]);
                        }

                        // 💾 Guardar oferta
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
                            'certifications'    => !empty($certifications) ? implode(', ', $certifications) : null,
                            'description'       => $desc,
                            'benefits'          => $benefitsText,
                            'salary_min'        => $minSalary,
                            'salary_max'        => $maxSalary,
                            'currency'          => $currency,
                            'compensation_type' => $compType,
                            'source'            => 'GetOnBoard',
                            'search_query'      => $methodologyName,
                            'external_id'       => $externalId,
                            'url'               => $urlJob,
                            'published_at'      => isset($attr['published_at'])
                                                     ? Carbon::createFromTimestamp($attr['published_at'])
                                                     : now(),
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);

                        // 🔗 Relación metodología ↔ oferta
                        $offer->methodologies()->syncWithoutDetaching([$methodologyId]);

                        $totalNew++;
                        $this->line("✅ {$title} ({$country} - {$city}) 💰{$minSalary}-{$maxSalary} {$currency}");
                    }

                    usleep(random_int(600000, 1200000)); // 0.6–1.2 seg

                } catch (\Throwable $th) {
                    Log::error("⚠️ Error en {$methodologyName} (página {$page}): " . $th->getMessage());
                }
            }

            // 📈 Registrar métricas
            MethodologyMetric::updateOrCreate(
                [
                    'methodology_id' => $methodologyId,
                    'run_date'       => Carbon::today(),
                    'source'         => 'GetOnBoard',
                ],
                [
                    'methodology_name'     => $methodologyName,
                    'jobs_found_count'     => $totalFound,
                    'jobs_new_count'       => $totalNew,
                    'countries_breakdown'  => $countries,
                    'modality_breakdown'   => $modalities,
                    'updated_at'           => now(),
                ]
            );

            $this->info("📊 {$methodologyName}: {$totalNew} nuevas | 🌎 {$totalUnmapped} sin coords | 📦 {$totalFound} totales");
        }

        $this->info("\n🎯 Proceso completado exitosamente (GetOnBoard).");
    }

    // 🌍 Helpers de geolocalización
    protected function getCoordsFromCountry(?string $city, ?string $country)
    {
        if ($city && strtolower($city) !== 'remoto') {
            [$lat, $lng] = $this->getCoords($city, $country);
            if ($lat && $lng) return [$city, $lat, $lng];
        }

        if (!$country || !isset($this->capitalMap[$country])) {
            return [$city ?? 'Desconocido', -12.0464, -77.0428];
        }

        $capital = $this->capitalMap[$country];
        return [$capital['city'], $capital['lat'], $capital['lng']];
    }

    protected function getCoords(?string $city, ?string $country)
    {
        $key = strtolower(trim("{$city},{$country}"));
        if (isset($this->geoCache[$key])) return $this->geoCache[$key];

        try {
            $res = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$city}, {$country}",
                'format' => 'json',
                'limit' => 1,
            ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                return $this->geoCache[$key] = [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $th->getMessage());
        }

        return $this->geoCache[$key] = [null, null];
    }
}
