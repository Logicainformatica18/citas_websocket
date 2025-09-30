<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    protected $primaryKey = 'id';

    public $incrementing = false; // Porque el ID viene desde el Excel (no autoincremental)

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'city',
        'city_ascii',
        'lat',
        'lng',
        'country',
        'iso2',
        'iso3',
        'admin_name',
        'capital',
        'population',
    ];
}
