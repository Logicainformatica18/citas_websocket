<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingWebResult extends Model
{
    protected $table = 'scraping_web_results';

    protected $fillable = [
        'source_id',
        'url',
        'raw_html',
        'ai_raw_response',
        'ai_json',
        'status',
        'error_message',
    ];

    protected $casts = [
        'ai_json' => 'array', // para que ya venga como array
    ];

    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'source_id');
    }
}
