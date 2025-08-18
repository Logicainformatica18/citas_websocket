<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MotivoCitaArea extends Pivot
{
    protected $table = 'motivos_cita_area';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'area_id',
        'id_motivos_cita',
    ];
}
