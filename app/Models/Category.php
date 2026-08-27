<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `categories` (catálogo) · REBANADA 2 del port de Encuestas.
 *
 * Columnas reales según el schema:
 *   id          bigint unsigned  PK
 *   description varchar(255)     NOT NULL
 *   detail      varchar(255)     NULL
 *   created_at  timestamp        NULL
 *   updated_at  timestamp        NULL
 */
class Category extends Model
{
    /**
     * CategoryController asigna propiedad por propiedad, igual que
     * TypeController, así que hoy esto no se usa. Queda declarado por si
     * en algún punto se pasa a create()/fill().
     */
    protected $fillable = [
        'description',
        'detail',
    ];
}
