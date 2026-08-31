<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `reports`: id, client_id, description, detail, timestamps.
 * FK reports.client_id -> clients.id (ON DELETE CASCADE).
 */
class Report extends Model
{
    protected $table = 'reports';

    protected $fillable = [
        'client_id', 'description', 'detail',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
