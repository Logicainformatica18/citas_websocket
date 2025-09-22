<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingField extends Model
{
    protected $fillable = ['scraping_id', 'field_name', 'selector', 'path'];

    public function scraping()
    {
        return $this->belongsTo(Scraping::class);
    }
}
