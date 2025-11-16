<?php

namespace App\Helpers;

class RegionHelper
{
    protected static array $regionMap = [
        // 🌎 LATINOAMÉRICA
       'LATAM' => [
        'México','Mexico','Colombia','Brasil','Brazil','Argentina','Perú','Peru','Chile','Venezuela',
        'Paraguay','Bolivia','Uruguay','Ecuador','Nicaragua','Panamá','Panama','Guatemala',
        'El Salvador','Cuba','Costa Rica','Honduras','República Dominicana','Dominican Republic',
        'Puerto Rico','Latam'
    ],

       'NORTH_AMERICA' => [
        'Estados Unidos','United States','USA','Usa','US','Canadá','Canada','Turks and Caicos Islands'
    ],


         // 🌍 EUROPA
    'EUROPE' => [
        'España','Spain','Alemania','Germany','Francia','France','Reino Unido','United Kingdom','Uk',
        'Italia','Italy','Países Bajos','Netherlands','Nederland','Polonia','Poland','Suiza','Switzerland',
        'Bélgica','Belgium','Portugal','Austria','Suecia','Sweden','Noruega','Norway','Irlanda','Ireland',
        'Finlandia','Finland','Grecia','Greece','Dinamarca','Denmark','Hungría','Hungary','Luxemburgo','Luxembourg',
        'Chequia','Czech Republic','Croacia','Croatia','Rumania','Romania','Ucrania','Ukraine','Bulgaria','Serbia',
        'Europa','Europe','Emea'
    ],

    // 🌊 OCEANÍA
    'OCEANIA' => [
        'Australia','Nueva Zelanda','New Zealand','Australia,  New Zealand'
    ],

    // 🌍 ÁFRICA
    'AFRICA' => [
        'Sudáfrica','South Africa','Egipto','Egypt','Marruecos','Morocco','Nigeria','Kenia','Kenya','Sudan'
    ],

    // 🌐 REMOTO / OTROS
    'REMOTE' => [
        'Remote','Remoto','Desconocido','Anywhere','Global','Worldwide','Home Office'
    ],
    // 🌏 ASIA
'ASIA' => [
    'India','China','Japón','Japan','Singapore','Singapur','Filipinas','Philippines',
    'Corea del Sur','South Korea','Vietnam','Tailandia','Thailand','Indonesia','Malaysia','Malasia',
    'Emiratos Árabes Unidos','United Arab Emirates','UAE'
],

    ];

    /**
     * Devuelve la región asociada a un país (nombre o ISO2).
     */
    public static function fromCountry(?string $country): ?string
    {
        if (!$country) return null;

        $normalized = trim(ucwords(strtolower($country)));

        // 🔄 Si se pasa ISO2 (ej. "US", "DE", "IN"), conviértelo
        $isoMap = [
            'US' => 'Estados Unidos',
            'CA' => 'Canadá',
            'MX' => 'México',
            'BR' => 'Brasil',
            'AR' => 'Argentina',
            'PE' => 'Perú',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador',
            'BO' => 'Bolivia',
            'UY' => 'Uruguay',
            'ES' => 'España',
            'FR' => 'Francia',
            'DE' => 'Alemania',
            'IT' => 'Italia',
            'GB' => 'Reino Unido',
            'NL' => 'Países Bajos',
            'BE' => 'Bélgica',
            'CH' => 'Suiza',
            'PL' => 'Polonia',
            'IN' => 'India',
            'SG' => 'Singapur',
            'JP' => 'Japón',
            'AU' => 'Australia',
            'NZ' => 'Nueva Zelanda',
            'ZA' => 'Sudáfrica'
        ];

        $normalized = $isoMap[strtoupper($country)] ?? $normalized;

        foreach (self::$regionMap as $region => $countries) {
            if (in_array($normalized, $countries, true)) {
                return $region;
            }
        }

        return null;
    }
    public static function fallbackCapital(string $iso2): array
{
    $iso2 = strtoupper($iso2);

    $capitals = [
        'US' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364, 'country' => 'Estados Unidos'],
        'GB' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278, 'country' => 'Reino Unido'],
        'CA' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997, 'country' => 'Canadá'],
        'MX' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332, 'country' => 'México'],
        'BR' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828, 'country' => 'Brasil'],
        'ES' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038, 'country' => 'España'],
        'FR' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522, 'country' => 'Francia'],
        'DE' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050, 'country' => 'Alemania'],
        'IT' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964, 'country' => 'Italia'],
        'IN' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090, 'country' => 'India'],
        'SG' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198, 'country' => 'Singapur'],
        'AU' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093, 'country' => 'Australia'],
        'NZ' => ['city' => 'Wellington', 'lat' => -41.2865, 'lng' => 174.7762, 'country' => 'Nueva Zelanda'],

        'UNK' => ['city' => 'Unknown', 'lat' => 0, 'lng' => 0, 'country' => 'Desconocido'],
    ];

    return $capitals[$iso2] ?? $capitals['UNK'];
}

}
