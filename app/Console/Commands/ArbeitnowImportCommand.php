<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\JobOffer;
use App\Models\City;
use Carbon\Carbon;

class ArbeitnowImportCommand extends Command
{
    /*
    Ejemplos de ejecución:
    php artisan arbeitnow:import --query=developer
    php artisan arbeitnow:import --query=python
    php artisan arbeitnow:import --query=ai
    php artisan arbeitnow:import --query=marketing
    */

    protected $signature = 'arbeitnow:import {--query=}';
    protected $description = '🌍 Importa ofertas desde Arbeitnow (Europa, Asia y Remoto) con geolocalización automática y modalidades estandarizadas.';

    protected $stats = ['api_hits' => 0, 'fallback' => 0, 'mapped' => 0];

    /**
     * 🏙️ Capitales por país (Europa + Asia + Remote)
     */
    protected $capitalMap = [
        // 🇩🇪 Europa Central
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'at' => ['city' => 'Viena', 'lat' => 48.2082, 'lng' => 16.3738],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122],

        // 🇪🇸 Europa Occidental
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'pt' => ['city' => 'Lisboa', 'lat' => 38.7169, 'lng' => -9.1399],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964],
        'be' => ['city' => 'Bruselas', 'lat' => 50.8503, 'lng' => 4.3517],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041],

        // 🇬🇧 Islas
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'ie' => ['city' => 'Dublín', 'lat' => 53.3498, 'lng' => -6.2603],

        // 🇳🇴 Escandinavia
        'se' => ['city' => 'Estocolmo', 'lat' => 59.3293, 'lng' => 18.0686],
        'dk' => ['city' => 'Copenhague', 'lat' => 55.6761, 'lng' => 12.5683],
        'fi' => ['city' => 'Helsinki', 'lat' => 60.1699, 'lng' => 24.9384],
        'no' => ['city' => 'Oslo', 'lat' => 59.9139, 'lng' => 10.7522],

        // 🇮🇳 Asia
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'jp' => ['city' => 'Tokio', 'lat' => 35.6895, 'lng' => 139.6917],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],
        'kr' => ['city' => 'Seúl', 'lat' => 37.5665, 'lng' => 126.9780],
        'cn' => ['city' => 'Pekín', 'lat' => 39.9042, 'lng' => 116.4074],
        'ph' => ['city' => 'Manila', 'lat' => 14.5995, 'lng' => 120.9842],
        'vn' => ['city' => 'Hanói', 'lat' => 21.0285, 'lng' => 105.8542],
        'th' => ['city' => 'Bangkok', 'lat' => 13.7563, 'lng' => 100.5018],
        'id' => ['city' => 'Yakarta', 'lat' => -6.2088, 'lng' => 106.8456],
        'my' => ['city' => 'Kuala Lumpur', 'lat' => 3.1390, 'lng' => 101.6869],
        'tw' => ['city' => 'Taipéi', 'lat' => 25.0330, 'lng' => 121.5654],
        'hk' => ['city' => 'Hong Kong', 'lat' => 22.3193, 'lng' => 114.1694],

        // 🌍 Fallback global
        'remote' => ['city' => 'Remoto', 'lat' => 0.0000, 'lng' => 0.0000],
    ];

    public function handle()
    {
        $query = $this->option('query');
        $this->info("🌐 Importando desde Arbeitnow para '{$query}'...");

        $response = Http::timeout(20)->get('https://www.arbeitnow.com/api/job-board-api', [
            'search' => $query
        ]);

        if ($response->failed()) {
            $this->error("❌ Error al consultar Arbeitnow");
            return;
        }

        $jobs = $response->json()['data'] ?? $response->json() ?? [];
        $saved = $duplicates = 0;

        foreach ($jobs as $job) {
            $title = $job['title'] ?? 'N/A';
            $company = $job['company_name'] ?? null;
            $location = $job['location'] ?? '';
            $isRemote = $job['remote'] ?? false;
            $urlJob = $job['url'] ?? null;

            // 🌍 Detectar país desde ubicación o remoto
            $countryCode = $this->detectCountryCode($location, $isRemote);

            // 🧭 Buscar coordenadas
            [$city, $lat, $lng] = $this->getCoordsFromCountry($location, $countryCode);

        // 🧩 Fallback si no hay coordenadas válidas
if (!$lat || !$lng) {
    // 🚫 Ignorar registros sin país detectado
    if (empty($countryCode)) {
        $this->warn("⚠️ Oferta ignorada: no se detectó país para '{$title}' ({$location})");
        continue;
    }

    // 🗺️ Si hay país válido, usar su capital como fallback
    if (isset($this->capitalMap[$countryCode])) {
        $capital = $this->capitalMap[$countryCode];
        $city = $capital['city'];
        $lat = $capital['lat'];
        $lng = $capital['lng'];
        $this->stats['fallback']++;
        $this->warn("⚠️ {$countryCode}: sin coordenadas válidas, usando capital {$city}");
    } else {
        // Si el país no está en la lista, simplemente saltar
        $this->warn("⚠️ País no reconocido: {$countryCode}. Oferta ignorada.");
        continue;
    }
}


         $modality = 'remote'; // valor por defecto
$locLower = strtolower($location ?? '');

// 🧠 Normalizar modalidad según tu estándar global
if ($isRemote === true || str_contains($locLower, 'remote')) {
    $modality = 'fully_remote';          // 🟢 Remoto total
} elseif (str_contains($locLower, 'hybrid') || str_contains($locLower, 'híbrido')) {
    $modality = 'hybrid';               // 🟡 Híbrido
} elseif ($isRemote === false || str_contains($locLower, 'no remote') || str_contains($locLower, 'presencial')) {
    $modality = 'no_remote';             // 🔴 Presencial o sin opción remota
} else {
    $modality = 'remote';          // 🔵 Ambiguo o semi-remoto (fallback)
}

// 🔁 Evitar duplicados
if (!empty($job['slug']) && JobOffer::where('external_id', $job['slug'])->exists()) {
    $duplicates++;
    continue;
}


            // 💾 Crear registro
            JobOffer::create([
                'title'        => $title,
                'company'      => $company,
                'country'      => strtoupper($countryCode ?? 'XX'),
                'city'         => $city,
                'latitude'     => $lat,
                'longitude'    => $lng,
                'modality'     => $modality,
                'source'       => 'Arbeitnow',
                'external_id'  => $job['slug'] ?? null,
                'url'          => $urlJob,
                'currency'     => 'EUR',
                'salary_min'   => null,
                'salary_max'   => null,
                'search_query' => $query,
                'published_at' => isset($job['date']) ? Carbon::parse($job['date']) : now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $saved++;
        }

        $this->info("✅ Guardadas: {$saved} | 🔁 Duplicadas: {$duplicates} | 🗺️ API: {$this->stats['api_hits']} | 📍 Cities: {$this->stats['mapped']} | 🧭 Fallbacks: {$this->stats['fallback']}");
    }

    protected function detectCountryCode($location, $isRemote)
    {
        $loc = strtolower($location ?? '');
        if ($isRemote) return 'remote';

        return match (true) {
            // 🌍 Europa
            str_contains($loc, 'germany') => 'de',
            str_contains($loc, 'spain') => 'es',
            str_contains($loc, 'france') => 'fr',
            str_contains($loc, 'portugal') => 'pt',
            str_contains($loc, 'italy') => 'it',
            str_contains($loc, 'netherlands') => 'nl',
            str_contains($loc, 'switzerland') => 'ch',
            str_contains($loc, 'poland') => 'pl',
            str_contains($loc, 'austria') => 'at',
            str_contains($loc, 'uk') || str_contains($loc, 'united kingdom') => 'gb',
            str_contains($loc, 'ireland') => 'ie',
            str_contains($loc, 'sweden') => 'se',
            str_contains($loc, 'denmark') => 'dk',
            str_contains($loc, 'finland') => 'fi',
            str_contains($loc, 'norway') => 'no',

            // 🌏 Asia
            str_contains($loc, 'japan') => 'jp',
            str_contains($loc, 'india') => 'in',
            str_contains($loc, 'singapore') => 'sg',
            str_contains($loc, 'korea') => 'kr',
            str_contains($loc, 'china') => 'cn',
            str_contains($loc, 'philippines') => 'ph',
            str_contains($loc, 'vietnam') => 'vn',
            str_contains($loc, 'thailand') => 'th',
            str_contains($loc, 'indonesia') => 'id',
            str_contains($loc, 'malaysia') => 'my',
            str_contains($loc, 'taiwan') => 'tw',
            str_contains($loc, 'hong kong') => 'hk',
            default => null
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {
            $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)]))
                ->first();

            if ($found) {
                $this->stats['mapped']++;
                return [$found->city, $found->lat, $found->lng];
            }

            [$lat, $lng] = $this->getCoords($city, $countryCode);
            if ($lat && $lng) {
                $this->stats['api_hits']++;
                return [$city, $lat, $lng];
            }
        }

        return [$city, null, null];
    }

    protected function getCoords($city, $country)
    {
        try {
            $res = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$city}" . ($country ? ", {$country}" : ''),
                'format' => 'json',
                'limit' => 1
            ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $e) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $e->getMessage());
        }

        return [null, null];
    }
}
