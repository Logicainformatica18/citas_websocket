<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOffer extends Model
{
    use HasFactory;

    protected $table = 'job_offers';

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'title',
        'company',
        'country',
        'city',
        'latitude',
        'longitude',
        'modality',
        'workload',
        'salary_min',
        'salary_max',
        'currency',
        'source',
        'external_id',
        'url',
        'search_query',
        'published_at',
    ];

    /**
     * Casts automáticos
     */
    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'salary_min'   => 'decimal:2',
        'salary_max'   => 'decimal:2',
        'published_at' => 'datetime',
    ];
}
