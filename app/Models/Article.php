<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'details',
        'quanty',
        'price',
        'file_1',
        'file_2',
        'file_3',
        'file_4',
        'transfer_id',
        'product_id',
        'code',
        'condition',
        'state',
        
    ];
}
