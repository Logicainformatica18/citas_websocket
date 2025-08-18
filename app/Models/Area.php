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
                Motive::class,          // modelo relacionado
                'motivos_cita_area',    // tabla pivote
                'area_id',              // FK de este modelo en la pivote
                'id_motivos_cita',      // FK del otro modelo en la pivote
                'id_area',              // PK local
                'id_motivos_cita'       // PK del relacionado
            )
            // ->using(MotivoCitaArea::class) // si definiste el Pivot Model
            ->withTimestamps();
    }

    /* Scopes útiles (opcionales) */
    public function scopeHabilitadas($query)
    {
        return $query->where('habilitado', true);
    }
}
