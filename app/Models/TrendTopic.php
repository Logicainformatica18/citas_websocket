<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrendTopic extends Model
{
    protected $table = 'trend_topics';

    protected $fillable = [
        'topic_name',
        'topic_slug',
        'search_query',
        'category',
        'subcategory',
        'importance_weight',
        'active',
        'fail_count',
        'last_fail_at',
        'success_count',
        'last_success_at',
        'auto_disabled_reason',
        'min_required_results'
    ];

    /**
     * Casts
     *
     * ❗ Eliminamos el cast de boolean porque MySQL tinyint + boolean cast
     * puede producir valores NULL y no respetar 0/1 en el guardado.
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
        return $this->hasMany(TechnologyTrend::class, 'topic_category', 'topic_name');
    }

    /* ============================================
     * Scopes
     * ============================================ */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
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
     * Métodos útiles
     * ============================================ */

    public function markSuccess()
    {
        $this->success_count++;
        $this->last_success_at = now();
        $this->save();
    }

    public function markFail()
    {
        $this->fail_count++;
        $this->last_fail_at = now();
        $this->save();
    }

    public function disable($reason = null)
    {
        $this->active = 0;
        $this->auto_disabled_reason = $reason;
        $this->save();
    }
}
