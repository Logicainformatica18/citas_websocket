<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    protected $table = 'competencies';

 protected $fillable = [
    'career_id',
    'name',
    'description_es',
    'description_en',
    'category',
    'weight',        
    'embedding',
];

    protected $casts = [
        'embedding' => 'json',
    ];

    /**
     * Relación: una competencia pertenece a una carrera
     */
    public function career()
    {
        return $this->belongsTo(Career::class, 'career_id');
    }

    /**
     * Accesor para obtener un texto combinado útil en búsquedas o embeddings.
     */
    public function getFullTextAttribute()
    {
        return trim(
            ($this->name ?? '') . ' ' .
            ($this->description_es ?? '') . ' ' .
            ($this->category ?? '')
        );
    }
}
