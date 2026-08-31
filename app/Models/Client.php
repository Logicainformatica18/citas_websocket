<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Token anónimo del encuestado.
 *
 * La tabla `clients` solo tiene id, completed_at y timestamps: no guarda
 * nombre ni correo. Su única función es agrupar las respuestas de una
 * misma persona sin identificarla.
 */
class Client extends Model
{
    protected $table = 'clients';

    protected $fillable = [
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function surveyClients()
    {
        return $this->hasMany(SurveyClient::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
