<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['name', 'search_context', 'context_id'];

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function context()
    {
        return $this->belongsTo(SemanticContext::class, 'context_id');
    }
}
