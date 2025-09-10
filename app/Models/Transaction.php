<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_1',
        'status',
    ];

    /**
     * Relación con bloques (imagenes recortadas).
     */
    public function blocks()
    {
        return $this->hasMany(TransactionBlock::class);
    }

    /**
     * Relación con líneas (movimientos finales).
     */
    public function lines()
    {
        return $this->hasMany(TransactionLine::class);
    }
}
