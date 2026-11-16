<?php

/*
|--------------------------------------------------------------------------
| Reglas del matcher para las entidades de tipo 'language' (por slug)
|--------------------------------------------------------------------------
| Fuente única de market_entity_aliases para lenguajes:
|   - tests/Unit/EntityTextMatcherTest.php las usa tal cual;
|   - docs/produccion/herramientas/generar_aliases_sql.php genera con ellas los INSERT del
|     script 11 para el DBA.
| Cada regla: [kind, pattern, opciones]. kind: alias | negative | context.
| Opciones: cs (distingue mayúsculas), weak (alias que necesita contexto), regex, window (caracteres).
| Ver App\Services\Matching\EntityTextMatcher para la semántica.
*/

$a = fn (string $p, array $o = []) => ['alias', $p, $o];
$n = fn (string $p, array $o = []) => ['negative', $p, $o];
$c = fn (string $p, array $o = []) => ['context', $p, $o];

// Contexto de programación genérico para alias débiles
$dev = ['developer', 'desarrollador', 'desarrolladora', 'engineer', 'ingeniero', 'ingeniera', 'programming',
    'programación', 'programmer', 'programador', 'backend', 'back-end', 'software', 'coding', 'language', 'lenguaje'];

return [

    'sql' => [$a('SQL')],
    'java' => [$a('Java'), $n('Java Script')],
    'javascript' => [$a('JavaScript'), $a('Java Script'), $a('ECMAScript'), $a('JS', ['cs' => true])],
    'typescript' => [$a('TypeScript')],
    'python' => [$a('Python')],
    'c' => [$a('C#'), $a('C Sharp'), $a('CSharp')],                       // slug histórico de C#
    'c-plus-plus' => [$a('C++'), $a('C plus plus'), $a('cpp')],
    'php' => [$a('PHP')],
    'css' => [$a('CSS'), $a('CSS3')],
    'html' => [$a('HTML'), $a('HTML5')],
    'kotlin' => [$a('Kotlin')],
    'matlab' => [$a('MATLAB')],
    'json' => [$a('JSON')],
    'xml' => [$a('XML')],
    // Scala: distingue mayúsculas ("scala" en italiano/neerlandés, "scala…" de "scalable" truncado);
    // "scala" en minúsculas solo con contexto cercano (spark / scala).
    'scala' => [$a('Scala', ['cs' => true]), $a('SCALA', ['cs' => true]), $a('scala', ['cs' => true, 'weak' => true]),
        ...array_map(fn ($p) => $c($p, ['window' => 40]), ['Spark', 'Java', 'Akka', 'Kotlin', 'Python', 'big data', 'Hadoop'])],
    'perl' => [$a('Perl')],
    'cobol' => [$a('COBOL')],
    'lua' => [$a('Lua', ['cs' => true])],
    'vba' => [$a('VBA', ['cs' => true]), $a('Visual Basic for Applications')],
    'fsharp' => [$a('F#', ['cs' => true]), $a('FSharp')],
    // Rust: distingue mayúsculas ("rust" óxido; en neerlandés, calma). "RUST" también es el lenguaje.
    'rust' => [$a('Rust', ['cs' => true]), $a('RUST', ['cs' => true]), $a('Rustlang'), $n('Rust Belt')],

    // R: una letra. Débil: necesita contexto de datos/estadística o de programación.
    'r' => [
        $a('R', ['cs' => true, 'weak' => true]),
        $a('RStudio'), $a('R programming'), $a('R language'), $a('lenguaje R'), $a('R Shiny'),
        $a('tidyverse'), $a('ggplot2'),
        $n('R+D'), $n('R$', ['cs' => true]), $n('(R)'), $n('R/3'), $n('d.R.'), $n('SAP R'),
        // R&D, R&R, R&I, R&T, B&R, D&R…; montos en rand (R 30,000) y códigos (R-00193023)
        $n('[A-Z]+&R|R&[A-Z]+', ['cs' => true, 'regex' => true]), $n('R ?-?\d+',['cs' => true, 'regex' => true]),
        ...array_map(fn ($p) => $c($p), ['statistics', 'statistical', 'estadística', 'estadístico', 'data', 'datos',
            'analytics', 'analítica', 'machine learning', 'Python', 'SAS', 'Stata', 'SPSS', 'SQL', 'MATLAB',
            'econometrics', 'econometría', 'biostatistics', 'Shiny', 'CRAN', 'programming', 'programación']),
    ],

    // Go: palabra común. Distingue mayúsculas, débil con contexto cercano; "Golang" es fuerte.
    'go' => [
        $a('Go', ['cs' => true, 'weak' => true]), $a('Golang'),
        ...array_map(fn ($p) => $n($p), ['Go to', 'Go-to-market', 'Go live', 'Go/No-Go', 'No Go', 'Go getter',
            "Let's Go", 'Go ahead', 'Go beyond', 'Go above', 'On the Go', 'Pokémon Go', 'Go big', 'Go far',
            'Go the extra mile', 'Go out', 'Go back', 'Go through', 'Go Intérim', 'Go deeper', 'Go Direct']),
        $n('[„"“\']Go[“"”\']', ['cs' => true, 'regex' => true]),   // „Go“ entre comillas: dar el visto bueno
        ...array_map(fn ($p) => $c($p, ['window' => 80]), [...$dev, 'microservices', 'microservicios', 'Python',
            'Java', 'Rust', 'Kubernetes', 'Docker', 'gRPC', 'Node.js', 'C++', 'Scala', 'Kotlin', 'TypeScript',
            'development', 'desarrollo', 'SRE', 'DevOps', 'React', 'API', 'APIs', 'cloud', 'AWS', 'GCP', 'Linux',
            'Fullstack', 'Full Stack', 'services', 'servicios', 'Développeur', 'Développeuse', 'Développement',
            'logiciel', 'iOS', 'Android', 'IA', 'AI']),
    ],

    // Swift: distingue mayúsculas (descarta SWIFT bancario en mayúsculas y "swift" adjetivo), débil.
    'swift' => [
        $a('Swift', ['cs' => true, 'weak' => true]), $a('SwiftUI'),
        $a('SWIFT', ['cs' => true, 'weak' => true]),   // "iOS NATIF – SWIFT"; el bancario se descarta abajo
        $n('SWIFT messaging'), $n('SWIFT network'), $n('SWIFT MT'), $n('SEPA', ['window' => 40]), $n('LS-SWIFT'),
        $n('SWIFT Emergency'), $n('BIC', ['window' => 20]), $n('Target2', ['window' => 60]),
        $n('Swift/BIC'), $n('Swift BIC'), $n('Swift transfer'), $n('Swift payments'),
        $n('Taylor Swift'), $n('MT103', ['window' => 80]),
        ...array_map(fn ($p) => $c($p, ['window' => 120]), ['iOS', 'Xcode', 'Objective-C', 'Apple', 'macOS',
            'UIKit', 'Kotlin', 'mobile', 'móvil', 'iPhone', 'iPad', ...$dev]),
    ],

    // DAX: también es el índice bursátil alemán.
    'dax' => [
        $a('DAX', ['cs' => true, 'weak' => true]),
        $n('DAX index'), $n('DAX 40'), $n('DAX 30'), $n('DAX companies'), $n('DAX-listed'), $n('DAX-Konzern'),
        $n('DAX-Konzerne'), $n('DAX-Unternehmen'),
        ...array_map(fn ($p) => $c($p, ['window' => 150]), ['Power BI', 'PowerBI', 'Power Pivot', 'SSAS', 'Tabular', 'Analysis Services',
            'Excel', 'Fabric', 'Power Query', 'M Query', 'Business Intelligence', 'BI', 'data modelling',
            'data modeling', 'data model']),
    ],

    // Ruby: también nombre propio.
    'ruby' => [
        $a('Ruby', ['cs' => true, 'weak' => true]), $a('Ruby on Rails'), $a('RoR', ['cs' => true]),
        ...array_map(fn ($p) => $c($p, ['window' => 120]), ['Rails', 'RSpec', 'Sinatra', 'gems', 'Python',
            'JavaScript', 'PHP', 'Elixir', ...$dev]),
    ],

    // Inactivos (se evalúan en la auditoría; ver PLAN_RANKINGS.md):
    // Shell pasará a "Shell scripting" con alias Bash y sh.
    'shell' => [
        $a('Shell scripting'), $a('Shell script'), $a('Shell scripts'), $a('Bash'), $a('Bash scripting'),
        $a('sh', ['cs' => true, 'weak' => true]), $a('Zsh'),
        $c('script', ['window' => 40]), $c('scripting', ['window' => 40]), $c('Linux', ['window' => 60]), $c('Unix', ['window' => 60]),
        $n('PowerShell'),
    ],
    // Ada: distingue mayúsculas (descarta ADA, Americans with Disabilities Act), débil.
    'ada' => [
        $a('Ada', ['cs' => true, 'weak' => true]), $a('Ada 95'), $a('Ada 2005'), $a('Ada 2012'), $a('Ada programming'),
        $n('Ada Lovelace'), $n('Ada County'),
        ...array_map(fn ($p) => $c($p, ['window' => 80]), ['SPARK', 'avionics', 'aviónica', 'embedded', 'embebido',
            'DO-178', 'safety-critical', 'real-time', 'C++', ...$dev]),
    ],

];
