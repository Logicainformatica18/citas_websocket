<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardSection extends Model
{
    use HasFactory;

    protected $table = 'dashboard_sections';

    protected $fillable = [
        'dashboard_id',
        'title',
        'description',
        'position',
        'height',
        'colors', //  
    ];

    protected $casts = [
        'colors' => 'array', //  
    ];

    public function dashboard()
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class, 'group_id');
    }
}
