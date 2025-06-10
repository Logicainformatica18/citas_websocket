<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'clientes';
    protected $primaryKey = 'id_cliente';
    public $timestamps = false; // 👈 Esto desactiva created_at y updated_at

    protected $fillable = [
        'id_slin',
        'Codigo',
        'Razon_Social',
        'T.Doc.',
        'DNI',
        'NIT',
        'Direccion',
        'Ubigeo',
        'Departamento',
        'Provincia',
        'Distrito',
        'Telefono',
        'Email',
        'clave',
        'c_clave',
        'ref_telefono1',
        'ref_telefono2',
        'comentario',
        'canal',
        'habilitado',
        'id_rol'
    ];

public function updateFromSupport(array $data)
{
    $updated = false;

    if (!empty($data['dni'])) {
        $this->DNI = $data['dni'];
        $updated = true;
    }

    if (!empty($data['cellphone'])) {
        $this->Telefono = $data['cellphone'];
        $updated = true;
    }

    if (!empty($data['email'])) {
        $this->Email = $data['email'];
        $updated = true;
    }

    if (!empty($data['address'])) {
        $this->Direccion = $data['address'];
        $updated = true;
    }

    if ($updated) {
        $this->save();
    }

    return $this;
}
public function toFrontend()
{
    return [
        'id' => $this->id_cliente,
        'names' => $this->Razon_Social,
        'dni' => $this->DNI,
        'cellphone' => $this->Telefono,
        'email' => $this->Email,
        'address' => $this->Direccion,
    ];
}


}
