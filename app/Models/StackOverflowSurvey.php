<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StackOverflowSurvey extends Model
{
    use HasFactory;

    protected $table = 'stackoverflow_surveys';

    protected $fillable = [
        'main_branch',
        'age',
        'country',
        'iso2',
        'employment',
        'remote_work',
        'ed_level',
        'learn_code',
        'years_code',
        'years_code_pro',
        'dev_type',
        'currency',
        'comp_total',
        'language_have_worked_with',
        'language_want_work_with',
        'language_admired',
        'database_have_worked_with',
        'webframe_have_worked_with',
        'platform_have_worked_with',
        'ai_select',
        'ai_sentiment',
        'ai_benefit',
        'org_size',
        'industry',
        'job_satisfaction',
        'year',
    ];

    protected $casts = [
        'comp_total' => 'float',
        'year' => 'integer',
    ];
}
