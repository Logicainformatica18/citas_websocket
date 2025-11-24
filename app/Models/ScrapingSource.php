<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingSource extends Model
{
    protected $table = 'scraping_sources';

    protected $fillable = [
        'name',
        'url',
        'frequency',
        'has_pdf',
        'web_only',
        'has_api',
        'scrapable',
        'notes',
    ];

    protected $casts = [
        'has_pdf'   => 'boolean',
        'web_only'  => 'boolean',
        'has_api'   => 'boolean',
        'scrapable' => 'boolean',
    ];
}
