<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scraping extends Model
{
    protected $fillable = ['name', 'base_url'];

    public function fields()
    {
        return $this->hasMany(ScrapingField::class);
    }
}

