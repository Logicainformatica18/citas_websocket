<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendTopic extends Model
{
    protected $table = 'trend_topics';

    protected $fillable = [
        'topic_name',     // nombre visible del topic
        'search_query',   // query completa para GPT-5 Search
        'context',        // contexto adicional opcional
        'active',         // si está habilitado o no
        'notes',          // notas internas
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Relación opcional con scraping_sources
     */
    public function sources()
    {
        return $this->belongsToMany(
            ScrapingSource::class,
            'source_topic_pivot',
            'topic_id',
            'source_id'
        );
    }

    /**
     * Relación con las tendencias generadas
     */
    public function trends()
    {
        return $this->hasMany(TechnologyTrend::class, 'topic_category', 'topic_name');
    }
}
