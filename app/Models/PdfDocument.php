<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfDocument extends Model
{
    protected $fillable = [
        'title',
        'description',
        'source',
        'year',
        'processed',
        'file_path',
        'total_pages',
    ];

    protected $casts = [
        'processed' => 'boolean',
    ];

    // 📄 Un PDF tiene muchas páginas
    public function pages()
    {
        return $this->hasMany(PdfPage::class, 'pdf_id');
    }

    // 📦 Trozos de texto para resúmenes
    public function chunks()
    {
        return $this->hasMany(PdfChunk::class, 'pdf_id');
    }

    // 🧠 Resumen global
    public function summary()
    {
        return $this->hasOne(PdfSummary::class, 'pdf_id');
    }
}
