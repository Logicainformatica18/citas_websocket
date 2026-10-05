<?php

namespace App\Helpers;

use App\Models\City;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Utilidades comunes a los comandos de Computrabajo.
 */
class ComputrabajoHelper
{
    /** Coordenadas ya resueltas en este proceso, por "iso2|ciudad". */
    private static array $coordsCache = [];

    private static float $lastNominatimCall = 0.0;

    /**
     * URL canónica de una oferta: sin fragmento (#lc=ListOffers-…, que cambia en
     * cada búsqueda), sin parámetros y sin barra final.
     */
    public static function canonicalUrl(string $url): string
    {
        $url = explode('#', $url, 2)[0];
        $url = explode('?', $url, 2)[0];

        return rtrim($url, '/');
    }

    /**
     * Ubica la ciudad solo dentro del país del dominio ($code = iso2, p. ej. "mx").
     * Primero en el catálogo cities; si no está, en Nominatim restringido al país.
     * Si no la encuentra, devuelve coordenadas vacías: nunca las de otro país.
     *
     * @return array{0: ?string, 1: ?float, 2: ?float} [ciudad, lat, lng]
     */
    public static function coords(?string $city, string $country, string $code): array
    {
        if (!$city || strtolower($city) === 'remote') {
            return [$city, null, null];
        }

        $key = strtolower($code) . '|' . mb_strtolower($city);

        return self::$coordsCache[$key] ??= self::lookup($city, $country, $code);
    }

    private static function lookup(string $city, string $country, string $code): array
    {
        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->whereRaw('LOWER(iso2) = ?', [strtolower($code)])
            ->orderByDesc('population')
            ->first();

        if ($found) {
            return [$found->city, (float) $found->lat, (float) $found->lng];
        }

        $geo = self::geocode($city, $country, $code);

        return [$city, $geo['lat'] ?? null, $geo['lng'] ?? null];
    }

    /**
     * Consulta Nominatim restringido al país ($code = iso2), respetando su política
     * de uso: como máximo una petición por segundo.
     *
     * @return array{lat: float, lng: float, display_name: string}|null
     */
    public static function geocode(string $city, string $country, string $code): ?array
    {
        try {
            $wait = 1.0 - (microtime(true) - self::$lastNominatimCall);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
            self::$lastNominatimCall = microtime(true);

            $res = Http::withHeaders(['User-Agent' => 'ObservatorioISIL/1.0'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q'            => "{$city}, {$country}",
                    'countrycodes' => strtolower($code),
                    'format'       => 'json',
                    'limit'        => 1,
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];

                return ['lat' => (float) $data['lat'], 'lng' => (float) $data['lon'], 'display_name' => $data['display_name'] ?? ''];
            }
        } catch (\Throwable $th) {
            Log::warning("⚠️ Error Nominatim {$city} ({$code}): " . $th->getMessage());
        }

        return null;
    }
}
