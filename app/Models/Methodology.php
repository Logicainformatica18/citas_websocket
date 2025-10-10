<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Methodology extends Model
{
      protected $fillable = ['name'];
     public function courses()
    {
        return $this->belongsToMany(Course::class);
    }
    public function metrics()
{
    return $this->hasMany(MethodologyMetric::class);
}

}
