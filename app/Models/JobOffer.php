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
        'location',
        'modality',
        'workload',
        'salary_min',
        'salary_max',
        'currency',
        'source',
        'external_id',
        'url',
        'published_at',
    ];

    /**
     * Casts automáticos
     */
    protected $casts = [
        'published_at' => 'datetime',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
    ];
}
