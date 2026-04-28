<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceStatus extends Model
{
    protected $table = 'source_status';

    protected $fillable = [
        'source',
        'last_run_id',

        // 🔹 Estado ejecución
        'last_status',
        'last_started_at',
        'last_finished_at',
        'last_duration_seconds',

        // 🔹 Último run
        'last_records_found',
        'last_records_inserted',
        'last_records_skipped',

        // 🔥 NUEVO → acumulado histórico
        'total_records_found',
        'total_records_inserted',
        'total_records_skipped',

        // 🔹 errores
        'last_error',

        // 🔹 métricas tiempo
        'last_success_at',
        'last_failed_at',

        'fail_count',
        'success_count',

        // 🔥 CONFIG API
        'api_url',
        'api_key',
        'app_id',

        // 🔥 CONEXIÓN API
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
            return 'adzuna';
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
            'app_key' => $this->api_key,
        ];
    }

    /**
     * 🧠 NUEVO → Total registros (fallback inteligente)
     */
    public function getTotalRegistrosAttribute()
    {
        return $this->total_records_inserted ?? 0;
    }

    /**
     * 🧠 NUEVO → Último run registros
     */
    public function getLastRegistrosAttribute()
    {
        return $this->last_records_inserted ?? 0;
    }

    /**
     * 🧠 NUEVO → Uptime individual
     */
    public function getUptimeAttribute()
    {
        $total = $this->success_count + $this->fail_count;

        if ($total === 0) return 0;

        return round(($this->success_count / $total) * 100, 2);
    }

    /**
     * 🚨 NUEVO → detectar fuente muerta
     */
    public function getIsStaleAttribute()
    {
        if (!$this->last_success_at) return true;

        return now()->diffInHours($this->last_success_at) > 6;
    }
}