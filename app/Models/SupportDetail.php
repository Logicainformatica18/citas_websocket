<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Client;
use App\Models\Area;
use App\Models\User;
use App\Models\Motive;
use App\Models\AppointmentType;
use App\Models\WaitingDay;
use App\Models\InternalState;
use App\Models\ExternalState;
use App\Models\Type;

class SupportDetail extends Model
{
    use HasFactory;

  protected $table = 'support_details';


   protected $fillable = [
    'support_id',
    'project_id',
    'area_id',
    'id_motivos_cita',
    'id_tipo_cita',
    'id_dia_espera',
    'internal_state_id',
    'external_state_id',
    'type_id',

    'subject',
    'description',
    'priority',
    'type',
    'status',
    'attachment',
    'reservation_time',
    'attended_at',
    'derived',

    'Manzana',
    'comment',
    'ticket',

    'attended_start',
    'attended_end',
    'ticket_start',
    'ticket_end',
    'channel',
    
];


    // Relaciones existentes
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id', 'id_area');
    }

    // public function creator()
    // {
    //     return $this->belongsTo(User::class, 'created_by');
    // }

    // public function client()
    // {
    //     return $this->belongsTo(Client::class, 'client_id', 'id_cliente');
    // }

    // Nuevas relaciones
    public function motivoCita()
    {
        return $this->belongsTo(Motive::class, 'id_motivos_cita', 'id_motivos_cita');
    }

    public function tipoCita()
    {
        return $this->belongsTo(AppointmentType::class, 'id_tipo_cita', 'id_tipo_cita');
    }

    public function diaEspera()
    {
        return $this->belongsTo(WaitingDay::class, 'id_dia_espera', 'id_dias_espera');
    }

    public function internalState()
    {
        return $this->belongsTo(InternalState::class, 'internal_state_id');
    }

public function externalState()
{
    return $this->belongsTo( ExternalState::class, 'external_state_id');
}



    public function supportType()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id_proyecto');
    }
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }
// app/Models/SupportDetail.php
public function support()
{
    return $this->belongsTo(Support::class);
}
public function lastComment()
{
    return $this->hasOne(Comment::class, 'support_detail_id')->latestOfMany()->with('internalState');
}

}
