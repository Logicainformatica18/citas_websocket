<?php

/*
|--------------------------------------------------------------------------
| Catálogo de tecnologías, lenguajes, metodologías y categorías
|--------------------------------------------------------------------------
|
| Lo usa la extracción de sílabos (PDF subidos y silabos_v2) para no fragmentar
| el catálogo con variantes del mismo nombre.
|
| Orden de búsqueda de cada nombre que devuelve la IA:
|   1. alias de esta lista → nombre canónico (si el canónico no existe, se crea con esa
|      escritura). Va primero para poder redirigir nombres que ya existen duplicados;
|   2. nombre normalizado igual a uno existente (App\Support\CatalogName::key):
|      "IOT" = "IoT", "Bigdata" = "Big Data";
|   3. si no hay nada, se crea con el nombre que devolvió la IA (se registra en
|      structured_data['catalogo'] y en storage/logs/catalogo.log; ver `php artisan catalogo:nuevas`).
| Las categorías no se crean: una que no existe se asigna a 'other'.
|
| Convención de nombres canónicos: la de market_entities, de donde sale la alineación con
| el mercado: nombre oficial del producto o estándar, casi siempre en inglés y con su
| escritura oficial ("Power BI", "Node.js", "React.js", "Scrum"). Hay excepciones en
| español que ya existen y se respetan ("Cascada", "Bases de datos relacionales",
| "Modelos de aprendizaje profundo", "Inteligencia Artificial").
|
| Las claves van normalizadas (minúsculas, sin tildes, sin espacios ni signos salvo + y #).
| No se usa coincidencia por parecido: "Machine Learning" no es "Azure Machine Learning".
|
*/

return [

    // Nombres del catálogo que se pasan a la IA en el prompt de extracción.
    'prompt' => [
        // 0 = todas las tecnologías. Con un límite se pasan las más usadas en cursos y
        // las ligadas a market_entities primero.
        'max_tecnologias' => (int) env('CATALOGO_PROMPT_MAX_TECNOLOGIAS', 0),
        // Segundos que un worker reutiliza la lista antes de volver a leerla de la base.
        'cache_segundos' => (int) env('CATALOGO_PROMPT_CACHE_SEGUNDOS', 300),
    ],

    'alias' => [

        'technologies' => [
            // Existen en el catálogo (y en market_entities salvo Inteligencia Artificial).
            'artificialintelligence' => 'Inteligencia Artificial',
            'ia' => 'Inteligencia Artificial',
            'ai' => 'Inteligencia Artificial',
            'cloudcomputing' => 'Cloud',
            'computacionenlanube' => 'Cloud',
            'deeplearning' => 'Modelos de aprendizaje profundo',
            'aprendizajeprofundo' => 'Modelos de aprendizaje profundo',
            'relationaldatabases' => 'Bases de datos relacionales',
            'basesdedatosrelacional' => 'Bases de datos relacionales',
            'rdbms' => 'Bases de datos relacionales',
            'aws' => 'Amazon Web Service',
            'amazonwebservices' => 'Amazon Web Service',
            'gcp' => 'Google Cloud Platform',
            'googlecloud' => 'Google Cloud Platform',
            'react' => 'React.js',
            'node' => 'Node.js',
            'vscode' => 'Visual Studio Code',
            'microsoftexcel' => 'Excel',
            'restapi' => 'REST',
            'apirest' => 'REST',
            'restful' => 'REST',
            'restfulapi' => 'REST',

            // No existen todavía (ni en market_entities): fijan la escritura con la que se crean.
            'bigdata' => 'Big Data',
            'iot' => 'IoT',
            'internetofthings' => 'IoT',
            'internetdelascosas' => 'IoT',
            'machinelearning' => 'Machine Learning',
            'aprendizajeautomatico' => 'Machine Learning',
            'rpa' => 'RPA',
            'roboticprocessautomation' => 'RPA',
            'robotizaciondeprocesos' => 'RPA',
            'automatizacionroboticadeprocesos' => 'RPA',
            'nlp' => 'NLP',
            'naturallanguageprocessing' => 'NLP',
            'procesamientodelenguajenatural' => 'NLP',
            'datascience' => 'Data Science',
            'cienciadedatos' => 'Data Science',
            'cadenadebloques' => 'Blockchain',
        ],

        'languages' => [
            'js' => 'JavaScript',
            'ts' => 'TypeScript',
            'html5' => 'HTML',
            'css3' => 'CSS',
            'csharp' => 'C#',
            'cplusplus' => 'C++',
            'cpp' => 'C++',
            'golang' => 'Go',
        ],

        'methodologies' => [
            'agil' => 'Agile',
            'metodologiaagil' => 'Agile',
            'metodologiasagiles' => 'Agile',
            'agilidad' => 'Agile',
            'waterfall' => 'Cascada',
            'modeloencascada' => 'Cascada',
            'extremeprogramming' => 'XP',
            'programacionextrema' => 'XP',
            'rationalunifiedprocess' => 'RUP',
            'procesounificadoracional' => 'RUP',
            'procesounificado' => 'RUP',
            'scaledagileframework' => 'SAFe',
            'pensamientodediseno' => 'Design Thinking',
        ],

        // Categorías de tecnología (technology_categories). Incluye las dos duplicadas que
        // existen en la base, sin fusionarlas: los sílabos nuevos usan solo la canónica.
        'categories' => [
            'metodology' => 'methodology',
            'programminglanguage' => 'language',
            'lenguaje' => 'language',
            'lenguajedeprogramacion' => 'language',
            'herramienta' => 'tool',
            'nube' => 'cloud',
            'basededatos' => 'database',
            'databases' => 'database',
            'frameworks' => 'framework',
            'libreria' => 'library',
            'biblioteca' => 'library',
            'modelo' => 'model',
            'concepto' => 'concept',
            'plataforma' => 'platform',
            'motor' => 'engine',
            'sistemaoperativo' => 'operating system',
            'os' => 'operating system',
            'algoritmo' => 'algorithm',
            'estandar' => 'standard',
            'protocolo' => 'protocol',
            'servicio' => 'service',
            'seguridad' => 'security',
            'metodologia' => 'methodology',
        ],
    ],
];
