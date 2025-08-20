<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Area extends Model
{
    protected $table = 'areas';
    protected $primaryKey = 'id_area';
    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];

    /**
     * Motivos donde esta área es la “principal” (columna id_area de motivos_cita).
     * Relación 1:N
     */
    public function motivosPrincipales()
    {
        return $this->hasMany(Motive::class, 'id_area', 'id_area');
    }

    /**
     * Motivos relacionados vía pivote (muchos-a-muchos) en motivos_cita_area.
     * - pivote: motivos_cita_area (id, area_id, id_motivos_cita, timestamps)
     * - PK Area:   id_area
     * - PK Motive: id_motivos_cita
     */
   public function motivos(): BelongsToMany
{
    return $this->belongsToMany(
            Motive::class,
            'motivos_cita_area',
            'area_id',
            'id_motivos_cita',
            'id_area',
            'id_motivos_cita'
        )
        ->withTimestamps()
        ->withPivot(['id'])               // si te sirve el id de la pivote
        ->orderBy('motivos_cita.nombre_motivo'); // UX estable
}


    /* Scopes útiles (opcionales) */
    public function scopeHabilitadas($query)
    {
        return $query->where('habilitado', true);
    }
}
