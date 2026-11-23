<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\TitleExclusionService;

/** Marca las ofertas excluidas por título (job_offers.title_exclusion_id) y encola el recálculo de los rankings. */
class ApplyTitleExclusionsJob extends JobOfferProcessJob
{
    protected function step(JobOfferProcess $process, float $deadline): bool
    {
        return app(TitleExclusionService::class)->applyStep($process, $deadline);
    }
}
