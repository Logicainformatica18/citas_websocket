<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Syllabus extends Model
{
    use HasFactory;

    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_SILABOS_V2 = 'silabos_v2';

    protected $table = 'syllabus';

    protected $fillable = [
        'filename',
        'path',
        'status',
        'raw_text',
        'structured_data',
        // Integración silabos_v2
        'source',
        'external_model',
        'external_id',
        'codigo_familia',
        'external_version',
        'periodo',
        'periodo_orden',
        'tipo_plantilla',
        'course_code',
        'course_name',
        'course_id',
        'careers',
        'approved_at',
        'approved_at_source',
        'content',
        'content_hash',
        'superseded_at',
        'superseded_by',
    ];

    protected $casts = [
        'structured_data' => 'array', // Laravel lo maneja como array
        'careers' => 'array',
        'content' => 'array',
        'approved_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    protected $appends = ['detected_course'];

    public function getDetectedCourseAttribute()
    {
        return $this->structured_data['curso'] ?? null;
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /** Sílabos que siguen vigentes (no reemplazados por una versión posterior). */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function isFromSilabosV2(): bool
    {
        return $this->source === self::SOURCE_SILABOS_V2;
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
