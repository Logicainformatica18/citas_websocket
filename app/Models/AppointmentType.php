<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentType extends Model
{
    protected $table = 'tipos_cita';
    protected $primaryKey = 'id_tipo_cita';
    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];
}
