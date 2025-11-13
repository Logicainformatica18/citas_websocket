<?php

namespace App\Helpers;

class JoobleLocation
{
    public static function normalize(string $country): string
    {
        $country = strtolower(trim($country));

        // --------------------------------------
        // 1. ESTADOS DE EE.UU. → United States
        // --------------------------------------
        $usStates = [
            'al','ak','az','ar','ca','co','ct','de','fl','ga',
            'hi','id','il','in','ia','ks','ky','la','me','md',
            'ma','mi','mn','ms','mo','mt','ne','nv','nh','nj',
            'nm','ny','nc','nd','oh','ok','or','pa','ri','sc',
            'sd','tn','tx','ut','vt','va','wa','wv','wi','wy',
            // uppercase detection
            'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
            'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
            'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
            'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
            'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY',
        ];

        if (in_array($country, $usStates)) {
            return 'United States';
        }

        // --------------------------------------
        // 2. MAPEOS DE PAÍSES PERMITIDOS
        // --------------------------------------
        return match ($country) {

            // América
            'united states', 'usa', 'us', 'eeuu', 'estados unidos' => 'United States',
            'mexico', 'mx', 'méxico' => 'Mexico',
            'brazil', 'br', 'brasil' => 'Brazil',
            'canada', 'ca' => 'Canada',

            // Europa
            'united kingdom', 'uk', 'gb', 'inglaterra' => 'United Kingdom',
            'germany', 'de', 'alemania' => 'Germany',
            'spain', 'es', 'españa' => 'Spain',
            'italy', 'it', 'italia' => 'Italy',

            // Asia
            'india', 'in' => 'India',

            // Por defecto → capitaliza
            default => ucfirst($country),
        };
    }
}
