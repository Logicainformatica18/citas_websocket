<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * 🔹 Relationships
     */
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

    /**
     * 🔹 Nueva relación con Career
     * Un curso puede pertenecer a muchas carreras
     */
    public function careers()
    {
        return $this->belongsToMany(Career::class)
            ->withPivot('semester', 'is_mandatory')
            ->withTimestamps();
    }
}
