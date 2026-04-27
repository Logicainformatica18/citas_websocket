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

        // 🔥 CONFIG API
        'api_url',
        'api_key',   // 👉 se usa como app_key

        // 🔥 NUEVO CAMPO
        'app_id',

        // 🔥 ESTADO CONEXIÓN
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
     * 🔗 Relación con scraper_runs
     */
    public function lastRun()
    {
        return $this->belongsTo(ScraperRun::class, 'last_run_id');
    }

    /**
     * 🎨 Color estado scraping
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
     * 🔘 Icono estado scraping
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
     * 🔌 Icono conexión API
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
     * 🔎 Scope: fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('last_status', 'failed');
    }

    /**
     * ⏱ Scope: recientes
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('last_finished_at', '>=', now()->subHours($hours));
    }

    /**
     * 🔥 Tiene credenciales tipo Adzuna
     */
    public function hasAdzunaCredentials(): bool
    {
        return !empty($this->app_id) && !empty($this->api_key);
    }

    /**
     * 🔥 Tipo de API configurada
     */
    public function getApiTypeAttribute()
    {
        if ($this->app_id && $this->api_key) {
            return 'adzuna'; // app_id + api_key
        }

        if ($this->api_key) {
            return 'api_key';
        }

        return 'none';
    }

    /**
     * 🔥 Helper: obtener credenciales listas
     */
    public function getCredentials()
    {
        return [
            'app_id' => $this->app_id,
            'app_key' => $this->api_key, // 👈 reutilizado
        ];
    }
}
