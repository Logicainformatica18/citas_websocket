<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\JobOffer;
use Carbon\Carbon;

class GetOnBoardImportCommand extends Command
{
    protected $signature = 'getonboard:import {--query=programador} {--pages=1}';
    protected $description = '📡 Importa ofertas laborales desde GetOnBoard, usando la capital del país como coordenada cuando no hay ciudad.';

    protected $stats = [
        'api_hits' => 0,
        'fallback' => 0,
        'mapped' => 0,
    ];

    // 🌎 Base de capitales latinoamericanas
    protected $capitalMap = [
        'Argentina' => ['city' => 'Buenos Aires', 'lat' => -34.6037, 'lng' => -58.3816],
        'Bolivia' => ['city' => 'La Paz', 'lat' => -16.5000, 'lng' => -68.1500],
        'Chile' => ['city' => 'Santiago', 'lat' => -33.4489, 'lng' => -70.6693],
        'Colombia' => ['city' => 'Bogotá', 'lat' => 4.7110, 'lng' => -74.0721],
        'Ecuador' => ['city' => 'Quito', 'lat' => -0.1807, 'lng' => -78.4678],
        'México' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'Peru' => ['city' => 'Lima', 'lat' => -12.0464, 'lng' => -77.0428],
        'Uruguay' => ['city' => 'Montevideo', 'lat' => -34.9011, 'lng' => -56.1645],
        'Venezuela' => ['city' => 'Caracas', 'lat' => 10.4806, 'lng' => -66.9036],
        'Paraguay' => ['city' => 'Asunción', 'lat' => -25.2637, 'lng' => -57.5759],
        'Costa Rica' => ['city' => 'San José', 'lat' => 9.9281, 'lng' => -84.0907],
        'Panamá' => ['city' => 'Panamá', 'lat' => 8.9824, 'lng' => -79.5199],
        'Guatemala' => ['city' => 'Ciudad de Guatemala', 'lat' => 14.6349, 'lng' => -90.5069],
        'El Salvador' => ['city' => 'San Salvador', 'lat' => 13.6929, 'lng' => -89.2182],
        'Honduras' => ['city' => 'Tegucigalpa', 'lat' => 14.0723, 'lng' => -87.1921],
        'Nicaragua' => ['city' => 'Managua', 'lat' => 12.1364, 'lng' => -86.2514],
        'Cuba' => ['city' => 'La Habana', 'lat' => 23.1136, 'lng' => -82.3666],
        'Puerto Rico' => ['city' => 'San Juan', 'lat' => 18.4655, 'lng' => -66.1057],
        'República Dominicana' => ['city' => 'Santo Domingo', 'lat' => 18.4861, 'lng' => -69.9312],
    ];

    public function handle()
    {
        $query = strtolower($this->option('query'));
        $pages = (int) $this->option('pages');

        $this->info("🔍 Buscando ofertas en GetOnBoard para '{$query}' ({$pages} página/s)...");

        $totalSaved = 0;
        $totalDuplicates = 0;
        $totalUnmapped = 0;

        for ($page = 1; $page <= $pages; $page++) {
            $url = "https://www.getonbrd.com/api/v0/search/jobs?query=" . urlencode($query) . "&page={$page}&per_page=100";

            try {
                $response = Http::timeout(25)->get($url);

                if ($response->failed()) {
                    $this->error("❌ Error al consultar la API en página {$page}");
                    continue;
                }

                $data = $response->json('data') ?? [];
                if (empty($data)) {
                    $this->warn("⚠️ No se encontraron resultados en la página {$page}");
                    continue;
                }

                $saved = 0;
                $duplicates = 0;
                $unmapped = 0;

                foreach ($data as $job) {
                    $attr = $job['attributes'] ?? [];

                    $title = $attr['title'] ?? 'N/A';
                    $company = $attr['company']['data']['attributes']['name'] ?? null;
                    $country = $attr['countries'][0] ?? 'Desconocido';
                    $city = $attr['city'] ?? null;
                    $modality = $attr['remote_modality'] ?? 'unknown';
                    $urlJob = $job['links']['public_url'] ?? null;

                    // 🧭 Mapear coordenadas
                    [$city, $latitude, $longitude] = $this->getCoordsFromCountry($city, $country);

                    if (!$latitude || !$longitude) {
                        $unmapped++;
                        continue;
                    }

                    // 🔎 Evita duplicados
                    $exists = JobOffer::where('url', $urlJob)->exists();
                    if ($exists) {
                        $duplicates++;
                        continue;
                    }

                    JobOffer::create([
                        'title' => $title,
                        'company' => $company,
                        'country' => $country,
                        'city' => $city,
                        'modality' => $modality,
                        'salary_min' => $attr['min_salary'] ?? null,
                        'salary_max' => $attr['max_salary'] ?? null,
                        'currency' => $attr['salary_currency'] ?? 'USD',
                        'experience_level' => $attr['experience_level'] ?? null,
                        'category' => $attr['category_name'] ?? null,
                        'role' => $attr['role'] ?? null,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'source' => 'GetOnBoard',
                        'search_query' => $query,
                        'external_id' => $job['id'] ?? null,
                        'url' => $urlJob,
                        'published_at' => isset($attr['published_at'])
                            ? Carbon::createFromTimestamp($attr['published_at'])
                            : now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $saved++;
                }

                $this->line("📄 Página {$page}: ✅ {$saved} guardadas | 🔁 {$duplicates} duplicadas | ⚠️ {$unmapped} sin coordenadas");
                $totalSaved += $saved;
                $totalDuplicates += $duplicates;
                $totalUnmapped += $unmapped;

                sleep(1.5);

            } catch (\Throwable $e) {
                $this->error("⚠️ Error en página {$page}: " . $e->getMessage());
                Log::error("GetOnBoard API error (página {$page}): " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("🎯 Importación finalizada para '{$query}':");
        $this->line("   ✅ {$totalSaved} guardadas");
        $this->line("   🔁 {$totalDuplicates} duplicadas");
        $this->line("   ⚠️ {$totalUnmapped} sin coordenadas");
        $this->line("   📊 Asignadas desde capitales: {$this->stats['fallback']}");
    }

    // 🌍 Devuelve coordenadas de capital si no hay ciudad
    protected function getCoordsFromCountry(?string $city, ?string $country)
    {
        if ($city && strtolower($city) !== 'remoto') {
            [$lat, $lng] = $this->getCoords($city, $country);
            if ($lat && $lng) {
                $this->stats['mapped']++;
                return [$city, $lat, $lng];
            }
        }

        // 🔹 Usa capital por país si no hay ciudad o no se pudo mapear
        if (!$country || !isset($this->capitalMap[$country])) {
            $this->warn("⚠️ País no reconocido: {$country}, usando Lima por defecto");
            $this->stats['fallback']++;
            return ['Lima', -12.0464, -77.0428];
        }

        $this->stats['fallback']++;
        $capital = $this->capitalMap[$country];
        $this->line("📍 {$country} → {$capital['city']} ({$capital['lat']}, {$capital['lng']})");
        return [$capital['city'], $capital['lat'], $capital['lng']];
    }

    // 🌐 Intenta geocodificar ciudad con Nominatim
    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::withHeaders([
                'User-Agent' => 'LaravelJobScraper/1.0'
            ])->timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$city}, {$country}",
                'format' => 'json',
                'limit' => 1,
            ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                $this->stats['api_hits']++;
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $th->getMessage());
        }

        return [null, null];
    }
}
