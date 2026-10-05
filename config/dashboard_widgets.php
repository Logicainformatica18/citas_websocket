<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sesión MySQL para ejecutar el SQL de los widgets
    |--------------------------------------------------------------------------
    |
    | Los SQL de ranking (COUNT DISTINCT, UNION) arman tablas temporales de
    | millones de filas. Si superan el límite, MySQL las baja a disco y la
    | consulta tarda el doble o más. Con el motor TempTable (MySQL 8) el límite
    | es tmp_table_size; con el motor MEMORY rige el menor de los dos valores.
    | Por eso ambos se ajustan juntos.
    |
    | Valores en bytes. Con 0 o vacío no se modifica la variable y rige la del
    | servidor.
    |
    | 128 MB es el menor valor con el que el widget más pesado («top de carreras
    | más demandadas») no baja tablas a disco. Medición en local (mediana):
    | 4,3 s sin ajuste (servidor con 59 MB), 4,4 s con 64 MB, 2,0 s con 128 MB y
    | 2,0 s con 256 MB; con 3 ejecuciones simultáneas, 8,4 s, 8,5 s, 3,6 s y
    | 4,0 s. Es un límite, no una reserva: cada sesión usa solo lo que su
    | consulta necesita, y el total lo acota temptable_max_ram (1 GB por omisión).
    |
    */

    'sql_session' => [
        'tmp_table_size'      => (int) env('WIDGET_SQL_TMP_TABLE_SIZE', 134217728),
        'max_heap_table_size' => (int) env('WIDGET_SQL_MAX_HEAP_TABLE_SIZE', 134217728),
    ],

];
