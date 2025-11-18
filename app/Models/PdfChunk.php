<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfChunk extends Model
{
    protected $fillable = [
        'pdf_id',
        'chunk_index',
        'content'
    ];

    // 📘 Relación con documento
    public function document()
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_id');
    }
}
