<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfSummary extends Model
{
    protected $fillable = [
        'pdf_id',
        'summary_short',
        'summary_medium',
        'summary_long',
        'insights_json',
        'topics_json'
    ];

    protected $casts = [
        'insights_json' => 'array',
        'topics_json'   => 'array'
    ];

    // 🔗 Resumen pertenece al PDF
    public function document()
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_id');
    }
}
