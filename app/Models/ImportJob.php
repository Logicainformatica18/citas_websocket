<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'source', 'filename', 'columns_detected', 'status'
    ];

    protected $casts = [
        'columns_detected' => 'array',
    ];

    public function mapping()
    {
        return $this->hasOne(ImportMapping::class);
    }
}
