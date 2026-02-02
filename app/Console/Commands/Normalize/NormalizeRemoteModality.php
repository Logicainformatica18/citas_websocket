<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeRemoteModality extends Command
{
    protected $signature = 'normalize:remote-modality
                            {--limit=5000 : Cantidad de registros por lote}
                            {--dry-run : Solo muestra conteos, no ejecuta updates}';

    protected $description = 'Normaliza modalidad laboral (remote / presencial / no_remote raspado)';

    public function handle()
    {
        $limit  = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        /*
        |--------------------------------------------------------------------------
        | 1. REMOTE inferido (no tocar NULL)
        |--------------------------------------------------------------------------
        */
        $remoteWhere = "
            modality IS NOT NULL
            AND LOWER(modality) NOT IN ('remote', 'remote_local', 'fully_remote')
            AND (
                LOWER(title)       REGEXP 'remote|work from home|wfh|home office|teletrabajo|remoto'
             OR LOWER(description) REGEXP 'remote|work from home|wfh|home office|teletrabajo|remoto'
             OR LOWER(benefits)    REGEXP 'remote|work from home|wfh|home office|teletrabajo|remoto'
             OR LOWER(location)   REGEXP 'remote|anywhere'
            )
        ";

        /*
        |--------------------------------------------------------------------------
        | 2. PRESENCIAL inferido DESDE no_remote
        |--------------------------------------------------------------------------
        */
        $presencialFromNoRemoteWhere = "
            modality = 'no_remote'
            AND (
                LOWER(title) REGEXP
                    'on[ -]?site|in[ -]?office|office[ -]?based|must be (located|based)|local candidates|relocation|required'
             OR LOWER(description) REGEXP
                    'on[ -]?site|in[ -]?office|office[ -]?based|must be (located|based)|local candidates|relocation|required'
             OR LOWER(description) REGEXP
                    'presencial|residir|ubicado en|vivir en|en oficina|traslado requerido'
            )
        ";

        if ($dryRun) {
            $remoteCount = DB::table('ws.job_offers')
                ->whereRaw($remoteWhere)
                ->count();

            $presencialCount = DB::table('ws.job_offers')
                ->whereRaw($presencialFromNoRemoteWhere)
                ->count();

            $this->info("🔎 DRY RUN");
            $this->info("• Remote inferido: {$remoteCount}");
            $this->info("• Presencial desde no_remote: {$presencialCount}");

            return Command::SUCCESS;
        }

        /*
        |--------------------------------------------------------------------------
        | EXEC 1: REMOTE
        |--------------------------------------------------------------------------
        */
        $remoteUpdated = DB::update("
            UPDATE ws.job_offers
            SET
                modality = 'remote',
                remote_type = 'inferred',
                updated_at = NOW()
            WHERE {$remoteWhere}
            LIMIT {$limit}
        ");

        $this->info("✅ Remote inferido: {$remoteUpdated}");

        /*
        |--------------------------------------------------------------------------
        | EXEC 2: PRESENCIAL desde no_remote
        |--------------------------------------------------------------------------
        */
        $presencialUpdated = DB::update("
            UPDATE ws.job_offers
            SET
                modality = 'presencial',
                remote_type = 'inferred',
                updated_at = NOW()
            WHERE {$presencialFromNoRemoteWhere}
            LIMIT {$limit}
        ");

        $this->info("✅ Presencial desde no_remote: {$presencialUpdated}");

        if ($remoteUpdated === 0 && $presencialUpdated === 0) {
            $this->warn("⚠️ No hay más registros para normalizar.");
        }

        return Command::SUCCESS;
    }
}
