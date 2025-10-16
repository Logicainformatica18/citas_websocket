<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;

class GetOnBoardByMethodologiesCommand extends Command
{
    protected $signature = 'getonboard:methodologies {--pages=1}';
    protected $description = '📊 Recorre todas las metodologías y guarda métricas de empleos desde GetOnBoard.';

    protected $capitalMap = [
        'Argentina' => ['city' => 'Buenos Aires', 'lat' => -34.6037, 'lng' => -58.3816],
        'Bolivia' => ['city' => 'La Paz', 'lat' => -16.5, 'lng' => -68.15],
        'Chile' => ['city' => 'Santiago', 'lat' => -33.4489, 'lng' => -70.6693],
        'Colombia' => ['city' => 'Bogotá', 'lat' => 4.711, 'lng' => -74.0721],
        'Ecuador' => ['city' => 'Quito', 'lat' => -0.1807, 'lng' => -78.4678],
        'México' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'Peru' => ['city' => 'Lima', 'lat' => -12.0464, 'lng' => -77.0428],
        'Uruguay' => ['city' => 'Montevideo', 'lat' => -34.9011, 'lng' => -56.1645],
        'Venezuela' => ['city' => 'Caracas', 'lat' => 10.4806, 'lng' => -66.9036],
    ];

    protected $geoCache = [];

    public function handle()
    {
        $pages = (int) $this->option('pages');
        $methodologies = Methodology::pluck('name', 'id');

        $this->info("🔎 Iniciando scraping de GetOnBoard por metodología ({$methodologies->count()} metodologías)...");

        foreach ($methodologies as $methodologyId => $methodologyName) {
            $this->warn("\n💡 Procesando metodología: {$methodologyName}");

            $totalFound = 0;
            $totalNew = 0;
            $totalUnmapped = 0;
            $countries = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {
                $url = "https://www.getonbrd.com/api/v0/search/jobs?query=" . urlencode($methodologyName) . "&page={$page}&per_page=100";

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) continue;

                    $data = $response->json('data') ?? [];
                    $totalFound += count($data);

                    foreach ($data as $job) {
                        $attr = $job['attributes'] ?? [];

                        $title = $attr['title'] ?? 'N/A';
                        $company = $attr['company']['data']['attributes']['name'] ?? null;
                        $country = $attr['countries'][0] ?? 'Unknown';
                        $city = $attr['city'] ?? null;
                        $modality = $attr['remote_modality'] ?? 'unknown';
                        $urlJob = $job['links']['public_url'] ?? null;

                        $countries[$country] = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                        [$city, $lat, $lng] = $this->getCoordsFromCountry($city, $country);
                        if (!$lat || !$lng) {
                            $totalUnmapped++;
                            continue;
                        }

                        $exists = JobOffer::where('source', 'GetOnBoard')
                            ->where(function ($q) use ($job, $title, $company, $city, $country, $methodologyName, $urlJob) {
                                $externalId = $job['id'] ?? null;
                                $q->where('external_id', $externalId)
                                  ->orWhere(function ($q2) use ($title, $company, $city, $country, $methodologyName, $urlJob) {
                                      $q2->where('title', $title)
                                         ->where('company', $company)
                                         ->where('city', $city)
                                         ->where('country', $country)
                                         ->where('search_query', $methodologyName)
                                         ->where(function ($q3) use ($urlJob) {
                                             $q3->where('url', $urlJob)
                                                ->orWhere('url', 'like', '%' . substr($urlJob, -25) . '%');
                                         });
                                  });
                            })
                            ->exists();

                        if ($exists) continue;

                        JobOffer::create([
                            'title' => $title,
                            'company' => $company,
                            'country' => $country,
                            'city' => $city,
                            'modality' => $modality,
                            'latitude' => $lat,
                            'longitude' => $lng,
                            'source' => 'GetOnBoard',
                            'search_query' => $methodologyName,
                            'external_id' => $job['id'] ?? null,
                            'url' => $urlJob,
                            'published_at' => isset($attr['published_at'])
                                ? Carbon::createFromTimestamp($attr['published_at'])
                                : now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $totalNew++;
                    }

                    sleep(1.5);

                } catch (\Throwable $th) {
                    Log::error("⚠️ Error en {$methodologyName} (página {$page}): " . $th->getMessage());
                }
            }

            // 📆 Métrica del día
            $today = now()->toDateString();

            $existsToday = MethodologyMetric::whereDate('run_date', $today)
                ->where('methodology_id', $methodologyId)
                ->where('source', 'GetOnBoard')
                ->exists();

            if ($existsToday) {
                $this->warn("📆 Ya existe una métrica registrada hoy ({$today}) para {$methodologyName}, se omite.");
                continue;
            }

            MethodologyMetric::create([
                'methodology_id' => $methodologyId,
                'methodology_name' => $methodologyName,
                'jobs_found_count' => $totalFound,
                'jobs_new_count' => $totalNew,
                'countries_breakdown' => $countries,
                'modality_breakdown' => $modalities,
                'run_date' => now(),
                'source' => 'GetOnBoard',
            ]);

            $this->info("✅ {$methodologyName}: {$totalNew} nuevas | 🌎 {$totalUnmapped} sin coords | 📦 {$totalFound} encontradas");
        }

        $this->info("\n🎯 Proceso completado: todas las métricas registradas.");
    }

    // 🌍 Geocodificación (igual que en languages)
    protected function getCoordsFromCountry(?string $city, ?string $country)
    {
        if ($city && strtolower($city) !== 'remoto') {
            [$lat, $lng] = $this->getCoords($city, $country);
            if ($lat && $lng) return [$city, $lat, $lng];
        }

        if (!$country || !isset($this->capitalMap[$country])) {
            return ['Lima', -12.0464, -77.0428];
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
                return $this->geoCache[$key] = [(float)$data['lat'], (float)$data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $th->getMessage());
        }

        return $this->geoCache[$key] = [null, null];
    }
}
