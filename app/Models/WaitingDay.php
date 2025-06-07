<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitingDay extends Model
{
    protected $table = 'dias_espera';
    protected $primaryKey = 'id_dias_espera';
    public $timestamps = false;

    protected $fillable = [
        'dias',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];
}
