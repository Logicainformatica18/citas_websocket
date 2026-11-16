<?php

namespace App\Services;

use App\Services\Ranking\EntityRankingService;

/**
 * Ranking de Lenguajes (/dashboard/ranking/languages).
 * Toda la lógica está en EntityRankingService; la configuración, en
 * config/entity_rankings.php ('languages').
 */
class LanguageRankingService extends EntityRankingService
{
    // Se conservan por compatibilidad; los valores salen de la configuración.
    public const LOCK_NAME = 'ranking-languages-refresh';
    public const STATUS_KEY = 'ranking-languages-refresh:status';

    protected string $type = 'languages';
}
