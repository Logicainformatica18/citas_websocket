<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOffer extends Model
{
    use HasFactory;

    protected $table = 'job_offers';

    /**
     * 🧩 Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'title',
        'company',
        'country',
        'region',
        'city',
        'latitude',
        'longitude',
        'modality',
        'workload',
        'experience_level',
        'education_level',   // 🆕 Nivel educativo requerido (Bachelor, Master, etc.)
        'certifications',    // 🆕 Certificados detectados (AWS, Scrum, ITIL...)
        'requirements',      // 🆕 Texto con requisitos extraídos
        'skills',            // 🆕 Habilidades clave (Python, React, SQL...)
        'salary_min',
        'salary_max',
        'currency',
        'compensation_type',
        'source',
        'external_id',
        'url',
        'search_query',
        'published_at',
    ];

    /**
     * 🧠 Conversión automática de tipos
     */
    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'salary_min'   => 'decimal:2',
        'salary_max'   => 'decimal:2',
        'published_at' => 'datetime',
    ];

    /**
     * 📊 Accesor opcional: retorna skills como array
     */
    public function getSkillsArrayAttribute(): array
    {
        if (empty($this->skills)) return [];
        return array_map('trim', explode(',', $this->skills));
    }

    /**
     * 📊 Accesor opcional: retorna certificaciones como array
     */
    public function getCertificationsArrayAttribute(): array
    {
        if (empty($this->certifications)) return [];
        return array_map('trim', explode(',', $this->certifications));
    }
}
