<?php

namespace App\Helpers;

class RegionMapper
{
    /**
     * 🌎 Detecta la región geográfica basada en un país o región normalizada.
     * Funciona tanto para países reales como para regiones genéricas.
     *
     * @param string|null $country Normalizado (ej: "India", "Estados Unidos", "Latinoamérica", "Europa")
     * @return string Región normalizada (ej: "Asia", "Europa", "Latinoamérica", "Global")
     */
    public static function resolve(?string $country): string
    {
        if (!$country) {
            return 'Desconocido';
        }

        $country = strtolower(trim($country));

        // ---------------------
        // NORTEAMÉRICA
        // ---------------------
        $northAmerica = [
            'estados unidos', 'usa', 'us', 'united states',
            'canadá', 'canada',
            'méxico', 'mexico',
            'norteamérica', 'america del norte',
        ];

        if (in_array($country, $northAmerica)) {
            return 'Norteamérica';
        }

        // ---------------------
        // LATINOAMÉRICA
        // ---------------------
        $latinAmerica = [
            'perú', 'peru',
            'chile',
            'argentina',
            'colombia',
            'ecuador',
            'venezuela',
            'uruguay',
            'bolivia',
            'paraguay',
            'brasil', 'brazil',
            'méxico', 'mexico', // si lo quieres en LATAM, aquí está
            'república dominicana',
            'puerto rico',
            'latinoamérica', 'latin america', 'latam',
            'américa del sur', 'south america', 'sudamérica',
            'américa', 'americas', // Remotive
        ];

        if (in_array($country, $latinAmerica)) {
            return 'Latinoamérica';
        }

        // ---------------------
        // EUROPA
        // ---------------------
        $europe = [
            'españa', 'spain',
            'alemania', 'germany',
            'francia', 'france',
            'portugal',
            'italia', 'italy',
            'reino unido', 'united kingdom', 'uk',
            'países bajos', 'netherlands',
            'polonia', 'poland',
            'suecia', 'sweden',
            'suiza', 'switzerland',
            'bélgica', 'belgium',
            'europa', 'europe',
            'emea', // Remotive
            'european union',
            'european timezones',
        ];

        if (in_array($country, $europe)) {
            return 'Europa';
        }

        // ---------------------
        // ASIA
        // ---------------------
        $asia = [
            'india', 'in',
            'china', 'cn',
            'japón', 'japan', 'jp',
            'corea del sur', 'south korea', 'kr',
            'singapur', 'singapore', 'sg',
            'hong kong',
            'filipinas', 'philippines',
            'asia',
            'apac',
        ];

        if (in_array($country, $asia)) {
            return 'Asia';
        }

        // ---------------------
        // ÁFRICA
        // ---------------------
        $africa = [
            'sudáfrica', 'south africa', 'za',
            'nigeria',
            'egipto', 'egypt',
            'marruecos', 'morocco',
            'áfrica', 'africa',
        ];

        if (in_array($country, $africa)) {
            return 'África';
        }

        // ---------------------
        // OCEANÍA
        // ---------------------
        $oceania = [
            'australia', 'au',
            'nueva zelanda', 'new zealand', 'nz',
            'oceanía', 'oceania'
        ];

        if (in_array($country, $oceania)) {
            return 'Oceanía';
        }

        // ---------------------
        // GLOBAL / REMOTO
        // ---------------------
        $global = [
            'remoto', 'remote',
            'global',
            'mundial', 'worldwide',
            'anywhere',
        ];

        if (in_array($country, $global)) {
            return 'Global';
        }

        return 'Desconocido';
    }
}
