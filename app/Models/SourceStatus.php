<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceStatus extends Model
{
    protected $table = 'source_status';

    protected $fillable = [
        'source',
        'last_run_id',

        'last_status',
        'last_started_at',
        'last_finished_at',
        'last_duration_seconds',

        'last_records_found',
        'last_records_inserted',
        'last_records_skipped',

        'last_error',

        'last_success_at',
        'last_failed_at',

        'fail_count',
        'success_count',

        // config API
        'api_url',
        'api_key',

        // estado conexión
        'connection_status',
        'last_connection_check',
        'connection_error',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_finished_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'last_connection_check' => 'datetime',
    ];

    /**
     * Relación con scraper_runs
     */
    public function lastRun()
    {
        return $this->belongsTo(ScraperRun::class, 'last_run_id');
    }

    /**
     * Helper: estado visual
     */
    public function getStatusColorAttribute()
    {
        return match ($this->last_status) {
            'success' => 'green',
            'failed' => 'red',
            'running' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Helper: icono estado
     */
    public function getStatusIconAttribute()
    {
        return match ($this->last_status) {
            'success' => '🟢',
            'failed' => '🔴',
            'running' => '🟡',
            default => '⚪',
        };
    }

    /**
     * Helper: estado conexión
     */
    public function getConnectionIconAttribute()
    {
        return match ($this->connection_status) {
            'ok' => '🟢',
            'failed' => '🔴',
            default => '⚪',
        };
    }

    /**
     * Scope: fuentes con error
     */
    public function scopeFailed($query)
    {
        return $query->where('last_status', 'failed');
    }

    /**
     * Scope: fuentes activas recientemente
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('last_finished_at', '>=', now()->subHours($hours));
    }
}
