<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $fillable = [
        'title', 'description', 'detail', 'front_page', 'visible',
        'email_confirmation', 'password', 'created_by', 'pollster_r',
        'date_start', 'date_end', 'url', 'type', 'state',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'visible' => 'boolean',
        'email_confirmation' => 'boolean',
        'date_start' => 'date:Y-m-d',
        'date_end' => 'date:Y-m-d',
    ];

    public function created_bys()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(SurveyDetail::class);
    }
}