<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityTrend extends Model
{
    protected $table = 'entity_trends';

    /**
     * La tabla es analítica / transaccional híbrida
     * NO usa updated_at
     */
    public $timestamps = false;

    /**
     * Solo created_at existe como timestamp
     */
    const CREATED_AT = 'created_at';

    /**
     * Campos que SÍ existen en la tabla
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
     * Casts correctos (importante)
     */
    protected $casts = [
        'trend_score'      => 'float',
        'confidence_score' => 'float',
        'discovered_at'    => 'datetime',
        'created_at'       => 'datetime',
        'raw_payload'      => 'array',
    ];

    /**
     * Relación TRANSACCIONAL REAL
     */
    public function marketEntity()
    {
        return $this->belongsTo(
            MarketEntity::class,
            'market_entity_id'
        );
    }
}
