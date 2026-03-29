<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeCountries extends Command
{
    protected $signature = 'countries:normalize';
    protected $description = 'Normaliza los países en job_offers';

    public function handle()
    {
        DB::statement("
            UPDATE job_offers
            SET country = CASE

                -- 🇵🇪 PERÚ
                WHEN UPPER(country) IN ('PERU', 'PERÚ') THEN 'Perú'

                -- 🇺🇸 ESTADOS UNIDOS
                WHEN UPPER(country) IN ('UNITED STATES', 'USA', 'US', 'US,') THEN 'Estados Unidos'

                -- ❌ BASURA
                WHEN UPPER(country) IN (
                    'REMOTE',
                    'REMOTO',
                    'LATAM',
                    'LATINOAMERICA',
                    'LATINOAMÉRICA',
                    'MUNDIAL',
                    'FLEXIBLE',
                    'ELIGIBLE-US',
                    'NORTH AMERICA',
                    'NORTHERN AMERICA'
                ) THEN NULL

                -- ❌ MULTIPAÍS
                WHEN country LIKE '%,%' THEN NULL

                -- ❌ COSAS RARAS
                WHEN LENGTH(country) <= 3 THEN NULL

                ELSE country

            END
        ");

        $this->info('✅ Países normalizados correctamente');
    }
}
