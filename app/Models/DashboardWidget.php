<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'group_id',
        'ai_training_id',
        'title',
        'chart_type',
        'content',
        'type',
        'text',
        'data_source',
        'filters',
        'colors',
        'primary_color',
        'position_x',
        'position_y',
        'width',
        'height',
        'is_expanded',
        'options',
    ];

    protected $casts = [
        'data_source' => 'array',
        'filters' => 'array',
        'colors' => 'array',
        'options' => 'array',
    ];

    public function dashboard()
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function aiTraining()
    {
        return $this->belongsTo(AITraining::class, 'ai_training_id');
    }

    public function group()
    {
        return $this->belongsTo(DashboardWidget::class, 'group_id');
    }

    public function children()
    {
        return $this->hasMany(DashboardWidget::class, 'group_id');
    }
}
