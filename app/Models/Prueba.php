<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Prueba  extends Model
{
    protected $table = 'ranking_weights';

    protected $fillable = [
        'labor_weight',
        'trend_weight',
        'context',
        'is_active',
        'applied_at',
        'updated_by',
    ];

    protected $casts = [
        'labor_weight' => 'float',
        'trend_weight' => 'float',
        'is_active'    => 'boolean',
        'applied_at'   => 'datetime',
    ];

    /* =====================================================
       SCOPES
    ===================================================== */

    /**
     * Scope: solo ponderaciones activas
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: por contexto (certifications, careers, etc.)
     */
    public function scopeContext(Builder $query, string $context): Builder
    {
        return $query->where('context', $context);
    }

    /* =====================================================
       MÉTODOS DE DOMINIO
    ====================================================public static function getActive(string $context = 'certifications'): self
= */

    /**
     * Obtener la ponderación activa para un contexto
     */
public static function getActive(string $context = 'certifications'): ?self

    {
        return static::query()
            ->context($context)
            ->active()
            ->orderByDesc('id')
            ->first();

    }

    /**
     * Activar esta ponderación y desactivar las demás
     */
    public function activate(): void
    {
        static::query()
            ->where('context', $this->context)
            ->where('is_active', 1)
            ->update(['is_active' => 0]);

        $this->update([
            'is_active'  => 1,
            'applied_at' => now(),
        ]);
    }

    /* =====================================================
       VALIDACIONES DE NEGOCIO
    ===================================================== */

    /**
     * Validar que la suma sea 1.00 (100%)
     */
    public function isValid(): bool
    {
        return round($this->labor_weight + $this->trend_weight, 2) === 1.00;
    }
}
