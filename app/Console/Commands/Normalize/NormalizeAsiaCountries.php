<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeAsiaCountries extends Command
{
    protected $signature = 'normalize:asia-countries {--dry-run}';

    protected $description = 'Normaliza países y región ASIA en job_offers';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🌏 Normalizando países de ASIA' . ($dryRun ? ' (DRY RUN)' : ''));

        DB::beginTransaction();

        try {
            // 1️⃣ Normalizar región
            $this->update(
                "UPDATE job_offers SET region = 'Remote'
                 WHERE UPPER(region) IN ('ASIA','APAC','ASIA REGION')",
                $dryRun
            );

            // 2️⃣ Limpiar países basura
            $this->update(
                "UPDATE job_offers SET country = NULL
                 WHERE region = 'ASIA' AND country IN ('Apac','Asia')",
                $dryRun
            );

            // 3️⃣ Japón
            $this->update(
                "UPDATE job_offers SET country = 'Japan'
                 WHERE region = 'ASIA'
                   AND country IN ('Japón','Japon','Japan')",
                $dryRun
            );

            // 4️⃣ Filipinas
            $this->update(
                "UPDATE job_offers SET country = 'Philippines'
                 WHERE region = 'ASIA'
                   AND country IN ('Filipinas','Philippines')",
                $dryRun
            );

            // 5️⃣ Singapur
            $this->update(
                "UPDATE job_offers SET country = 'Singapore'
                 WHERE region = 'ASIA'
                   AND country IN ('Singapur','Singapore')",
                $dryRun
            );

            // 6️⃣ Hong Kong
            $this->update(
                "UPDATE job_offers SET country = 'Hong Kong'
                 WHERE region = 'ASIA'
                   AND country IN ('Hong kong','Hong Kong')",
                $dryRun
            );

            // 7️⃣ Corea del Sur
            $this->update(
                "UPDATE job_offers SET country = 'South Korea'
                 WHERE region = 'ASIA'
                   AND country IN ('South Korea','Korea')",
                $dryRun
            );

            // 8️⃣ Limpieza final
            $this->update(
                "UPDATE job_offers
                 SET country = TRIM(country),
                     region  = TRIM(region)
                 WHERE region = 'ASIA'",
                $dryRun
            );

            if ($dryRun) {
                DB::rollBack();
                $this->warn('🟡 DRY RUN: no se aplicaron cambios');
            } else {
                DB::commit();
                $this->info('✅ Normalización ASIA completada');
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function update(string $sql, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line('SQL → ' . $sql);
            return;
        }

        $affected = DB::update($sql);
        $this->line("✔ {$affected} filas afectadas");
    }
}
