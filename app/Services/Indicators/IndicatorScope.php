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
 *  a) Ofertas: se ignoran las excluidas por título (rol no TI, job_offers.title_exclusion_id,
 *     que marca job-offers:apply-title-exclusions).
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

    /** " AND {alias}.title_exclusion_id IS NULL" (vacío si la columna aún no existe). */
    public static function offersSql(string $alias = 'job_offers'): string
    {
        return JobOffer::hasTitleExclusionColumn() && ! in_array('title', self::$off, true)
            ? " AND {$alias}.title_exclusion_id IS NULL" : '';
    }

    /** Igual que offersSql() para el Query Builder. */
    public static function offers(Builder $query, string $alias = 'job_offers'): Builder
    {
        if (JobOffer::hasTitleExclusionColumn() && ! in_array('title', self::$off, true)) {
            $query->whereNull("{$alias}.title_exclusion_id");
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
        return JobOffer::hasTitleExclusionColumn() && ! in_array('title', self::$off, true)
            ? "SUM({$alias}.title_exclusion_id IS NULL)" : 'COUNT(*)';
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

    public static function entityType(string $pivot): string
    {
        return self::PIVOTS[$pivot] ?? throw new \InvalidArgumentException("Tabla pivote sin tipo de entidad: {$pivot}");
    }
}
