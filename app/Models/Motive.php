<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// Si creaste el Pivot Model:
// use App\Models\MotivoCitaArea;

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
    'detail',
    'detail_2',   // ← nuevo
    'id_tipo_cita',
    'id_dia_espera',
    'id_area',
    'id_areap',
    'habilitado',
];


    protected $casts = [
        'habilitado' => 'boolean', // bit(1) -> boolean
    ];

    /* -------------------- BelongsTo (existentes) -------------------- */

    public function tipoCita(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'id_tipo_cita');
    }

    public function diaEspera(): BelongsTo
    {
        return $this->belongsTo(WaitingDay::class, 'id_dia_espera');
    }

    /** Área “principal” almacenada en la columna id_area (relación 1-a-1/Many-to-One) */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'id_area', 'id_area');
    }

    /* -------------------- BelongsToMany (nueva) -------------------- */

    /**
     * Áreas relacionadas vía tabla pivote motivos_cita_area (muchos-a-muchos).
     * Claves personalizadas:
     *  - pivote: motivos_cita_area (id, area_id, id_motivos_cita, timestamps)
     *  - PK local (motivos_cita): id_motivos_cita
     *  - PK Area: id_area
     */
    public function areas(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            Area::class,             // Modelo relacionado
            'motivos_cita_area',     // Tabla pivote
            'id_motivos_cita',       // FK de este modelo en la pivote
            'area_id',               // FK del otro modelo en la pivote
            'id_motivos_cita',       // PK local
            'id_area'                // PK del relacionado
        )
        // ->using(MotivoCitaArea::class) // Descomenta si creaste el Pivot Model
        ->withTimestamps();

        return $relation;
    }

    /* -------------------- Scopes útiles (opcionales) -------------------- */

    public function scopeHabilitados($query)
    {
        return $query->where('habilitado', true);
    }

    public function scopePorArea($query, int $areaId)
    {
        // Filtra por el área “principal” (columna id_area)
        return $query->where('id_area', $areaId);
    }

    public function scopeConAreaPivot($query, int $areaId)
    {
        // Filtra por la relación muchos-a-muchos (pivote)
        return $query->whereHas('areas', fn($q) => $q->where('areas.id_area', $areaId));
    }
}
