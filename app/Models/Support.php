<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Client;
use App\Models\User;
use App\Models\SupportDetail;

class Support extends Model
{
    use HasFactory;

    protected $table = 'supports';

    protected $fillable = [
        'client_id',
        'created_by',
        'cellphone',
        'state',
        'status_global',
    ];

    // Relaciones principales
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id_cliente');
    }

    // Nueva relación: uno a muchos con detalles
    public function details()
    {
        return $this->hasMany(SupportDetail::class);
    }

    // Si quisieras solo el último detalle
    public function latestDetail()
    {
        return $this->hasOne(SupportDetail::class)->latestOfMany();
    }
}
