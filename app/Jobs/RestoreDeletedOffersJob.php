<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\TitleExclusionService;

/** Revierte una eliminación desde job_offer_deletion_backups. */
class RestoreDeletedOffersJob extends JobOfferProcessJob
{
    protected function step(JobOfferProcess $process, float $deadline): bool
    {
        return app(TitleExclusionService::class)->restoreStep($process, $deadline);
    }
}
