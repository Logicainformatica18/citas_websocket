<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TechPosition extends Model
{
    protected $table = 'tech_positions';

    protected $fillable = [
        'position_name',
        'position_name_en',
        'position_slug',
        'category',
        'subcategory',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * AUTO-slug al guardar
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (empty($model->position_slug) && !empty($model->position_name)) {
                $model->position_slug = Str::slug($model->position_name);
            }
        });
    }

    /* =====================================================
       🔗 RELACIONES CLAVE PARA LA MÉTRICA
    ===================================================== */

    /**
     * 🔗 Carreras asociadas a este rol
     * (regla académica del Observatorio)
     */
    public function careers()
    {
        return $this->belongsToMany(
            Career::class,
            'career_tech_position',
            'tech_position_id',
            'career_id'
        )->withTimestamps();
    }

    /**
     * 🔗 Vacantes donde aparece este rol
     * (demanda laboral real)
     */
    public function jobOffers()
    {
        return $this->belongsToMany(
            JobOffer::class,
            'job_offer_tech_position',
            'tech_position_id',
            'job_offer_id'
        )
        ->withPivot(['confidence_score', 'source'])
        ->withTimestamps();
    }
}
