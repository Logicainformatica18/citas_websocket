<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EntityTrend extends Model
{
    protected $table = 'entity_trends';

    /**
     * Solo usa created_at
     */
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    /**
     * Campos permitidos
     */
    protected $fillable = [
        'market_entity_id',
        'technology_trend_id',

        'trend_name',
        'trend_score',

        'source_title',
        'source_url',
        'source_type',

        'discovered_by',
        'discovered_at',

        'year',
        'quarter',

        'match_type',
        'confidence_score',

        'raw_payload',
        'created_at',
    ];

    /**
     * Casts correctos
     */
    protected $casts = [
        'trend_score'      => 'float',
        'confidence_score' => 'float',
        'discovered_at'    => 'datetime',
        'created_at'       => 'datetime',
        'raw_payload'      => 'array',
        'year'             => 'integer',
        'quarter'          => 'integer',
    ];

    /* =====================================================
       RELACIONES
    ===================================================== */

    /**
     * Entidad de mercado asociada
     */
    public function marketEntity()
    {
        return $this->belongsTo(
            MarketEntity::class,
            'market_entity_id'
        );
    }

    /**
     * Trend tecnológico origen (si aplica)
     */
    public function technologyTrend()
    {
        return $this->belongsTo(
            TechnologyTrend::class,
            'technology_trend_id'
        );
    }

    /* =====================================================
       SCOPES ÚTILES PARA AUDITORÍA
    ===================================================== */

    /**
     * Filtrar por periodo
     */
    public function scopeForPeriod(Builder $query, int $year, int $quarter): Builder
    {
        return $query->where('year', $year)
                     ->where('quarter', $quarter);
    }

    /**
     * Filtrar por entidad
     */
    public function scopeByEntity(Builder $query, int $entityId): Builder
    {
        return $query->where('market_entity_id', $entityId);
    }

    /**
     * Solo trends con fuente válida
     */
    public function scopeWithSource(Builder $query): Builder
    {
        return $query->whereNotNull('source_url');
    }

    /**
     * Orden por score descendente
     */
    public function scopeTop(Builder $query): Builder
    {
        return $query->orderByDesc('trend_score');
    }
}
