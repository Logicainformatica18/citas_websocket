<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeOceaniaCountries extends Command
{
//php artisan joboffers:normalize-oceania --dry-run

    protected $signature = 'joboffers:normalize-oceania {--dry-run}';

    protected $description = 'Normaliza países de Oceanía en job_offers';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🌊 Normalizando países de Oceanía' . ($dryRun ? ' (DRY RUN)' : ''));

        DB::beginTransaction();

        try {
            // 1️⃣ Normalizar región (OCEANIA → Oceanía)
            $this->executeUpdate(
                "
                UPDATE job_offers
                SET region = 'Oceanía'
                WHERE UPPER(TRIM(REPLACE(region,'_',' '))) IN ('OCEANIA','OCEANÍA')
                ",
                $dryRun
            );

            // 2️⃣ Australia
            $this->executeUpdate(
                "
                UPDATE job_offers
                SET country = 'Australia'
                WHERE region = 'Oceanía'
                  AND UPPER(TRIM(country)) IN ('AUSTRALIA')
                ",
                $dryRun
            );

            // 3️⃣ Nueva Zelanda
            $this->executeUpdate(
                "
                UPDATE job_offers
                SET country = 'Nueva Zelanda'
                WHERE region = 'Oceanía'
                  AND UPPER(TRIM(country)) IN (
                      'NEW ZEALAND',
                      'NUEVA ZELANDA'
                  )
                ",
                $dryRun
            );

            // 4️⃣ Limpieza final
            $this->executeUpdate(
                "
                UPDATE job_offers
                SET country = TRIM(country),
                    region  = TRIM(region)
                WHERE region = 'Oceanía'
                ",
                $dryRun
            );

            if ($dryRun) {
                DB::rollBack();
                $this->warn('🟡 DRY RUN: no se aplicaron cambios');
            } else {
                DB::commit();
                $this->info('✅ Oceanía normalizada correctamente');
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Ejecuta un UPDATE con soporte DRY-RUN
     */
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
