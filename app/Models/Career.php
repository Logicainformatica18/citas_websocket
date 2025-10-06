<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Career extends Model
{
    use HasFactory;
 
    protected $fillable = [
        'name',
        'slug',
        'description',
        'detail',
        'faculty',
        'degree_title',
        'duration_years',
        'active',
    ];

    /**
     * 🔹 Relationships
     * A Career has many Courses (many-to-many)
     */
    public function courses()
    {
        return $this->belongsToMany(Course::class)
            ->withPivot('semester', 'is_mandatory')
            ->withTimestamps();
    }

    /**
     * 🔹 Automatically generate slug if not provided
     */
    protected static function booted()
    {
        static::creating(function ($career) {
            if (empty($career->slug)) {
                $career->slug = Str::slug($career->name);
            }
        });
    }

    /**
     * 🔹 Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByFaculty($query, $faculty)
    {
        return $query->where('faculty', $faculty);
    }
}
