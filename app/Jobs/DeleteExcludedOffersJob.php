<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\TitleExclusionService;

/** "Eliminar definitivamente" las ofertas de un análisis, con respaldo previo en job_offer_deletion_backups. */
class DeleteExcludedOffersJob extends JobOfferProcessJob
{
    protected function step(JobOfferProcess $process, float $deadline): bool
    {
        return app(TitleExclusionService::class)->deleteStep($process, $deadline);
    }
}
