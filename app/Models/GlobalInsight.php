<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalInsight extends Model
{
    protected $table = 'global_insights';

    protected $fillable = [
        'source',
        'source_url',
        'source_type',

        'category',
        'subcategory',

        'title',
        'summary',
        'content',

        'published_at',
        'region',
        'country',

        'impact_score',
        'keywords',
        'entities',

        'hash',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'keywords'     => 'array',
        'entities'     => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    // Filtrar por categoría
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Filtrar por fuente
    public function scopeSource($query, $source)
    {
        return $query->where('source', $source);
    }

    // Filtrar por año
    public function scopeYear($query, $year)
    {
        return $query->whereYear('published_at', $year);
    }

    // Insights más recientes
    public function scopeLatestInsights($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    // Por impacto
    public function scopeHighImpact($query)
    {
        return $query->where('impact_score', '>=', 4);
    }

    // Buscador simple por título o resumen
    public function scopeSearch($query, $text)
    {
        return $query->where(function ($q) use ($text) {
            $q->where('title', 'like', "%$text%")
              ->orWhere('summary', 'like', "%$text%");
        });
    }
}
