<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfPartSummary extends Model
{
    protected $fillable = [
        'part_id',
        'summary_short',
        'summary_medium',
        'summary_long',
        'insights_json',
        'topics_json',
    ];

    protected $casts = [
        'insights_json' => 'array',
        'topics_json'   => 'array',
    ];

    public function part()
    {
        return $this->belongsTo(PdfDocumentPart::class, 'part_id');
    }
}
