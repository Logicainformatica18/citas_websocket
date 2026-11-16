<?php

namespace App\Jobs;

/**
 * Se conserva por compatibilidad (por si quedara alguno en la cola);
 * los controladores ya despachan RefreshEntityRankingJob.
 */
class RefreshLanguageRankingJob extends RefreshEntityRankingJob
{
    public function __construct()
    {
        parent::__construct('languages');
    }
}
