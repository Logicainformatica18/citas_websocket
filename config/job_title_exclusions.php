<?php

/*
|--------------------------------------------------------------------------
| Exclusión de ofertas por título (roles que no son de TI)
|--------------------------------------------------------------------------
| La lista de patrones vive en la tabla job_title_exclusion_keywords (se administra desde
| /job-offers/title-exclusions). Aquí solo hay valores fijos:
|
|   ti_patterns / ti_acronyms  PATRONES_TI y PATRON_SIGLAS_TI de
|                              docs/produccion/herramientas/exportar_periodos_ti.py. NO deciden
|                              qué se excluye: sirven para medir el riesgo de falso positivo (cuántos
|                              títulos que un patrón excluiría tienen además una palabra de TI) y como
|                              contexto para la IA.
|   categories                 categorías sugeridas en la interfaz (se puede escribir otra).
|   ai_model                   el mismo modelo que "Detectar nuevos lenguajes con IA".
|   slice_seconds              tiempo de trabajo de cada Job antes de encadenar el siguiente (el worker
|                              corre con --timeout=170 y la cola database tiene retry_after = 90).
*/

return [

    'ti_patterns' => [
        // desarrollo
        'developer', '\bdev\b', 'desarrollador', 'desarrollo (de )?software',
        'desarrollo web', 'programad', 'programmer', 'software',
        'desenvolvedor', 'developpeur', 'entwickler', 'sviluppat',
        'full ?stack', 'front ?end', 'back ?end', '\bweb\b',
        '\bjava\b', 'python', 'javascript', 'typescript', '\breact',
        'angular', '\bnode', '\.net\b', '\bphp\b', '\bruby\b', 'golang',
        'c\+\+', 'c#', 'kotlin', '\bswift\b', '\bios\b', 'android',
        '\bmobile\b', 'embedded', 'firmware', 'linux', 'unix', 'kernel',
        // datos e inteligencia artificial
        '\bdata\b', '\bdatos\b', '\bdados\b', 'donnees', 'big data',
        'machine learning', '\bml\b', '\bai\b', '\bia\b',
        'inteligencia artificial', 'artificial intelligence', 'deep learning',
        '\bnlp\b', 'computer vision', '\bllm', '\bbi\b',
        'business intelligence', 'power ?bi', 'analytics', '\betl\b',
        '\bdba\b', 'database', 'bases? de datos', '\bsql\b', '\bspark\b',
        'databricks', 'snowflake', 'hadoop',
        // infraestructura, nube y redes
        'devops', 'devsecops', '\bsre\b', 'site reliability', '\bcloud\b',
        '\baws\b', 'azure', '\bgcp\b', 'sysadmin', 'systems? admin',
        'administrador de (sistemas|redes)', '\bsistemas\b', 'network',
        '\bredes\b',
        // seguridad
        'ciberseguridad', 'cyber', 'seguridad (informatica|de la informacion)',
        'infosec', 'pentest', '\bsoc\b',
        // calidad y soporte
        '\bqa\b', 'tester', 'software test', 'test automation',
        'automatizacion de pruebas', 'help ?desk', 'service desk',
        'soporte (tecnico|ti|it)', 'it support', 'desktop support',
        // diseño, gestión y arquitectura
        '\bux\b', '\bui\b', 'product owner', 'scrum', 'agile coach',
        '(software|solutions?|cloud|enterprise|data|it) architect',
        'arquitecto de (software|soluciones|datos|nube)', 'tech lead',
        '\bcto\b', '\bcio\b',
        // informática en general
        'informati', 'computer', 'computacion', '\bsap\b', 'salesforce',
        'information technology', 'tecnologias? de (la )?informacion',
        '(gerente|jefe|director|head) (de |of )?tecnologia',
        'head of technology',
    ],

    // Solo en MAYÚSCULAS y sobre el título original (para no confundir el inglés "it" con "IT")
    'ti_acronyms' => '\b(IT|TI|TIC|TICs)\b',

    'categories' => [
        'ventas', 'marketing', 'recursos humanos', 'contabilidad', 'atención al cliente', 'digitación',
        'finanzas', 'legal', 'administración', 'salud', 'educación', 'transporte y logística',
        'operarios y oficios', 'comercio y servicios', 'otras ingenierías', 'otros',
    ],

    'ai_model' => 'gpt-4.1-mini',

    'slice_seconds' => 60,
];
