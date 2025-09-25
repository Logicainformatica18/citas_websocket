<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['name'];

    public function languages()
    {
        return $this->belongsToMany(Language::class);
    }

    public function technologies()
    {
        return $this->belongsToMany(Technology::class);
    }

    public function methodologies()
    {
        return $this->belongsToMany(Methodology::class);
    }
}

