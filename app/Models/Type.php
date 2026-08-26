<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `types` (catálogo) · REBANADA 1 del port de Encuestas.
 *
 * Columnas reales según el dump:
 *   id          bigint unsigned  PK
 *   description varchar(255)     NOT NULL
 *   detail      varchar(255)     NULL
 *   created_at  timestamp        NULL
 *   updated_at  timestamp        NULL
 *
 * OJO: este archivo reemplaza al Type.php que existía en el proyecto base
 * (el que se borró con borrar-modelos.bat). Mismo nombre de clase,
 * contenido distinto: aquel era un huérfano sin tabla en la BD.
 *
 * ALCANCE: en el schema, `types` solo es referenciada por `courses.type_id`,
 * y `courses` quedó fuera de las 9 tablas del port. Por eso no hay
 * relaciones todavía. Cuando portes el módulo académico:
 *
 *     public function courses()
 *     {
 *         return $this->hasMany(Course::class);
 *     }
 *
 * Mientras esa FK no exista, destroy() no puede fallar por integridad
 * referencial. Cuando exista, borrar un tipo en uso va a lanzar
 * QueryException — la FK no tiene ON DELETE — y habrá que decidir si se
 * bloquea antes o se captura.
 */
class Type extends Model
{
    /**
     * TypeController asigna propiedad por propiedad, igual que
     * UserController, así que hoy esto no se usa. Queda declarado por si
     * en algún punto se pasa a create()/fill().
     */
    protected $fillable = [
        'description',
        'detail',
    ];
}
