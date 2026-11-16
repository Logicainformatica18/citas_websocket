<?php

namespace App\Console\Commands;

use App\Console\Commands\Ranking\RefreshEntityRankingCommand;

class RefreshCertificationRankingCommand extends RefreshEntityRankingCommand
{
    protected $signature = 'ranking:refresh-certifications
        {--year= : Solo este año (por defecto, desde 2024 hasta el año actual)}
        {--period= : Solo este semestre: s1 o s2}';

    protected $description = 'Recalcula las tablas de resumen del Ranking de Certificaciones (ranking por semestre y evolución).';

    protected string $rankingType = 'certifications';
}
