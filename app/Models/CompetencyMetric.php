<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetencyMetric extends Model
{
    protected $fillable = [
        'competency_id',
        'competency_name',
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
        'run_date' => 'date',
    ];
}
