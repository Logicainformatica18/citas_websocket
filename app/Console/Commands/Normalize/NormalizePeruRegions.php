<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePeruRegions extends Command
{
    protected $signature = 'normalize:peru-regions {--dry-run}';

    protected $description = 'Normaliza region_normalized para Perú usando latitude/longitude (26 regiones)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $sql = "
        UPDATE job_offers
        SET city = CASE

            WHEN latitude BETWEEN -12.25 AND -11.85
             AND longitude BETWEEN -77.30 AND -76.90
                THEN 'Callao'

            WHEN latitude BETWEEN -13.95 AND -11.50
             AND longitude BETWEEN -77.90 AND -75.40
                THEN 'Lima'

            WHEN latitude BETWEEN -10.90 AND -8.00
             AND longitude BETWEEN -78.90 AND -76.70
                THEN 'Áncash'

            WHEN latitude BETWEEN -17.90 AND -15.50
             AND longitude BETWEEN -73.90 AND -70.20
                THEN 'Arequipa'

            WHEN latitude BETWEEN -14.90 AND -13.30
             AND longitude BETWEEN -73.30 AND -71.20
                THEN 'Apurímac'

            WHEN latitude BETWEEN -15.30 AND -13.00
             AND longitude BETWEEN -75.00 AND -73.00
                THEN 'Ayacucho'

            WHEN latitude BETWEEN -7.90 AND -4.80
             AND longitude BETWEEN -79.90 AND -77.00
                THEN 'Cajamarca'

            WHEN latitude BETWEEN -15.90 AND -12.80
             AND longitude BETWEEN -72.90 AND -70.00
                THEN 'Cusco'

            WHEN latitude BETWEEN -14.50 AND -12.30
             AND longitude BETWEEN -75.60 AND -73.50
                THEN 'Huancavelica'

            WHEN latitude BETWEEN -10.80 AND -8.50
             AND longitude BETWEEN -77.50 AND -75.00
                THEN 'Huánuco'

            WHEN latitude BETWEEN -15.60 AND -13.00
             AND longitude BETWEEN -76.60 AND -74.00
                THEN 'Ica'

            WHEN latitude BETWEEN -12.90 AND -10.50
             AND longitude BETWEEN -76.60 AND -73.50
                THEN 'Junín'

            WHEN latitude BETWEEN -9.60 AND -6.80
             AND longitude BETWEEN -80.10 AND -77.70
                THEN 'La Libertad'

            WHEN latitude BETWEEN -7.40 AND -5.50
             AND longitude BETWEEN -80.10 AND -78.80
                THEN 'Lambayeque'

            WHEN latitude BETWEEN -6.50 AND  0.50
             AND longitude BETWEEN -77.80 AND -69.50
                THEN 'Loreto'

            WHEN latitude BETWEEN -13.80 AND -10.50
             AND longitude BETWEEN -72.50 AND -68.80
                THEN 'Madre de Dios'

            WHEN latitude BETWEEN -17.50 AND -15.80
             AND longitude BETWEEN -71.50 AND -69.50
                THEN 'Moquegua'

            WHEN latitude BETWEEN -11.20 AND -9.80
             AND longitude BETWEEN -76.80 AND -75.00
                THEN 'Pasco'

            WHEN latitude BETWEEN -6.90 AND -4.00
             AND longitude BETWEEN -81.60 AND -79.00
                THEN 'Piura'

            WHEN latitude BETWEEN -17.60 AND -14.50
             AND longitude BETWEEN -71.60 AND -68.80
                THEN 'Puno'

            WHEN latitude BETWEEN -8.70 AND -5.50
             AND longitude BETWEEN -78.00 AND -75.00
                THEN 'San Martín'

            WHEN latitude BETWEEN -18.50 AND -16.90
             AND longitude BETWEEN -71.20 AND -69.50
                THEN 'Tacna'

            WHEN latitude BETWEEN -4.20 AND -3.40
             AND longitude BETWEEN -80.70 AND -80.00
                THEN 'Tumbes'

            WHEN latitude BETWEEN -10.20 AND -7.00
             AND longitude BETWEEN -75.80 AND -72.00
                THEN 'Ucayali'

            WHEN latitude BETWEEN -6.50 AND -3.00
             AND longitude BETWEEN -78.50 AND -76.00
                THEN 'Amazonas'

            ELSE region_normalized
        END
        WHERE LOWER(TRIM(country)) IN ('peru','perú','pe')
          AND latitude IS NOT NULL
          AND longitude IS NOT NULL
        ";

        if ($dryRun) {
            $this->warn('🟡 DRY RUN activado – no se ejecutó el UPDATE');
            $this->line($sql);
            return Command::SUCCESS;
        }

        $affected = DB::update($sql);

        $this->info("✅ Normalización completada. Filas afectadas: {$affected}");

        return Command::SUCCESS;
    }
}
