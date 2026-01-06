<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',          // 👈 CLAVE para navegación por URL
        'description',
        'layout_type',
        'is_default',
    ];

    /* =====================================================
       Relaciones
    ===================================================== */
    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class);
    }

    /* =====================================================
       Helpers útiles (opcional pero recomendado)
    ===================================================== */

    // URL directa del dashboard
    public function getUrlAttribute(): string
    {
        return route('dashboard.show', $this->slug);
    }
}
