<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Technology extends Model
{
     protected $fillable = ['name']; // 👈 agrega esto
     public function courses()
    {
        return $this->belongsToMany(Course::class);
    }
    public function category()
{
    return $this->belongsTo(TechnologyCategory::class, 'category_id');
}
public function metrics()
{
    return $this->hasMany(TechnologyMetric::class);
}
public function context() {
    return $this->belongsTo(SemanticContext::class, 'context_id');
}

}
