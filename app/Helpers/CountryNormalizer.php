<?php

namespace App\Helpers;

class CountryNormalizer
{
    /**
     * 🗺️ Normaliza cualquier nombre o código de país a su forma estándar en español.
     *
     * @param  string|null  $country
     * @return string
     */
    public static function normalize(?string $country): string
    {
        if (!$country) {
            return 'Desconocido';
        }

        $country = strtolower(trim($country));

        // 🧭 Mapeo estándar (códigos ISO, nombres en inglés y español)
        $map = [
            // América
            'us' => 'Estados Unidos', 'usa' => 'Estados Unidos', 'united states' => 'Estados Unidos', 'eeuu' => 'Estados Unidos',
            'mx' => 'México', 'me' => 'México', 'mexico' => 'México',
            'ca' => 'Canadá', 'canada' => 'Canadá',
            'br' => 'Brasil', 'brazil' => 'Brasil',
            'ar' => 'Argentina', 'argentina' => 'Argentina',
            'cl' => 'Chile', 'chile' => 'Chile',
            'pe' => 'Perú', 'peru' => 'Perú',
            'co' => 'Colombia', 'colombia' => 'Colombia',
            'uy' => 'Uruguay', 'uruguay' => 'Uruguay',
            've' => 'Venezuela', 'venezuela' => 'Venezuela',
            'bo' => 'Bolivia', 'bolivia' => 'Bolivia',
            'ec' => 'Ecuador', 'ecuador' => 'Ecuador',
            'py' => 'Paraguay', 'paraguay' => 'Paraguay',
            'do' => 'República Dominicana', 'dominican republic' => 'República Dominicana',
            'pr' => 'Puerto Rico', 'puerto rico' => 'Puerto Rico',

            // Europa
            'es' => 'España', 'sp' => 'España', 'spain' => 'España',
            'gb' => 'Reino Unido', 'uk' => 'Reino Unido', 'united kingdom' => 'Reino Unido',
            'fr' => 'Francia', 'france' => 'Francia',
            'de' => 'Alemania', 'germany' => 'Alemania',
            'it' => 'Italia', 'italy' => 'Italia',
            'nl' => 'Países Bajos', 'netherlands' => 'Países Bajos',
            'be' => 'Bélgica', 'belgium' => 'Bélgica',
            'ch' => 'Suiza', 'switzerland' => 'Suiza',
            'pt' => 'Portugal', 'portugal' => 'Portugal',
            'pl' => 'Polonia', 'poland' => 'Polonia',
            'se' => 'Suecia', 'sweden' => 'Suecia',

            // Asia
            'in' => 'India', 'india' => 'India',
            'cn' => 'China', 'china' => 'China',
            'jp' => 'Japón', 'japan' => 'Japón',
            'kr' => 'Corea del Sur', 'south korea' => 'Corea del Sur',
            'sg' => 'Singapur', 'singapore' => 'Singapur',
            'hk' => 'Hong Kong', 'hong kong' => 'Hong Kong',
            'ph' => 'Filipinas', 'philippines' => 'Filipinas',

            // Oceanía
            'au' => 'Australia', 'australia' => 'Australia',
            'nz' => 'Nueva Zelanda', 'new zealand' => 'Nueva Zelanda',

            // África
            'za' => 'Sudáfrica', 'south africa' => 'Sudáfrica',
            'ng' => 'Nigeria', 'nigeria' => 'Nigeria',
            'eg' => 'Egipto', 'egypt' => 'Egipto',
            'ma' => 'Marruecos', 'morocco' => 'Marruecos',

            // Regiones genéricas
            'eu' => 'Europa', 'europe' => 'Europa',
            'ww' => 'Mundial', 'worldwide' => 'Mundial', 'global' => 'Mundial',
            'remote' => 'Remoto', 'anywhere' => 'Remoto',
        ];

        return $map[$country] ?? ucfirst($country);
    }
}
