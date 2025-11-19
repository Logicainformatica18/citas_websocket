<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfPage extends Model
{
    protected $fillable = [
        'pdf_id',
        'part_id',
        'page_number',
        'text_content',
        'page_image',
        'type',
        'metadata_json',
        'elements_json',
        'ai_summary_short',
        'ai_summary_medium',
        'ai_summary_long',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'elements_json' => 'array',
    ];

    public function part()
    {
        return $this->belongsTo(PdfDocumentPart::class, 'part_id');
    }

    public function pdf()
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_id');
    }

    public function tables()
    {
        return $this->hasMany(PdfPageTable::class, 'page_id');
    }

    public function graphs()
    {
        return $this->hasMany(PdfPageGraph::class, 'page_id');
    }
}
