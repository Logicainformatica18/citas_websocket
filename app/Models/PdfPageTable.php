<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfPageTable extends Model
{
    protected $fillable = [
        'page_id',
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
