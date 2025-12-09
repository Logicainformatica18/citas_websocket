<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendTechnology extends Model
{
    protected $table = 'trend_technologies';

    protected $fillable = [
        'source_id',
        'source_url',
        'category',
        'subcategory',
        'item_name',
        'item_type',
        'summary',
        'year',
        'quarter',
        'value',
        'rank',
        'metadata',
        'hash',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'source_id');
    }

    public static function generateHash($sourceUrl, $itemName)
    {
        return hash('sha256', $sourceUrl . $itemName);
    }
}
