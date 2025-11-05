<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'title',
        'description',
        'position',
        'width',
        'height',
    ];

    public function dashboard()
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class, 'group_id'); // para agrupar gráficos bajo la sección
    }
}
