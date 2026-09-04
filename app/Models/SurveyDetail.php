<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyDetail extends Model
{
    protected $fillable = [
        'survey_id', 'selection_id', 'title', 'question', 'detail', 'detail_2',
        'detail_3', 'type', 'role', 'option', 'correct', 'point', 'requerid',
        'evaluate', 'initialize', 'category', 'enumeration', 'orden',
        'visible', 'state',
    ];

    protected $casts = [
        'option' => 'array',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function selection()
    {
        return $this->belongsTo(Selection::class);
    }
}
