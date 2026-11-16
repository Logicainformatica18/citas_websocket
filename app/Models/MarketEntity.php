<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketEntity extends Model
{
    protected $table = 'market_entities';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'entity_type',
        'origin',
        'category',
        'vendor',
        'level',
        'has_isil',
        'has_trend',
        'is_active',
        'inactive_reason',
        'inactive_at',
    ];

    protected $casts = [
        'has_isil'  => 'boolean',
        'has_trend' => 'boolean',
        'is_active' => 'boolean',
        'inactive_at' => 'datetime',
    ];

    /**
     * Entidades activas. is_active es la única fuente de verdad: los rankings, la
     * evolución, los exports y los scrapers ignoran las inactivas (p. ej. IDC, OECD).
     */
    public function scopeActive($query)
    {
        return $query->where('market_entities.is_active', 1);
    }

    /** Desactiva la entidad sin borrar sus datos históricos. */
    public function deactivate(string $reason): void
    {
        $this->forceFill([
            'is_active' => false,
            'inactive_reason' => $reason,
            'inactive_at' => now(),
        ])->save();
    }

    public function activate(): void
    {
        $this->forceFill([
            'is_active' => true,
            'inactive_reason' => null,
            'inactive_at' => null,
        ])->save();
    }

    /* =========================================
       RELACIONES
    ========================================= */

    public function entityTrends()
    {
        return $this->hasMany(
            EntityTrend::class,
            'market_entity_id'
        );
    }

    /**
     * Relación MANY TO MANY con carreras
     */
    public function careers()
    {
        return $this->belongsToMany(
            Career::class,
            'market_entity_career',
            'market_entity_id',
            'career_id'
        )->withTimestamps();
    }
    public function certifications()
{
    return $this->hasMany(Certification::class, 'market_entity_id');
}
}
