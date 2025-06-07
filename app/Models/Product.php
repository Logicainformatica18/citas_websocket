<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'code',
        'sku',
        'description',
        'detail',
        'brand',
        'model',
        'serial_number',
        'condition',
        'state',
        'quantity',
        'price',
        'location',
        'file_1',
    ];
    
}
