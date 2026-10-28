<?php

/*
|--------------------------------------------------------------------------
| Integración con silabos_v2
|--------------------------------------------------------------------------
| Sílabos aprobados que silabos_v2 envía a POST /api/silabos/ingest.
| Ver README → "Ingesta de sílabos desde silabos_v2".
*/

return [

    // Clave compartida que silabos_v2 manda en la cabecera X-Api-Key.
    // Si está vacía, el endpoint rechaza todas las peticiones.
    'ingest_key' => env('SILABOS_INGEST_API_KEY'),

    // Dónde se ordenan los periodos sin fecha (TBD, TBD_EE) al desempatar sílabos de la
    // misma versión: 'first' (anteriores a cualquier periodo, decisión del equipo) o 'last'.
    'tbd_position' => env('SILABOS_TBD_POSITION', 'first'),

    // Lanzar el análisis con IA del curso (AnalyzeCourseJob) tras cada ingesta.
    // Usa OpenAI (gpt-4o-mini y gpt-5 con búsqueda web), por eso está apagado por defecto.
    'analyze_after_ingest' => (bool) env('SILABOS_ANALYZE_AFTER_INGEST', false),

    /*
    | Mapeo de carreras: "{modelo}:{codigo en silabos_v2}" => slug de careers en el observatorio.
    | Solo las 9 carreras que usa el observatorio. Propuesta por nombre y por cursos en común
    | (cursos de la carrera en el observatorio que también son sílabos aprobados de la carrera
    | en silabos_v2); detalle en docs/mapeo-carreras-silabos.md.
    |
    | Es la única lista: silabos_v2 manda todos los sílabos con todas sus carreras.
    | Un sílabo sin ninguna carrera de esta lista se guarda como 'ignored' y no se procesa;
    | uno con varias se vincula solo a las de esta lista.
    */
    'career_map' => [
        'isil:CADT' => 'arquitectura-de-datos',                      // ARQUITECTURA DE DATOS (25/31 cursos)
        'isil:CSEG' => 'ciberseguridad',                             // CIBERSEGURIDAD (25/31)
        'isil:CCDT' => 'ciencia-de-datos-e-inteligencia-artificial', // CIENCIA DE DATOS (26/31)
        'isil:CNUB' => 'computacion-en-la-nube',                     // COMPUTACIÓN EN NUBE (28/31)
        'isil:CINR' => 'redes-y-comunicaciones',                     // ADMINISTRACIÓN DE REDES Y COMUNICACIONES (23/34)
        'isil:BISI' => 'ingenieria-de-sistemas',                     // INGENIERÍA DE SISTEMAS DE INFORMACIÓN (38/48)
        'isil:BTIN' => 'tecnologias-de-informacion',                 // DIRECCION DE TECNOLOGIAS DE LA INFORMACIÓN (43/53)

        // PENDIENTES DE CONFIRMAR: el nombre no coincide y la evidencia por cursos no es concluyente.
        // 'isil:CINS' => 'desarrollo-de-software',   // DESARROLLO DE SISTEMAS DE INFORMACIÓN (23/34; siguiente BTIN 19/34)
        // 'isil:CISI' => 'sistemas-de-informacion',  // ARQUITECTURA DE PLATAFORMAS Y SERVICIOS DE TI (25/34; siguiente BTIN 24/34)
    ],
];
