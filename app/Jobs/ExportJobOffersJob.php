<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\JobOfferExportService;

/** Export a Excel de /job-offers con los filtros del listado (por tramos; el último empaqueta el .xlsx). */
class ExportJobOffersJob extends JobOfferProcessJob
{
    protected function step(JobOfferProcess $process, float $deadline): bool
    {
        return app(JobOfferExportService::class)->step($process, $deadline);
    }
}
