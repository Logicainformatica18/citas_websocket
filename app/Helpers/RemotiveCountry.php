<?php

namespace App\Helpers;

class RemotiveCountry
{
    /**
     * Normaliza ubicaciones de Remotive (que incluye regiones, países y alias raros)
     */
    public static function normalize(?string $value): string
    {
        if (!$value) return 'Desconocido';

        $value = strtolower(trim($value));

        // 🔥 Mapeo completo para Remotive (países + zonas + regiones)
        $map = [

            // ------------------------
            // 🇺🇸 Estados Unidos
            // ------------------------
            'usa' => 'Estados Unidos',
            'u.s.a' => 'Estados Unidos',
            'u.s.' => 'Estados Unidos',
            'us' => 'Estados Unidos',
            'united states' => 'Estados Unidos',
            'united states of america' => 'Estados Unidos',
            'america' => 'Estados Unidos',

            // ------------------------
            // 🇨🇦 Canadá
            // ------------------------
            'ca' => 'Canadá',
            'canada' => 'Canadá',

            // ------------------------
            // 🇲🇽 México
            // ------------------------
            'mx' => 'México',
            'mexico' => 'México',

            // ------------------------
            // 🇮🇳 India (corregido)
            // ------------------------
            'india' => 'India',
            'in' => 'India',
            'india only' => 'India',
            'indian timezone' => 'India',

            // ------------------------
            // 🇪🇺 Europa
            // ------------------------
            'europe' => 'Europa',
            'eu' => 'Europa',
            'european union' => 'Europa',
            'european timezones' => 'Europa',
            'emea' => 'Europa',   // Remotive la usa

            // ------------------------
            // 🌎 Latinoamérica
            // ------------------------
            'latin america' => 'Latinoamérica',
            'latam' => 'Latinoamérica',
            'south america' => 'Latinoamérica',
            'central america' => 'Latinoamérica',

            // ------------------------
            // 🌍 África
            // ------------------------
            'africa' => 'África',

            // ------------------------
            // 🌏 Asia / Pacífico
            // ------------------------
            'apac' => 'Asia',
            'asia' => 'Asia',
            'asian timezones' => 'Asia',

            // ------------------------
            // 🌐 Global / Remoto
            // ------------------------
            'worldwide' => 'Mundial',
            'global' => 'Mundial',
            'anywhere' => 'Remoto',
            'multiple countries' => 'Mundial',
            'remote' => 'Remoto',
            'fully remote' => 'Remoto',
        ];

        // ✔ Coincidencia exacta
        if (isset($map[$value])) {
            return $map[$value];
        }

        // ✔ Listas: "USA, Canada" → se procesa cada parte
        if (str_contains($value, ',')) {
            $parts = array_map('trim', explode(',', $value));
            $normalized = array_unique(array_map([self::class, 'normalize'], $parts));

            // Si todas son países de América del Norte
            if (self::allIn($normalized, ['Estados Unidos', 'Canadá', 'México'])) {
                return 'América del Norte';
            }

            // Si mezcla diferentes continentes → Mundial
            if (count($normalized) > 1) {
                return 'Mundial';
            }

            return $normalized[0];
        }

        // ✔ Si no está mapeado, devolver capitalizado
        return ucfirst($value);
    }


    /**
     * Determina si todos los valores están dentro de un grupo específico.
     */
    private static function allIn(array $values, array $allowed): bool
    {
        foreach ($values as $v) {
            if (!in_array($v, $allowed)) return false;
        }
        return true;
    }
}
