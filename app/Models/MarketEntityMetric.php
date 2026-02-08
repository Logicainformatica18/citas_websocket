<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketEntityMetric extends Model
{
    protected $table = 'market_entity_metrics';

    protected $fillable = [
        'market_entity_id',
        'entity_name',
        'run_date',
        'source',
        'jobs_found_count',
        'jobs_new_count',
        'countries_breakdown',
        'modality_breakdown',
    ];

    protected $casts = [
        'run_date'             => 'date',
        'countries_breakdown'  => 'array',
        'modality_breakdown'   => 'array',
        'jobs_found_count'     => 'integer',
        'jobs_new_count'       => 'integer',
    ];

    /* =====================================================
       RELATIONS
    ===================================================== */

    public function marketEntity()
    {
        return $this->belongsTo(MarketEntity::class);
    }
}
