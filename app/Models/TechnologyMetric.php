<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnologyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'technology_id',
        'technology_name',
        'jobs_found_count',
        'jobs_new_count',
        'countries_breakdown',
        'modality_breakdown',
        'run_date',
        'source',
    ];

    protected $casts = [
        'countries_breakdown' => 'array',
        'modality_breakdown' => 'array',
        'run_date' => 'datetime',
    ];

    /**
     * Relación: una métrica pertenece a una tecnología.
     */
    public function technology()
    {
        return $this->belongsTo(Technology::class);
    }
}
