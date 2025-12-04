<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;

class GetOnBoardByCompetenciesCommand extends Command
{
    protected $signature = 'getonboard:competencies {--pages=1}';
    protected $description = '🌐 Scrapea GetOnBoard por competencia y registra métricas laborales para el Observatorio.';

    protected $capitalMap = [
        'Argentina' => ['city' => 'Buenos Aires', 'lat' => -34.6037, 'lng' => -58.3816],
        'Bolivia' => ['city' => 'La Paz', 'lat' => -16.5, 'lng' => -68.15],
        'Chile' => ['city' => 'Santiago', 'lat' => -33.4489, 'lng' => -70.6693],
        'Colombia' => ['city' => 'Bogotá', 'lat' => 4.711, 'lng' => -74.0721],
        'Ecuador' => ['city' => 'Quito', 'lat' => -0.1807, 'lng' => -78.4678],
        'México' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'Perú' => ['city' => 'Lima', 'lat' => -12.0464, 'lng' => -77.0428],
        'Uruguay' => ['city' => 'Montevideo', 'lat' => -34.9011, 'lng' => -56.1645],
        'Venezuela' => ['city' => 'Caracas', 'lat' => 10.4806, 'lng' => -66.9036],
    ];

  public function handle()
{
    $pages = (int) $this->option('pages');

    // SOLO competencias relacionadas a carreras — usando NAME (español)
    $competencies = Competency::select('id', 'name')
        ->whereNotNull('career_id')
        ->pluck('name', 'id');

    $this->info("🔎 Iniciando scraping de GetOnBoard para {$competencies->count()} competencias...");

    foreach ($competencies as $competencyId => $competencyName) {

        $this->warn("\n💡 Procesando competencia: {$competencyName}");

        $totalFound = 0;
        $totalNew = 0;
        $totalUnmapped = 0;
        $countries = [];
        $modalities = [];

        for ($page = 1; $page <= $pages; $page++) {

            $query = urlencode($competencyName);
            $url = "https://www.getonbrd.com/api/v0/search/jobs?query={$query}&page={$page}&per_page=100";

            try {

                $response = Http::timeout(25)->get($url);

                if ($response->failed()) {
                    $this->warn("❌ Falló la API en {$competencyName} (página {$page})");
                    continue;
                }

                $data = $response->json('data') ?? [];
                $totalFound += count($data);

                foreach ($data as $job) {

                    $attr = $job['attributes'] ?? [];
                    $title       = $attr['title'] ?? 'N/A';
                    $company     = $attr['company']['data']['attributes']['name'] ?? null;
                    $country     = $attr['countries'][0] ?? 'Desconocido';
                    $city        = $attr['city'] ?? null;
                    $modality    = $attr['remote_modality'] ?? 'unknown';
                    $urlJob      = $job['links']['public_url'] ?? null;
                    $externalId  = $job['id'] ?? null;

                    // CONTADORES
                    $countries[$country] = ($countries[$country] ?? 0) + 1;
                    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                    // COORDENADAS
                    [$city, $lat, $lng] = $this->getCoordsFromCountry($city, $country);

                    if (!$lat || !$lng) {
                        $totalUnmapped++;
                        continue;
                    }

                    // DUPLICADOS
                    $existing = JobOffer::where('source', 'GetOnBoard')
                        ->where(function ($q) use ($externalId, $title, $company, $city, $country, $competencyName, $urlJob) {
                            $q->where('external_id', $externalId)
                                ->orWhere(function ($q2) use ($title, $company, $city, $country, $competencyName, $urlJob) {
                                    $q2->whereRaw('LOWER(title) = ?', [strtolower($title)])
                                        ->whereRaw('LOWER(IFNULL(company, "")) = ?', [strtolower($company ?? '')])
                                        ->where('city', $city)
                                        ->where('country', $country)
                                        ->where('search_query', $competencyName)
                                        ->where('url', $urlJob);
                                });
                        })
                        ->first();

                    if ($existing) {
                        $existing->competencies()->syncWithoutDetaching([$competencyId]);
                        continue;
                    }

                    // NORMALIZAR PAÍS
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

                    $desc         = strip_tags($attr['description'] ?? '');
                    $benefitsText = strip_tags($attr['benefits'] ?? '');

                    // Salario
                    $minSalary = $attr['min_salary'] ?? null;
                    $maxSalary = $attr['max_salary'] ?? null;
                    $compType  = $attr['compensation_type'] ?? null;

                    // Moneda detectada
                    $currency = null;
                    if (preg_match('/(USD|MXN|CLP|PEN|COP|ARS|BOB|VEF)/i', $benefitsText, $m)) {
                        $currency = strtoupper($m[1]);
                    }

                    // Experiencia
                    $seniority = $attr['seniority']['data']['id'] ?? null;
                    $experienceYears = null;
                    if (preg_match('/(\d+)\s*(años|years)/i', $desc, $m)) {
                        $experienceYears = (int) $m[1];
                    }

                    // Certificaciones
                    $certifications = [];
                    if (preg_match_all('/(AWS|Azure|Scrum|PMP|Certification|Certified)/i', $desc, $matches)) {
                        $certifications = array_unique($matches[0]);
                    }

                    // CREAR OFERTA
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
                        'years_experience'  => $experienceYears,
                        'certifications'    => !empty($certifications) ? implode(', ', $certifications) : null,
                        'description'       => $desc,
                        'benefits'          => $benefitsText,
                        'salary_min'        => $minSalary,
                        'salary_max'        => $maxSalary,
                        'currency'          => $currency,
                        'compensation_type' => $compType,
                        'source'            => 'GetOnBoard',
                        'search_query'      => $competencyName, // <── correcto para español
                        'external_id'       => $externalId,
                        'url'               => $urlJob,
                        'published_at'      => isset($attr['published_at'])
                            ? Carbon::createFromTimestamp($attr['published_at'])
                            : now(),
                    ]);

                    // VINCULAR COMPETENCIA
                    $offer->competencies()->syncWithoutDetaching([$competencyId]);

                    $this->line("✅ {$title} ({$country} - {$city})");

                    $totalNew++;
                }

                usleep(random_int(600000, 1200000));

            } catch (\Throwable $th) {
                Log::error("⚠️ Error en {$competencyName} (página {$page}): " . $th->getMessage());
            }
        }

        // GUARDAR MÉTRICAS
        CompetencyMetric::updateOrCreate(
            [
                'competency_id' => $competencyId,
                'run_date'      => Carbon::today(),
                'source'        => 'GetOnBoard',
            ],
            [
                'competency_name'     => $competencyName,
                'jobs_found_count'    => $totalFound,
                'jobs_new_count'      => $totalNew,
                'countries_breakdown' => $countries,
                'modality_breakdown'  => $modalities,
            ]
        );

        $this->info("📊 {$competencyName}: {$totalNew} nuevas | 🌎 {$totalUnmapped} sin coords | 📦 {$totalFound} totales");
    }

    $this->info("\n🎯 Scraping + métricas completado exitosamente (GetOnBoard por competencias).");
}


    // ---------------------------------------------------------------------
    // Helpers
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
}
