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
    ];

    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
        'has_ai_response' => 'boolean',
    ];
}
