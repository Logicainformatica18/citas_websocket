<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas'; // nombre de la tabla
    protected $primaryKey = 'id_area'; // clave primaria personalizada
    public $timestamps = false; // no hay campos created_at ni updated_at

    protected $fillable = [
        'descripcion',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];

    // Relaciones con otros modelos (si aplica)
    // public function supports()
    // {
    //     return $this->hasMany(Support::class, 'area_id', 'id_area');
    // }
}
