<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfPage extends Model
{
    protected $fillable = [
        'pdf_id',
        'page_number',
        'image_path',
        'text_content',
        'content_type',
        'metadata_json',
        'detected_elements',
        'ai_processed'
    ];

    protected $casts = [
        'metadata_json'     => 'array',
        'detected_elements' => 'array',
        'ai_processed'      => 'boolean',
    ];

    // 📘 Relación con el documento
    public function document()
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_id');
    }

    // 📊 Gráficos detectados en la página
    public function graphs()
    {
        return $this->hasMany(PdfGraph::class, 'pdf_page_id');
    }

    // 📋 Tablas detectadas en la página
    public function tables()
    {
        return $this->hasMany(PdfTable::class, 'pdf_page_id');
    }
}
