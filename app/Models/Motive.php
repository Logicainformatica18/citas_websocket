<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AppointmentType;
use App\Models\WaitingDay;
use App\Models\Area;
class Motive extends Model
{
    protected $table = 'motivos_cita';
    protected $primaryKey = 'id_motivos_cita';
    public $timestamps = false;

    protected $fillable = [
        'nombre_motivo',
        'id_tipo_cita',
        'id_dia_espera',
        'id_area',
        'id_areap',
        'habilitado',

    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];

// app/Models/Motive.php

public function tipoCita()
{
    return $this->belongsTo(AppointmentType::class, 'id_tipo_cita');
}

public function diaEspera()
{
    return $this->belongsTo(WaitingDay::class, 'id_dia_espera');
}

public function area()
{
    return $this->belongsTo(Area::class, 'id_area');
}


}
