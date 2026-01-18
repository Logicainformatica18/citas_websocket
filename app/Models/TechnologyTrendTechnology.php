<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TechnologyTrendTechnology extends Model
{
    use HasFactory;

    protected $table = 'technology_trend_technology';

    protected $fillable = [
        'technology_trend_id',
        'technology_id',
        'confidence_score',
        'source',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
    ];

    /* =========================================================
     * Relaciones
     * ========================================================= */

    public function trend()
    {
        return $this->belongsTo(
            TechnologyTrend::class,
            'technology_trend_id'
        );
    }

    public function technology()
    {
        return $this->belongsTo(
            Technology::class,
            'technology_id'
        );
    }
}
