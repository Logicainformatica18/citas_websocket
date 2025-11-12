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
    ]
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
}
