<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Methodology extends Model
{
    protected $fillable = ['name', 'slug', 'context_id', 'enabled'];

     public function courses()
    {
        return $this->belongsToMany(Course::class);
    }
    public function metrics()
{
    return $this->hasMany(MethodologyMetric::class);
}

public function context()
{
    return $this->belongsTo(SemanticContext::class, 'context_id');
}

}
