<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    // market_entity_id: sin él, el firstOrCreate de los scrapers descartaba el vínculo
    // con market_entities y quedaban lenguajes sin entidad (Go, Rust, Scala).
    protected $fillable = ['name', 'search_context', 'context_id', 'enabled', 'market_entity_id'];

    /**
     * Lenguajes que los scrapers deben procesar: habilitados en la administración y,
     * si están vinculados a market_entities, con la entidad activa
     * (market_entities.is_active es la fuente de verdad).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('languages.enabled', 1)
            ->where(function (Builder $q) {
                $q->whereNull('languages.market_entity_id')
                    ->orWhereExists(function ($e) {
                        $e->selectRaw('1')
                            ->from('market_entities as me_active')
                            ->whereColumn('me_active.id', 'languages.market_entity_id')
                            ->where('me_active.is_active', 1);
                    });
            });
    }

    public function marketEntity()
    {
        return $this->belongsTo(MarketEntity::class, 'market_entity_id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function context()
    {
        return $this->belongsTo(SemanticContext::class, 'context_id');
    }
    public function jobOffers()
{
    return $this->belongsToMany(JobOffer::class, 'language_job')
                ->withTimestamps();
}

}
