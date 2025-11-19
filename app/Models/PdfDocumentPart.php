<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfDocumentPart extends Model
{
    protected $fillable = [
        'pdf_id',
        'part_number',
        'file_path',
        'gcs_path',
        'ocr_output_prefix',
        'start_page',
        'end_page',
        'processed'
    ];

    public function pdf()
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_id');
    }

    public function pages()
    {
        return $this->hasMany(PdfPage::class, 'part_id');
    }

    public function summary()
    {
        return $this->hasOne(PdfPartSummary::class, 'part_id');
    }
}
