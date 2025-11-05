<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'layout_type',
        'is_default',
    ];

    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class);
    }
}
