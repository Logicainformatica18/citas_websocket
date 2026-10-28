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

    // Dónde se ordenan los periodos sin fecha (TBD, TBD_EE) al desempatar
    // sílabos de la misma versión: 'last' (más reciente que cualquier periodo) o 'first'.
    // PENDIENTE DE CONFIRMAR por el equipo; 'last' es provisional.
    'tbd_position' => env('SILABOS_TBD_POSITION', 'last'),

    // Lanzar el análisis con IA del curso (AnalyzeCourseJob) tras cada ingesta.
    // Usa OpenAI (gpt-4o-mini y gpt-5 con búsqueda web), por eso está apagado por defecto.
    'analyze_after_ingest' => (bool) env('SILABOS_ANALYZE_AFTER_INGEST', false),

    /*
    | Mapeo de carreras: "{modelo}:{codigo en silabos_v2}" => slug de careers en el observatorio.
    | Solo están activas las coincidencias exactas por nombre normalizado. Los candidatos
    | comentados hay que revisarlos uno por uno (listado completo en docs/mapeo-carreras-silabos.md).
    | Una carrera sin mapeo no se vincula: el sílabo se ingiere igual y se registra en el log de revisión.
    */
    'career_map' => [
        'isil:CADT' => 'arquitectura-de-datos',   // ARQUITECTURA DE DATOS
        'isil:CSEG' => 'ciberseguridad',          // CIBERSEGURIDAD

        // Candidatos por similitud, NO activos (revisar):
        // 'isil:CNUB' => 'computacion-en-la-nube',     // COMPUTACIÓN EN NUBE (93 %)
        // 'isil:CINS' => 'sistemas-de-informacion',    // DESARROLLO DE SISTEMAS DE INFORMACIÓN (77 %)
        // 'isil:BISI' => 'sistemas-de-informacion',    // INGENIERÍA DE SISTEMAS DE INFORMACIÓN (77 %)
        // 'isil:BTIN' => 'tecnologias-de-informacion', // DIRECCION DE TECNOLOGIAS DE LA INFORMACIÓN (76 %)
        // 'isil:CINR' => 'redes-y-comunicaciones',     // ADMINISTRACIÓN DE REDES Y COMUNICACIONES (71 %)
    ],
];
