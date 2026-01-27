<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeEuropeCountries extends Command
{
    protected $signature = 'normalize:europe-countries {--dry-run}';

    protected $description = 'Normaliza países de EUROPA en job_offers (actúa sobre la misma columna country)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🌍 Normalizando países de EUROPA' . ($dryRun ? ' (DRY RUN)' : ''));

        DB::beginTransaction();

        try {

            /* =====================================================
               1️⃣ Basura → NULL (no son países)
            ===================================================== */
            $this->executeUpdate("
                UPDATE job_offers
                SET country = 'Remote'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN (
                    'EUROPA',
                    'EUROPE',
                    'EMEA'
                  )
            ", $dryRun);

            /* =====================================================
               2️⃣ Normalización de países (ESPAÑOL)
            ===================================================== */

            // Alemania
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Alemania'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('GERMANY','ALEMANIA','DEUTSCHLAND')
            ", $dryRun);
            $this->executeUpdate("
               UPDATE job_offers
SET country = 'Alemania'
WHERE region = 'Europa'
  AND LOWER(TRIM(country)) = 'de';

            ", $dryRun);

            // España
            $this->executeUpdate("
                UPDATE job_offers SET country = 'España'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('SPAIN','ESPANA','ESPAÑA')
            ", $dryRun);

            // Francia
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Francia'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('FRANCE','FRANCIA')
            ", $dryRun);

            // Italia
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Italia'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('ITALY','ITALIA')
            ", $dryRun);

            // Reino Unido
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Reino Unido'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN (
                    'UK',
                    'UNITED KINGDOM',
                    'REINO UNIDO',
                    'GREAT BRITAIN'
                  )
            ", $dryRun);

            // Países Bajos
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Países Bajos'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN (
                    'NETHERLANDS',
                    'NEDERLAND',
                    'PAISES BAJOS',
                    'PAÍSES BAJOS'
                  )
            ", $dryRun);

            // Polonia
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Polonia'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('POLAND','POLSKA','POLONIA')
            ", $dryRun);

            // Suiza
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Suiza'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('SWITZERLAND','SCHWEIZ','SUISSE')
            ", $dryRun);

            // Bélgica
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Bélgica'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('BELGIUM','BELGICA','BÉLGICA')
            ", $dryRun);

            // Portugal
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Portugal'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) = 'PORTUGAL'
            ", $dryRun);

            // Rumanía
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Rumanía'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('ROMANIA','RUMANIA')
            ", $dryRun);

            // Bulgaria
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Bulgaria'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) = 'BULGARIA'
            ", $dryRun);

            // Hungría
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Hungría'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('HUNGARY','HUNGRIA')
            ", $dryRun);

            // Serbia
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Serbia'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) = 'SERBIA'
            ", $dryRun);

            // Suecia
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Suecia'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('SWEDEN','SUECIA')
            ", $dryRun);

            // Ucrania
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Ucrania'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) = 'UCRANIA'
            ", $dryRun);

            // Dinamarca
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Dinamarca'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('DENMARK','DINAMARCA')
            ", $dryRun);

            // Grecia
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Grecia'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('GREECE','GRECIA')
            ", $dryRun);

            // Irlanda
            $this->executeUpdate("
                UPDATE job_offers SET country = 'Irlanda'
                WHERE region = 'Europa'
                  AND UPPER(TRIM(country)) IN ('IRELAND','IRLANDA')
            ", $dryRun);

            /* =====================================================
               3️⃣ Limpieza final
            ===================================================== */
            $this->executeUpdate("
                UPDATE job_offers
                SET country = TRIM(country)
                WHERE region = 'Europa'
            ", $dryRun);

            if ($dryRun) {
                DB::rollBack();
                $this->warn('🟡 DRY RUN: no se aplicaron cambios');
            } else {
                DB::commit();
                $this->info('✅ Normalización de EUROPA completada');
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function executeUpdate(string $sql, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line('SQL → ' . trim($sql));
            return;
        }

        $affected = DB::update($sql);
        $this->line("✔ {$affected} filas afectadas");
    }
}
