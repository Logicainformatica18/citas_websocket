<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Technology extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'context_id',
        'enabled',   // 👈 importante para permitir edición
    ];

    protected $casts = [
        'enabled' => 'boolean', // 👈 para que siempre llegue como true/false
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function category()
    {
        return $this->belongsTo(TechnologyCategory::class, 'category_id');
    }

    public function metrics()
    {
        return $this->hasMany(TechnologyMetric::class);
    }

    public function context()
    {
        return $this->belongsTo(SemanticContext::class, 'context_id');
    }

    // 👇 Scope para filtrar SOLO tecnologías activas
    public function scopeActive($query)
    {
        return $query->where('enabled', true);
    }
}
