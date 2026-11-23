<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\TitleExclusionService;

/**
 * "Sugerir con IA": un solo tramo (lectura de títulos ~3 s, llamada a la IA hasta 120 s, conteos de
 * las sugerencias ~5 s). Las sugerencias quedan en el resultado del proceso; no se agrega nada a la
 * lista hasta que el usuario las aprueba.
 */
class SuggestTitleExclusionsJob extends JobOfferProcessJob
{
    protected function step(JobOfferProcess $process, float $deadline): bool
    {
        app(TitleExclusionService::class)->suggest($process);

        return true;
    }
}
