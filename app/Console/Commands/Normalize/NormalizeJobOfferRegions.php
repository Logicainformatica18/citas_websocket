<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeJobOfferRegions extends Command
{
    protected $signature = 'joboffers:normalize-regions 
                            {--force : Recalcular todas las regiones}';

    protected $description = 'Normaliza el campo region_normalized en job_offers';

    public function handle(): int
    {
        $this->info('🔄 Normalizando regiones de job_offers...');

        $query = DB::table('job_offers');

        if (! $this->option('force')) {
            $query->whereNull('region_normalized');
            $this->info('👉 Solo registros sin normalizar');
        } else {
            $this->warn('⚠️ Recalculando TODAS las regiones');
        }

        $affected = $query->update([
            'region_normalized' => DB::raw("
                CASE
                    WHEN region IS NULL OR TRIM(region) = '' THEN 'Desconocido'

                    WHEN UPPER(region) IN ('AFRICA') THEN 'África'
                    WHEN UPPER(region) IN ('ASIA') THEN 'Asia'

                    WHEN UPPER(region) IN ('EUROPE', 'EUROPA') THEN 'Europa'

                    WHEN UPPER(region) IN (
                        'LATAM',
                        'LATINOAMERICA',
                        'LATINOAMÉRICA',
                        'LATIN AMERICA'
                    ) THEN 'Latinoamérica'

                    WHEN UPPER(region) IN (
                        'NORTH_AMERICA',
                        'NORTH AMERICA',
                        'NORTEAMERICA',
                        'NORTE AMÉRICA',
                        'NORTEAMÉRICA'
                    ) THEN 'Norteamérica'

                    WHEN UPPER(region) IN ('OCEANIA') THEN 'Oceanía'
                    WHEN UPPER(region) IN ('GLOBAL','WORLDWIDE') THEN 'Global'
                    WHEN UPPER(region) IN ('REMOTE','REMOTO') THEN 'Remoto'

                    ELSE 'Desconocido'
                END
            ")
        ]);

        $this->info("✅ Registros actualizados: {$affected}");
        Log::info('Job offers region normalization completed', [
            'affected' => $affected,
            'forced'   => $this->option('force'),
        ]);

        return Command::SUCCESS;
    }
}
