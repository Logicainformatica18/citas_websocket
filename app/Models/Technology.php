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
        // Sin él, el firstOrCreate de los scrapers descartaba el vínculo con market_entities
        'market_entity_id',
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

    /**
     * Tecnologías que los scrapers deben procesar: habilitadas en la administración y,
     * si están vinculadas a market_entities, con la entidad activa
     * (market_entities.is_active es la fuente de verdad).
     */
    public function scopeActive($query)
    {
        return $query
            ->where('technologies.enabled', true)
            ->where(function ($q) {
                $q->whereNull('technologies.market_entity_id')
                    ->orWhereExists(function ($e) {
                        $e->selectRaw('1')
                            ->from('market_entities as me_active')
                            ->whereColumn('me_active.id', 'technologies.market_entity_id')
                            ->where('me_active.is_active', 1);
                    });
            });
    }

    public function marketEntity()
    {
        return $this->belongsTo(MarketEntity::class, 'market_entity_id');
    }
}
