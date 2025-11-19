<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfPageGraph extends Model
{
    protected $table = 'pdf_page_graphs';

    protected $fillable = [
        'page_id',
        'title',
        'data_json',
        'insights_json',
    ];

    protected $casts = [
        'data_json' => 'array',
        'insights_json' => 'array',
    ];

    public function page()
    {
        return $this->belongsTo(PdfPage::class, 'page_id');
    }
}
