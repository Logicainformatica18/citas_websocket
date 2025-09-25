<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Syllabus extends Model
{
    use HasFactory;

    protected $table = 'syllabus';

    protected $fillable = [
        'filename',
        'path',
        'status',
        'raw_text',
        'structured_data',
    ];

    protected $casts = [
        'structured_data' => 'array',  
    ];
}
