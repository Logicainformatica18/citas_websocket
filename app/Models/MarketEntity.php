<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketEntity extends Model
{
    protected $table = 'market_entities';

    public $timestamps = false;

protected $fillable = [
    'name',
    'slug',
    'entity_type',
    'origin',
    'category',
    'vendor',
    'level',
    'has_isil',
    'has_trend',
];


    protected $casts = [
        'has_trend' => 'boolean',
    ];

    /* =========================================
       RELACIONES
    ========================================= */

    public function entityTrends()
    {
        return $this->hasMany(
            EntityTrend::class,
            'entity_id'
        )->whereColumn(
            'entity_trends.entity_type',
            'market_entities.entity_type'
        );
    }
}
