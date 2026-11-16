<?php

namespace App\Console\Commands;

use App\Console\Commands\Ranking\RefreshEntityRankingCommand;

class RefreshTechnologyRankingCommand extends RefreshEntityRankingCommand
{
    protected $signature = 'ranking:refresh-technologies
        {--year= : Solo este año (por defecto, desde 2024 hasta el año actual)}
        {--period= : Solo este semestre: s1 o s2}';

    protected $description = 'Recalcula las tablas de resumen del Ranking de Tecnologías (ranking por semestre y evolución) y precalienta la caché de JobMarketStatus.';

    protected string $rankingType = 'technologies';
}
