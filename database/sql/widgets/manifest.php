<?php

/*
|--------------------------------------------------------------------------
| SQL optimizados de widgets
|--------------------------------------------------------------------------
|
| Lo usa el comando observatorio:optimizar-sql-widgets. Cada entrada ubica un
| widget por columnas de negocio, nunca por id, porque los id cambian entre
| entornos:
|
|   - dashboard_slug: dashboards.slug, que tiene índice único (uk_dashboards_slug).
|   - widget_title:   dashboard_widgets.title, que es la pregunta que originó el
|                     widget. No tiene índice único, por eso el comando verifica
|                     que dentro del dashboard exista exactamente un widget de
|                     tipo "chart" con ese título.
|
| El entrenamiento (sqltrainings) se ubica a partir del widget, no por la
| pregunta: query_text se repite en cada reintento de la IA.
|
| "archivo" es el prefijo de <archivo>.original.sql y <archivo>.optimizado.sql,
| ubicados en esta misma carpeta. El original sirve de huella: si el SQL del
| entorno no es ni el original ni el optimizado, el comando se detiene.
|
*/

return [

    'carreras-mas-demandadas' => [
        'dashboard_slug' => 'dashboard-principal',
        'widget_title'   => 'dame un top de carreras más demandadas',
        'archivo'        => 'carreras-mas-demandadas',
    ],

    'carreras-demandan-lenguajes' => [
        'dashboard_slug' => 'dashboard-principal',
        'widget_title'   => 'dame el top 5 de carreras que demandan lenguajes de programacion',
        'archivo'        => 'carreras-demandan-lenguajes',
    ],

];
