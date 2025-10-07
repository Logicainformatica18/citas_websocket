<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnologyTrendEnriched extends Model
{
    protected $table = "technology_trend_enricheds";
    protected $fillable = [
        'language',
        'language_type',
        'iso2_code',
        'year',
        'quarter',
        'num_repos',
        'num_users',
        'num_pushes',
        'total_bytes',
        'popularity_index',
        'source',
    ];
}
