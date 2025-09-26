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
}
