<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyClient extends Model
{
    protected $table = 'survey_clients';

    protected $fillable = [
        'survey_detail_id', 'client_id', 'option', 'answer', 'selection_detail_id',
    ];

    protected $casts = [
        'option' => 'string',
    ];
}
