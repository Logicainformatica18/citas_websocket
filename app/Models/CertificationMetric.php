<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificationMetric extends Model
{
    protected $table = 'certification_metrics';

    protected $fillable = [
        'certification_id',
        'certification_name',
        'jobs_found_count',
        'jobs_new_count',
        'countries_breakdown',
        'modality_breakdown',
        'run_date',
        'source',
    ];

    protected $casts = [
        'countries_breakdown' => 'array',
        'modality_breakdown'  => 'array',
        'run_date'            => 'date',
    ];

    public function certification()
    {
        return $this->belongsTo(Certification::class);
    }
}
