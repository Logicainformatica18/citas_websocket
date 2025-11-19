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

    public function parts()
    {
        return $this->hasMany(PdfDocumentPart::class, 'pdf_id');
    }

    public function pages()
    {
        return $this->hasMany(PdfPage::class, 'pdf_id');
    }
}
