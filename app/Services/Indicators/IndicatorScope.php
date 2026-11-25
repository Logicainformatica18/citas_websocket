<?php

namespace App\Services\Indicators;

use App\Models\JobOffer;
use App\Services\Ranking\ExclusionSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Criterio ÚNICO de "qué cuenta" en los indicadores de análisis del Observatorio (demanda,
 * distribución, tendencias y totales que ve el usuario). Las vistas operativas (módulo
 * job-offers, estado de scraping, importaciones, logs) NO lo usan: muestran todo lo recolectado.
 *
 *  a) Ofertas: se ignoran las de estado no_ti (job_offers.ti_status, estado ÚNICO: lo escriben la
 *     exclusión por keywords —origen keyword—, la clasificación con IA y la revisión manual). Las
 *     dudosas, pendientes y en proceso cuentan. Si la columna aún no existe (código desplegado antes de
 *     la migración 2026_11_25_000004), se usa title_exclusion_id: mismo resultado.
 *  d) Reportes de tendencia (solo rankings, sus modales y su evolución): se ignoran los de estado ruido
 *     (entity_trends.relevance_status) SOLO si está activo el filtro (ai_classification_settings.
 *     trends_filter_enabled, apagado hasta que la usuaria vea su efecto).
 *  b) Asociaciones oferta–entidad: se ignoran los pares de entity_association_exclusions
 *     vigentes (entities:exclude-associations), salvo apagado de emergencia
 *     (entities:exclusions --disable).
 *  c) Asociaciones oferta–entidad: solo cuentan las entidades activas (market_entities.is_active).
 *
 * Cada filtro existe en dos formas: SQL (para consultas crudas, con alias) y Query Builder.
 * Todo lo interpolado son nombres de tabla/alias del código o enteros leídos de la BD.
 * EntityRankingService (los tres rankings) usa exactamente estas piezas.
 */
final class IndicatorScope
{
    /** Tabla pivote → tipo de entidad en market_entities. */
    public const PIVOTS = [
        'language_job' => 'language',
        'technology_job' => 'technology',
        'certification_job' => 'certification',
        'methodology_job' => 'methodology',
    ];

    /** Clave de caché con la versión de los datos de los indicadores (ver bumpCacheVersion). */
    public const CACHE_VERSION_KEY = 'indicators:data_version';

    /**
     * Filtros apagados ('title', 'detector', 'inactive'). SOLO para medir el impacto de cada tipo
     * de exclusión (docs/produccion/herramientas/medir_indicadores.php); la aplicación nunca lo cambia.
     *
     * @var array<int, string>
     */
    public static array $off = [];

    /** @var array<string, string> ids activos por tipo, durante la petición/proceso */
    private static array $activeIds = [];

    /**
     * Versión de los datos de los indicadores. Forma parte de las claves de caché de los
     * indicadores cacheados (Datos Generales, Geo): al cambiar las exclusiones se incrementa
     * y esas cachés se invalidan de una vez.
     */
    public static function cacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * Invalida las cachés de los indicadores. Lo llaman job-offers:apply-title-exclusions (consola
     * y botón "Excluir de los indicadores"), entities:exclude-associations, entities:exclusions y
     * la eliminación/restauración de ofertas.
     */
    public static function bumpCacheVersion(): int
    {
        $v = self::cacheVersion() + 1;
        Cache::forever(self::CACHE_VERSION_KEY, $v);

        return $v;
    }

    /* ---------------- a) ofertas ---------------- */

    /** Condición "la oferta cuenta" sobre {alias}, o null si no hay columna o el filtro está apagado. */
    private static function offerCondition(string $alias): ?string
    {
        if (in_array('title', self::$off, true)) {
            return null;
        }
        if (JobOffer::hasTiStatusColumn()) {
            return "{$alias}.ti_status <> 'no_ti'";
        }

        return JobOffer::hasTitleExclusionColumn() ? "{$alias}.title_exclusion_id IS NULL" : null;
    }

    /** " AND {alias}.ti_status <> 'no_ti'" (vacío si la columna aún no existe). */
    public static function offersSql(string $alias = 'job_offers'): string
    {
        $c = self::offerCondition($alias);

        return $c === null ? '' : " AND {$c}";
    }

    /** Igual que offersSql() para el Query Builder. */
    public static function offers(Builder $query, string $alias = 'job_offers'): Builder
    {
        $c = self::offerCondition($alias);
        if ($c !== null) {
            $query->whereRaw($c);
        }

        return $query;
    }

    /* ---------------- d) reportes de tendencia (rankings) ---------------- */

    /** true si los rankings ignoran los reportes en estado ruido (interruptor en la base, apagado por defecto). */
    public static function trendsFilterActive(): bool
    {
        static $active = null;
        if ($active === null) {
            $active = ! in_array('trends', self::$off, true)
                && \Illuminate\Support\Facades\Schema::hasColumn('entity_trends', 'relevance_status')
                && \App\Services\AiClassification\AiSettings::trendsFilterEnabled();
        }

        return $active;
    }

    /** " AND {alias}.relevance_status <> 'ruido'" si el filtro de tendencias está activo; si no, ''. */
    public static function trendsSql(string $alias = 'entity_trends'): string
    {
        return self::trendsFilterActive() ? " AND {$alias}.relevance_status <> 'ruido'" : '';
    }

    /** Igual que trendsSql() para el Query Builder. */
    public static function trends(Builder $query, string $alias = 'entity_trends'): Builder
    {
        if (self::trendsFilterActive()) {
            $query->where("{$alias}.relevance_status", '<>', 'ruido');
        }

        return $query;
    }

    /**
     * Conteo de ofertas válidas en un GROUP BY que recorre toda la tabla (p. ej. por fuente):
     * "SUM({alias}.title_exclusion_id IS NULL)" en vez de COUNT(*) + WHERE. Con el WHERE, MySQL
     * elige idx_jo_title_excl (85 % de las filas son NULL) y tarda 4 veces más que recorrer el
     * índice del GROUP BY. Descartar luego los grupos con 0 (HAVING).
     */
    public static function countSql(string $alias = 'job_offers'): string
    {
        $c = self::offerCondition($alias);

        return $c === null ? 'COUNT(*)' : "SUM({$c})";
    }

    /* ---------------- b) + c) asociaciones ---------------- */

    /**
     * " AND {alias}.market_entity_id IN (activas) AND NOT EXISTS (excluida)" para una fila de la
     * tabla pivote con alias {alias}.
     */
    public static function associationsSql(string $pivot, string $alias): string
    {
        $active = in_array('inactive', self::$off, true)
            ? ''
            : " AND {$alias}.market_entity_id IN (" . self::activeIdsSql(self::entityType($pivot)) . ')';

        return $active . self::associationExclusionSql($pivot, $alias);
    }

    /** Igual que associationsSql() para el Query Builder. */
    public static function associations(Builder $query, string $pivot, string $alias): Builder
    {
        $sql = self::associationsSql($pivot, $alias);

        return $sql === '' ? $query : $query->whereRaw(substr($sql, 5));   // sin el " AND " inicial
    }

    /**
     * b) solo: " AND NOT EXISTS (… entity_association_exclusions …)" ('' si la exclusión está
     * apagada o la tabla no existe). Usa el índice idx_eae_filtro (pivot_table, job_offer_id,
     * market_entity_id, vigente).
     */
    public static function associationExclusionSql(string $pivot, string $alias): string
    {
        if (! ExclusionSettings::active() || in_array('detector', self::$off, true)) {
            return '';
        }
        $table = ExclusionSettings::TABLE;

        return " AND NOT EXISTS (SELECT 1 FROM `{$table}` x WHERE x.pivot_table = '{$pivot}'"
            . " AND x.job_offer_id = {$alias}.job_offer_id AND x.market_entity_id = {$alias}.market_entity_id AND x.vigente = 1)";
    }

    /**
     * c) solo: ids de las entidades activas del tipo ('0' si no hay ninguna, para que el IN siga
     * siendo válido). El IN filtra igual que un JOIN a market_entities y es 2-3 veces más rápido.
     * Una asociación sin market_entity_id no cuenta (no apunta a ninguna entidad activa).
     */
    public static function activeIdsSql(string $entityType): string
    {
        if (! isset(self::$activeIds[$entityType]) || app()->runningInConsole()) {
            $ids = DB::table('market_entities')
                ->where('entity_type', $entityType)
                ->where('is_active', 1)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->implode(',');
            self::$activeIds[$entityType] = $ids !== '' ? $ids : '0';
        }

        return self::$activeIds[$entityType];
    }

    /* ---------------- b) + c) en PHP (procesos por tramos) ---------------- */

    /**
     * c) Ids de las entidades activas del tipo como conjunto (id => true), o null si el filtro
     * está apagado. Para procesos que leen las asociaciones por tramos y filtran en memoria.
     *
     * @return array<int, true>|null
     */
    public static function activeIdSet(string $entityType): ?array
    {
        if (in_array('inactive', self::$off, true)) {
            return null;
        }

        return array_fill_keys(array_map('intval', explode(',', self::activeIdsSql($entityType))), true);
    }

    /**
     * b) Pares (oferta, entidad) excluidos por el detector para las ofertas con id en
     * [$fromOffer, $toOffer), como conjunto "oferta:entidad" => true ([] si la exclusión está
     * apagada). Una lectura por tramo (índice idx_eae_filtro) en vez de un NOT EXISTS por par.
     *
     * @return array<string, true>
     */
    public static function excludedPairSet(string $pivot, int $fromOffer, int $toOffer): array
    {
        if (! ExclusionSettings::active() || in_array('detector', self::$off, true)) {
            return [];
        }
        // Cadenas en vez de objetos: un tramo puede traer decenas de miles de pares
        return array_fill_keys(DB::table(ExclusionSettings::TABLE)
            ->where('pivot_table', $pivot)
            ->where('job_offer_id', '>=', $fromOffer)->where('job_offer_id', '<', $toOffer)
            ->where('vigente', 1)
            ->selectRaw("CONCAT(job_offer_id, ':', market_entity_id) AS k")
            ->pluck('k')
            ->all(), true);
    }

    public static function entityType(string $pivot): string
    {
        return self::PIVOTS[$pivot] ?? throw new \InvalidArgumentException("Tabla pivote sin tipo de entidad: {$pivot}");
    }
}
