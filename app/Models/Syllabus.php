<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Syllabus extends Model
{
    use HasFactory;

    protected $table = 'syllabus';

    protected $fillable = [
        'filename',
        'path',
        'status',
        'raw_text',
        'structured_data',
    ];

    protected $casts = [
        'structured_data' => 'array', // Laravel lo maneja como array
    ];

    protected $appends = ['detected_course'];

    public function getDetectedCourseAttribute()
    {
        return $this->structured_data['curso'] ?? null;
    }

    // public function getStructuredDataAttribute($value)
    // {
    //     $data = $value ?? [];

    //     return [
    //         // 🔑 mantén las claves que React espera
    //         'languages'      => $data['lenguajes'] ?? [],
    //         'technologies'   => $data['tecnologias'] ?? [],
    //         'methodologies'  => $data['metodologias'] ?? [],
    //         'curso'          => $data['curso'] ?? null,
    //     ];
    // }
}
