<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TechnologyTrendJob extends Model
{
    use HasFactory;

    protected $table = 'technology_trend_job';

    protected $fillable = [
        'technology_trend_id',
        'job_offer_id',
        'match_type',
        'confidence_score',
    ];

    /* ======================================================
     | RELACIONES
     ====================================================== */

    public function trend()
    {
        return $this->belongsTo(
            TechnologyTrend::class,
            'technology_trend_id'
        );
    }

    public function jobOffer()
    {
        return $this->belongsTo(
            JobOffer::class,
            'job_offer_id'
        );
    }
}
