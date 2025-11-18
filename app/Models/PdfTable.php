<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfTable extends Model
{
    protected $fillable = [
        'pdf_page_id',
        'table_index',
        'data_json',
        'insights_json'
    ];

    protected $casts = [
        'data_json'    => 'array',
        'insights_json'=> 'array'
    ];

    // 🔗 Pertenece a una página
    public function page()
    {
        return $this->belongsTo(PdfPage::class, 'pdf_page_id');
    }
}
