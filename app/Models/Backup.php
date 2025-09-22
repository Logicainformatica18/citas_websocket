<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'scraping_id',
        'row_id',
        'data',
        'reviewed',
    ];

    protected $casts = [
        'data' => 'array',       // JSON ↔ array automático
        'reviewed' => 'boolean',
    ];

    // Relación: este backup pertenece a un scraping
    public function scraping()
    {
        return $this->belongsTo(Scraping::class);
    }
}
