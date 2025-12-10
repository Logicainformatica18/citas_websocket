<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnologyTrend extends Model
{
    protected $table = 'technology_trends';

    protected $fillable = [
        'source_id',
        'topic_name',
        'topic_category',
        'regions',
        'year',
        'quarter',
        'trend_score',
        'source_url',
        'source_title',
        'source_type',
        'raw_data'
    ];

    protected $casts = [
        'regions' => 'array',
        'raw_data' => 'array'
    ];

    // Relación con scraping_sources
    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'source_id');
    }
}
