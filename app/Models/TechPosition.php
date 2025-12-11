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
        'active'
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

    /**
     * 🔗 LENGUAJES
     */
    public function languages()
    {
        return $this->belongsToMany(Language::class, 'tech_position_language');
    }

    /**
     * 🔗 TECNOLOGÍAS
     */
    public function technologies()
    {
        return $this->belongsToMany(Technology::class, 'tech_position_technology');
    }

    /**
     * 🔗 COMPETENCIAS
     */
    public function competencies()
    {
        return $this->belongsToMany(Competency::class, 'tech_position_competency');
    }

    /**
     * 🔗 METODOLOGÍAS
     */
    public function methodologies()
    {
        return $this->belongsToMany(Methodology::class, 'tech_position_methodology');
    }
}
