<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Certification;
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
    public function languages()
{
    return $this->belongsToMany(Language::class, 'language_job')
                ->withTimestamps();
}
    /**
     * 🔹 Relación con tecnologías
     */
    public function technologies()
    {
        return $this->belongsToMany(Technology::class, 'technology_job')
                    ->withTimestamps();
    }

    /**
     * 🔹 Relación con metodologías
     */
    public function methodologies()
    {
        return $this->belongsToMany(Methodology::class, 'methodology_job')
                    ->withTimestamps();
    }
public function competencies()
{
    return $this->belongsToMany(Competency::class, 'competency_job_offer')
                ->withTimestamps();
}
public function analyzeCompetenciesWeighted(int $careerId): array
{
    $texto = strtolower(
        ($this->title ?? '') . ' ' .
        ($this->requirements ?? '') . ' ' .
        ($this->skills ?? '') . ' ' .
        ($this->description ?? '')
    );

    $competencias = Competency::where('career_id', $careerId)->get();

    $matched = [];
    $missing = [];
    $score = 0;
    $totalWeight = $competencias->sum('weight');

    foreach ($competencias as $comp)
    {
        $match = str_contains($texto, strtolower($comp->name))
              || str_contains($texto, strtolower($comp->category))
              || str_contains($texto, strtolower($comp->description_es));

        if ($match) {
            $matched[] = $comp->name;
            $score += $comp->weight;
        } else {
            $missing[] = $comp->name;
        }
    }

    $finalScore = $totalWeight > 0 ? $score / $totalWeight : 0;

    return [
        'matched' => $matched,
        'missing' => $missing,
        'score'   => round($finalScore, 4),
    ];
}

/**
 * 🏅 Certificaciones asociadas a la oferta laboral
 */
public function certifications()
{
    return $this->belongsToMany(
        Certification::class,
        'certification_job',
        'job_offer_id',
        'certification_id'
    )->withTimestamps();
}
public function trendLinks()
{
    return $this->hasMany(
        TechnologyTrendJob::class,
        'job_offer_id'
    );
}

}
