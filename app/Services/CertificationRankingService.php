<?php

namespace App\Services;

use App\Services\Ranking\EntityRankingService;

/**
 * Ranking de Certificaciones (/dashboard/ranking-certificaciones).
 * Toda la lógica está en EntityRankingService; la configuración, en
 * config/entity_rankings.php ('certifications').
 *
 * Respecto del cálculo en vivo anterior: la tendencia usa la misma fórmula que Lenguajes y
 * Tecnologías (LOG(reportes+1)/LOG(max+1)*100; antes era menciones/total de reportes) y los
 * semestres son semiabiertos (antes se perdían el 30-jun y el 31-dic).
 */
class CertificationRankingService extends EntityRankingService
{
    public const LOCK_NAME = 'ranking-certifications-refresh';
    public const STATUS_KEY = 'ranking-certifications-refresh:status';

    protected string $type = 'certifications';
}
