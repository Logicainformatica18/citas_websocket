<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrendMarketSignal extends Model
{
    use HasFactory;

    protected $table = 'trend_market_signals';

    protected $fillable = [
        'topic_id',
        'topic_name',
        'topic_category',
        'topic_type',
        'year',
        'quarter',
        'job_offer_count',
        'regions',
        'roles',
        'signal_strength',
        'confidence_level',
        'scanned_keywords',
        'source_breakdown',
        'last_scanned_at',
    ];

    protected $casts = [
        'regions'          => 'array',
        'roles'            => 'array',
        'scanned_keywords' => 'array',
        'source_breakdown' => 'array',
        'last_scanned_at'  => 'datetime',
        'signal_strength'  => 'float',
    ];

    /* ================= RELATIONS ================= */

    public function trend()
    {
        return $this->belongsTo(
            TechnologyTrend::class,
            'topic_id'
        );
    }

    /* ================= SCOPES ================= */

    public function scopeForPeriod($query, int $year, int $quarter)
    {
        return $query
            ->where('year', $year)
            ->where('quarter', $quarter);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('topic_type', $type);
    }

    public function scopeWithMarketSignal($query, int $minOffers = 1)
    {
        return $query->where('job_offer_count', '>=', $minOffers);
    }
}
