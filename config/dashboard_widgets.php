<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sesión MySQL para ejecutar el SQL de los widgets
    |--------------------------------------------------------------------------
    |
    | Los SQL de ranking (COUNT DISTINCT, UNION) arman tablas temporales de
    | millones de filas. Si superan el límite, MySQL las baja a disco y la
    | consulta pasa de segundos a minutos. MySQL usa el MENOR de los dos valores,
    | por lo que ambos deben ajustarse juntos.
    |
    | Valores en bytes. Con 0 o vacío no se modifica la variable y rige la del
    | servidor.
    |
    */

    'sql_session' => [
        'tmp_table_size'      => (int) env('WIDGET_SQL_TMP_TABLE_SIZE', 268435456),
        'max_heap_table_size' => (int) env('WIDGET_SQL_MAX_HEAP_TABLE_SIZE', 268435456),
    ],

];
