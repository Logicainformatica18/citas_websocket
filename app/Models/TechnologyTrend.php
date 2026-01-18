<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnologyTrend extends Model
{
    protected $table = 'technology_trends';

    protected $fillable = [
        'source_id',
        'topic_name',
        'topic_category',
        'regions',
        'year',
        'quarter',
        'trend_score',
        'source_url',
        'source_title',
        'source_type',
        'raw_data',
        'scanned_keywords',
        'associated_technologies' // 👈 NUEVO
    ];

    protected $casts = [
        'regions' => 'array',
        'raw_data' => 'array',
        'scanned_keywords' => 'array',        // 👈 te faltaba
        'associated_technologies' => 'array', // 👈 NUEVO
    ];

    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'source_id');
    }

    public function jobs()
    {
        return $this->hasMany(
            TechnologyTrendJob::class,
            'technology_trend_id'
        );
    }
    public function markRunning(): void
{
    $this->update([
        'last_run_status' => 'running',
        'last_run_message' => null,
    ]);
}

public function markSuccess(int $results): void
{
    $this->update([
        'last_run_status' => 'success',
        'last_run_message' => "Resultados generados: {$results}",
        'success_count' => $this->success_count + 1,
        'last_success_at' => now(),
    ]);
}

public function markFail(string $message): void
{
    $this->update([
        'last_run_status' => 'failed',
        'last_run_message' => $message,
        'fail_count' => $this->fail_count + 1,
        'last_fail_at' => now(),
    ]);
}

}

