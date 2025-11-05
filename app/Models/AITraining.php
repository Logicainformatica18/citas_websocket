<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AITraining extends Model
{
    use HasFactory;

    protected $table = 'aitrainings';

    protected $fillable = [
        'topic',
        'prompt',
        'interpreter',
        'component',
        'description',
        'tags',
        'is_active',
        'has_ai_response',
        'explanation_prompt',
        'sql_training_id',
        'is_trained',
        'training_stage',
        'last_trained_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
        'has_ai_response' => 'boolean',
    ];

    // 🧩 Relación con el SQL Training (opcional)
    public function sqlTraining()
    {
        return $this->belongsTo(SqlTraining::class, 'sql_training_id');
    }

    // 📊 Relación con los widgets que provienen de este entrenamiento
    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class, 'ai_training_id');
    }
}
