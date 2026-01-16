<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrendTopic extends Model
{
    protected $table = 'trend_topics';

    /* ============================================
     * Mass assignment
     * ============================================ */
    protected $fillable = [
        'topic_name',
        'topic_slug',
        'search_query',

        // 🔥 NUEVOS
        'intent',
        'execution_mode',
        'last_run_status',
        'last_run_message',

        'category',
        'subcategory',
        'importance_weight',
        'active',

        // métricas
        'fail_count',
        'last_fail_at',
        'success_count',
        'last_success_at',
        'auto_disabled_reason',
        'min_required_results',
    ];

    /**
     * Casts
     *
     * ❗ No se castea boolean por decisión técnica correcta
     */
    protected $casts = [
        'importance_weight' => 'integer',
        'fail_count'        => 'integer',
        'success_count'     => 'integer',
        'min_required_results' => 'integer',

        'last_fail_at'      => 'datetime',
        'last_success_at'   => 'datetime',
    ];

    /* ============================================
     * Relaciones
     * ============================================ */
    public function trends()
    {
        return $this->hasMany(
            TechnologyTrend::class,
            'topic_category',
            'topic_name'
        );
    }

    /* ============================================
     * Scopes
     * ============================================ */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function scopeRunnable($query)
    {
        return $query
            ->where('active', 1)
            ->where('execution_mode', 'manual');
    }

    public function scopeIntent($query, string $intent)
    {
        return $query->where('intent', $intent);
    }

    /* ============================================
     * Boot: generar slug automáticamente
     * ============================================ */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (empty($model->topic_slug) && !empty($model->topic_name)) {
                $model->topic_slug = Str::slug($model->topic_name);
            }
        });
    }

    /* ============================================
     * Métodos de estado (UX / ejecución)
     * ============================================ */

    public function markRunning(): void
    {
        $this->last_run_status  = 'running';
        $this->last_run_message = null;
        $this->save();
    }

    public function markSuccess(int $results = 0): void
    {
        $this->success_count++;
        $this->last_success_at  = now();
        $this->fail_count       = 0;
        $this->auto_disabled_reason = null;

        $this->last_run_status  = 'success';
        $this->last_run_message = "Resultados generados: {$results}";
        $this->save();
    }

    public function markFail(string $message = null): void
    {
        $this->fail_count++;
        $this->last_fail_at     = now();

        $this->last_run_status  = 'failed';
        $this->last_run_message = $message;
        $this->save();
    }

    /* ============================================
     * Métodos existentes (respetados)
     * ============================================ */

    public function disable($reason = null)
    {
        $this->active = 0;
        $this->auto_disabled_reason = $reason;
        $this->save();
    }

    /* ============================================
     * Helpers semánticos (claridad de dominio)
     * ============================================ */

    public function isCertification(): bool
    {
        return $this->intent === 'certification';
    }

    public function isTechnologyTrend(): bool
    {
        return $this->intent === 'technology_trend';
    }

    public function isSkill(): bool
    {
        return $this->intent === 'skill';
    }

    public function isWorkforce(): bool
    {
        return $this->intent === 'workforce';
    }

    public function isMixed(): bool
    {
        return $this->intent === 'mixed';
    }
}
