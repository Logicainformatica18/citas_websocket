<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScraperRun extends Model
{
    use HasFactory;

    protected $table = 'scraper_runs';

    protected $fillable = [
        'command',
        'source',
        'entity',

        'status',

        'started_at',
        'finished_at',

        'records_found',
        'records_inserted',
        'records_skipped',

        'error_message',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'records_found'    => 'integer',
        'records_inserted' => 'integer',
        'records_skipped'  => 'integer',
    ];
}
