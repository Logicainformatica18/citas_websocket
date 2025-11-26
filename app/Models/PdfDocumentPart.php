<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfDocumentPart extends Model
{
protected $fillable = [
    'scraping_source_id',
    'part_number',
    'file_path',
    'original_name',
    'gcs_path',
    'ocr_output_prefix',
    'start_page',
    'end_page',
    'processed'
];


    /**
     * 📌 Relación correcta con ScrapingSource
     */
    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'scraping_source_id');
    }

    /**
     * 📄 Páginas OCR detectadas
     */
    public function pages()
    {
        return $this->hasMany(PdfPage::class, 'part_id');
    }

    /**
     * 🧠 Resumen del bloque
     */
    public function summary()
    {
        return $this->hasOne(PdfPartSummary::class, 'part_id');
    }
}
