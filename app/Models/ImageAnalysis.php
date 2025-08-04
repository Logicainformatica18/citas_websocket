<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageAnalysis extends Model
{
    protected $fillable = [
        'filename',
        'response',
    ];
}
