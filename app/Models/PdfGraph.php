<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfGraph extends Model
{
    protected $fillable = [
        'pdf_page_id',
        'graph_index',
        'image_path',
        'title',
        'data_json',
        'legend_json',
        'insights_json'
    ];

    protected $casts = [
        'data_json'    => 'array',
        'legend_json'  => 'array',
        'insights_json'=> 'array'
    ];

    // 🔗 Pertenece a una página
    public function page()
    {
        return $this->belongsTo(PdfPage::class, 'pdf_page_id');
    }
}
