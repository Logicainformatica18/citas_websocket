<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Clave normalizada de un nombre del catálogo (tecnologías, lenguajes, metodologías, categorías):
 * sin tildes, en minúsculas y sin espacios, guiones, puntos ni otros signos, salvo + y #
 * para no confundir C++ con C#. Así "IOT", "IoT" e "I.o.T" son la misma clave, y "Bigdata"
 * y "Big Data" también. Las claves de config/catalogo.php (alias) se escriben con esta forma.
 */
class CatalogName
{
    public static function key(?string $name): string
    {
        return preg_replace('/[^a-z0-9+#]/', '', Str::lower(Str::ascii((string) $name)));
    }

    /** Recorta y colapsa espacios; es el nombre con el que se crea una entidad nueva. */
    public static function clean(?string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }
}
