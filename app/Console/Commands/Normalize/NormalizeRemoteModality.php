<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeRemoteModality extends Command
{
    protected $signature = 'normalize:remote-modality
                            {--limit=5000 : Cantidad de registros por lote}
                            {--dry-run : Solo muestra conteos, no ejecuta updates}';

    protected $description = 'Normaliza modalidad laboral desde no_remote (remote / hybrid / no_precisa)';

    public function handle()
    {
        $limit  = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        /*
        |==========================================================
        | WHERE BASE: SOLO PARTIMOS DE no_remote
        |==========================================================
        */

        /*
        |----------------------------------------------------------
        | 1️⃣ no_remote → remote (evidencia explícita)
        |----------------------------------------------------------
        */
        $remoteWhere = "
            modality = 'no_remote'
            AND (
                LOWER(title)        REGEXP 'remote|work from home|wfh|home office|teletrabajo|remoto'
             OR LOWER(description)  REGEXP 'remote|work from home|wfh|home office|teletrabajo|remoto'
             OR LOWER(benefits)     REGEXP 'remote|work from home|wfh|home office|teletrabajo|remoto'
             OR LOWER(remote_type)  REGEXP 'remote|full'
            )
        ";

        /*
        |----------------------------------------------------------
        | 2️⃣ no_remote → hybrid (evidencia explícita)
        |----------------------------------------------------------
        */
        $hybridWhere = "
            modality = 'no_remote'
            AND (
                LOWER(title)        REGEXP 'hybrid|h[ií]brido'
             OR LOWER(description)  REGEXP 'hybrid|h[ií]brido'
             OR LOWER(benefits)     REGEXP 'hybrid|h[ií]brido'
             OR LOWER(remote_type)  REGEXP 'hybrid'
            )
        ";

        /*
        |----------------------------------------------------------
        | 3️⃣ no_remote → no_precisa
        | (NO hay evidencia presencial explícita)
        |----------------------------------------------------------
        */
        $noPrecisaWhere = "
            modality = 'no_remote'
            AND NOT (
                LOWER(title)        REGEXP 'on[- ]?site|onsite|presencial|in office|office based|local candidates|relocation|required'
             OR LOWER(description)  REGEXP 'on[- ]?site|onsite|presencial|in office|office based|local candidates|relocation|required'
             OR LOWER(description)  REGEXP 'residir|ubicado en|vivir en|en oficina|traslado requerido'
             OR LOWER(location)     REGEXP 'office|on[- ]?site|onsite'
            )
        ";

        /*
        |==========================================================
        | DRY RUN (conteos)
        |==========================================================
        */
        if ($dryRun) {
            $remoteCount = DB::table('job_offers')->whereRaw($remoteWhere)->count();
            $hybridCount = DB::table('job_offers')->whereRaw($hybridWhere)->count();
            $noPrecisaCount = DB::table('job_offers')->whereRaw($noPrecisaWhere)->count();

            $this->info("🔎 DRY RUN — normalize:remote-modality");
            $this->info("• no_remote → remote     : {$remoteCount}");
            $this->info("• no_remote → hybrid     : {$hybridCount}");
            $this->info("• no_remote → no_precisa : {$noPrecisaCount}");

            return Command::SUCCESS;
        }

        /*
        |==========================================================
        | EXEC 1: REMOTE
        |==========================================================
        */
        $remoteUpdated = DB::update("
            UPDATE job_offers
            SET
                modality = 'remote',
                remote_type = 'inferred',
                updated_at = NOW()
            WHERE {$remoteWhere}
            LIMIT {$limit}
        ");

        $this->info("✅ no_remote → remote: {$remoteUpdated}");

        /*
        |==========================================================
        | EXEC 2: HYBRID
        |==========================================================
        */
        $hybridUpdated = DB::update("
            UPDATE job_offers
            SET
                modality = 'hybrid',
                remote_type = 'inferred',
                updated_at = NOW()
            WHERE {$hybridWhere}
            LIMIT {$limit}
        ");

        $this->info("✅ no_remote → hybrid: {$hybridUpdated}");

        /*
        |==========================================================
        | EXEC 3: NO_PRECISA
        |==========================================================
        */
        $noPrecisaUpdated = DB::update("
            UPDATE job_offers
            SET
                modality = 'no_precisa',
                updated_at = NOW()
            WHERE {$noPrecisaWhere}
            LIMIT {$limit}
        ");

        $this->info("✅ no_remote → no_precisa: {$noPrecisaUpdated}");

        if (
            $remoteUpdated === 0 &&
            $hybridUpdated === 0 &&
            $noPrecisaUpdated === 0
        ) {
            $this->warn("⚠️ No hay más registros para normalizar.");
        }

        return Command::SUCCESS;
    }
}
