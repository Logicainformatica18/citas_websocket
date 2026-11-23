<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\TitleExclusionService;

/** "Analizar basura": simula la lista actual (categorías, patrones, fuentes, asociaciones e impacto en los rankings). */
class AnalyzeTitleExclusionsJob extends JobOfferProcessJob
{
    protected function step(JobOfferProcess $process, float $deadline): bool
    {
        return app(TitleExclusionService::class)->analyzeStep($process, $deadline);
    }
}
