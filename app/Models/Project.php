<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'proyecto'; // nombre real de la tabla en español

    protected $primaryKey = 'id_proyecto'; // clave primaria personalizada

    public $timestamps = false; // si tu tabla no tiene created_at / updated_at

    protected $fillable = [
        'descripcion',
        'habilitado',
    ];
}
