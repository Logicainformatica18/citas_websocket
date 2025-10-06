<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareerCourse extends Model
{
    use HasFactory;

    protected $table = 'career_course'; // Nombre real de la tabla pivote

    protected $fillable = [
        'career_id',
        'course_id',
        'semester',
        'is_mandatory',
    ];

    /**
     * 🔹 Relaciones inversas
     */
    public function career()
    {
        return $this->belongsTo(Career::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * 🔹 Scope útil: filtrar por semestre
     */
    public function scopeSemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    /**
     * 🔹 Scope: solo obligatorios
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }
}
