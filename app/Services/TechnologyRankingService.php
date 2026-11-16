<?php

namespace App\Services;

use App\Services\Ranking\EntityRankingService;

/**
 * Ranking de Tecnologías (/dashboard/ranking/technologies).
 * Toda la lógica está en EntityRankingService; la configuración, en
 * config/entity_rankings.php ('technologies').
 *
 * Respecto del cálculo en vivo anterior corrige: el máximo de tendencias se toma solo
 * de tecnologías (antes podía venir de un lenguaje, p. ej. IDC con 163 reportes) y
 * reportes_analizados cuenta solo reportes de tecnologías.
 */
class TechnologyRankingService extends EntityRankingService
{
    public const LOCK_NAME = 'ranking-technologies-refresh';
    public const STATUS_KEY = 'ranking-technologies-refresh:status';

    protected string $type = 'technologies';
}
