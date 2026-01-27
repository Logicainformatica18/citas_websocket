<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeNorthAmericaAndMexico extends Command
{
    protected $signature = 'joboffers:normalize-north-america {--dry-run}';
// php artisan joboffers:normalize-north-america
    protected $description = 'Corrige México a Latinoamérica y limpia Norteamérica (EEUU + Canadá)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🌎 Normalizando México y Norteamérica' . ($dryRun ? ' (DRY RUN)' : ''));

        DB::beginTransaction();

        try {

            /* =====================================================
               1️⃣ MÉXICO → LATINOAMÉRICA (EXACTAMENTE LO PEDIDO)
            ===================================================== */
            $this->executeUpdate("
                UPDATE job_offers
                SET region = 'Latinoamérica',
                    country = 'México'
                WHERE UPPER(TRIM(country)) IN (
                    'MEXICO',
                    'MÉXICO'
                )
            ", $dryRun);

            /* =====================================================
               2️⃣ NORMALIZAR REGIÓN NORTEAMÉRICA
            ===================================================== */
            $this->executeUpdate("
                UPDATE job_offers
                SET region = 'Norteamérica'
                WHERE UPPER(TRIM(REPLACE(region,'_',' '))) IN (
                    'NORTH AMERICA',
                    'NORTHAMERICA',
                    'NORTEAMERICA',
                    'NORTE AMÉRICA',
                    'NORTEAMÉRICA'
                )
            ", $dryRun);

            /* =====================================================
               3️⃣ ESTADOS UNIDOS
            ===================================================== */
            $this->executeUpdate("
                UPDATE job_offers
                SET country = 'Estados Unidos'
                WHERE region = 'Norteamérica'
                  AND UPPER(TRIM(country)) IN (
                    'UNITED STATES',
                    'UNITED STATES OF AMERICA',
                    'USA',
                    'US',
                    'U.S.',
                    'U.S.A'
                  )
            ", $dryRun);

            /* =====================================================
               4️⃣ CANADÁ
            ===================================================== */
            $this->executeUpdate("
                UPDATE job_offers
                SET country = 'Canadá'
                WHERE region = 'Norteamérica'
                  AND UPPER(TRIM(country)) IN (
                    'CANADA',
                    'CANADÁ'
                  )
            ", $dryRun);

            /* =====================================================
               5️⃣ LIMPIEZA FINAL NORTEAMÉRICA
            ===================================================== */
            $this->executeUpdate("
                UPDATE job_offers
                SET country = TRIM(country),
                    region  = TRIM(region)
                WHERE region IN ('Norteamérica','Latinoamérica')
            ", $dryRun);

            if ($dryRun) {
                DB::rollBack();
                $this->warn('🟡 DRY RUN: no se aplicaron cambios');
            } else {
                DB::commit();
                $this->info('✅ Normalización completada correctamente');
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
