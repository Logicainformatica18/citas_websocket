<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Sale extends Model
{
    protected $table = 'sales';

    protected $fillable = [
        'id_cliente',
        'project_id',
        'code',
        'holder',
        'stage',
        'mz_lote',
        'state',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_cliente', 'id_cliente');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id_proyecto');
    }
}
