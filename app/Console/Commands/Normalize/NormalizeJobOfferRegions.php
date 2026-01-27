<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeJobOfferRegions extends Command
{
    protected $signature = 'joboffers:normalize-regions 
                            {--force : Recalcular todas las regiones}';

    protected $description = 'Normaliza el campo region directamente en job_offers';

    public function handle(): int
    {
        $this->info('🔄 Normalizando regiones de job_offers (MISMA COLUMNA region)');

        DB::beginTransaction();

        try {
            $query = DB::table('job_offers');

            if (! $this->option('force')) {
                // solo regiones sucias / no canónicas
                $query->where(function ($q) {
                    $q->whereNull('region')
                      ->orWhere('region', '')
                      ->orWhereIn(DB::raw('UPPER(region)'), [
                          'AFRICA','ASIA','APAC','ASIA REGION',
                          'EUROPE','EUROPA',
                          'LATAM','LATINOAMERICA','LATINOAMÉRICA',
                          'NORTH_AMERICA','NORTEAMERICA','NORTE AMÉRICA',
                          'OCEANIA','GLOBAL','WORLDWIDE',
                          'REMOTE','REMOTO'
                      ]);
                });

                $this->info('👉 Solo regiones no canónicas');
            } else {
                $this->warn('⚠️ Recalculando TODAS las regiones');
            }

            $affected = $query->update([
                'region' => DB::raw("
                    CASE
                        WHEN region IS NULL OR TRIM(region) = '' THEN 'Desconocido'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) = 'AFRICA'
                            THEN 'África'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) IN (
                            'ASIA',
                            'ASIA REGION',
                            'APAC'
                        ) THEN 'Asia'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) IN (
                            'EUROPE',
                            'EUROPA'
                        ) THEN 'Europa'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) IN (
                            'LATAM',
                            'LATIN AMERICA',
                            'LATINOAMERICA',
                            'LATINOAMÉRICA'
                        ) THEN 'Latinoamérica'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) IN (
                            'NORTH AMERICA',
                            'NORTEAMERICA',
                            'NORTE AMÉRICA',
                            'NORTEAMÉRICA'
                        ) THEN 'Norteamérica'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) = 'OCEANIA'
                            THEN 'Oceanía'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) IN (
                            'GLOBAL',
                            'WORLDWIDE'
                        ) THEN 'Global'

                        WHEN UPPER(TRIM(REPLACE(region,'_',' '))) IN (
                            'REMOTE',
                            'REMOTO'
                        ) THEN 'Remoto'

                        ELSE 'Desconocido'
                    END
                ")
            ]);

            DB::commit();

            $this->info("✅ Regiones normalizadas: {$affected}");

            Log::info('Job offers region normalization completed', [
                'affected' => $affected,
                'forced'   => $this->option('force'),
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
