<?php

namespace App\Console\Commands;

use App\Console\Commands\Ranking\RefreshEntityRankingCommand;

class RefreshLanguageRankingCommand extends RefreshEntityRankingCommand
{
    protected $signature = 'ranking:refresh-languages
        {--year= : Solo este año (por defecto, desde 2024 hasta el año actual)}
        {--period= : Solo este semestre: s1 o s2}';

    protected $description = 'Recalcula la tabla de resumen del Ranking de Lenguajes y precalienta la caché de JobMarketStatus.';

    protected string $rankingType = 'languages';
}
