<?php

namespace App\Support;

/**
 * Extrae la sección de funciones del puesto de una descripción completa (HTML o texto).
 *
 * Prioriza precisión sobre cobertura: solo reconoce encabezados de una lista estricta
 * ("Responsibilities", "What you'll do", "Funciones", "Responsabilidades"…) que ocupen
 * su propia línea, y devuelve null ante cualquier duda. Encabezados ambiguos como
 * "The role", "Your impact" o "Role overview" NO cuentan: suelen introducir prosa
 * sobre el puesto, no una lista de funciones.
 *
 * La sección termina en el siguiente encabezado conocido (requisitos, beneficios,
 * sobre nosotros…) o cuando, tras una lista, vuelve la prosa.
 */
final class FunctionsSection
{
    /** Encabezados que abren la sección (normalizados: minúsculas, apóstrofo recto, sin ":" final). */
    private const HEADINGS = [
        // EN
        'responsibilities', 'key responsibilities', 'main responsibilities', 'primary responsibilities',
        'core responsibilities', 'your responsibilities', 'job responsibilities', 'role responsibilities',
        'roles and responsibilities', 'role and responsibilities', 'duties and responsibilities',
        'responsibilities and duties', 'responsibilities include', 'your key responsibilities',
        'what you\'ll do', 'what you will do', 'what you\'ll be doing', 'what you will be doing',
        'what you\'ll work on', 'what you will work on', 'here\'s what you\'ll do', 'what will you do',
        'in this role you will', 'in this role, you will', 'in this role you\'ll', 'in this role, you\'ll',
        'as a member of the team you will', 'your tasks', 'key duties', 'duties', 'major duties', 'main duties',
        'key tasks', 'main tasks',
        // ES
        'funciones', 'funciones principales', 'principales funciones', 'funciones del cargo', 'funciones del puesto',
        'funciones y responsabilidades', 'tus funciones', 'sus funciones', 'responsabilidades',
        'responsabilidades principales', 'principales responsabilidades', 'tus responsabilidades',
        'responsabilidades del cargo', 'responsabilidades del puesto', 'qué harás', 'que harás', 'tareas',
        'tus tareas', 'tareas principales', 'principales tareas', 'actividades principales', 'principales actividades',
        // DE
        'aufgaben', 'deine aufgaben', 'ihre aufgaben', 'deine hauptaufgaben', 'ihre hauptaufgaben',
    ];

    /** Sufijos permitidos tras un encabezado: "What you'll do as a Customer Engineer", "Responsibilities in this role". */
    private const HEADING_SUFFIX = '/^(?:\s+(?:as|in|at|on|for|within|en|como)\s.+|\s*\(.+\))$/u';

    /** Palabras que, en una línea con forma de encabezado, cierran la sección. */
    private const STOP = '/\b(?:'
        . 'requirements?|qualifications?|who you are|about you|you have|you\'ll bring|you will bring|what you bring|'
        . 'what we\'re looking for|what we are looking for|we\'re looking for|looking for|skills|experience|'
        . 'nice to have|bonus|preferred|benefits?|perks|we offer|what we offer|offer|compensation|salary|pay|'
        . 'about us|about the company|about the team|who we are|why join|why you\'ll love|our team|apply|'
        . 'location|locations|equal opportunity|diversity|culture|'
        . 'should have|you should|you\'ll need|you will need|what you need|look for|you must|must have|must bring|'
        . 'be successful|success looks like|interview|additional information|important notes|equity|time off|'
        . 'overview|advantage|special|how we work|'
        . 'requisitos|requerimientos|perfil|beneficios|ofrecemos|deseables?|conocimientos|competencias|formación|'
        . 'experiencia|condiciones|sobre nosotros|quiénes somos|nosotros|postula|'
        . 'profil|wir bieten|qualifikation\w*|über uns|benefits'
        . ')/iu';   // sin \b final: también plurales y derivados ("Must Haves", "Requirements")

    /**
     * Encabezados que hablan del candidato, de la empresa o hacen una pregunta ("Why Discord?",
     * "How GitLab supports you", "#LI-Remote"): una vez que la sección tiene contenido, la cierran.
     */
    private const OTHER_SECTION = '/(?:\b(?:you|your|you\'re|you\'ll|we|we\'re|our|us|why|how|tu|tus|te|usted|nosotros|nuestro|nuestra|nuestros|por qué|cómo|du|dich|dein|deine|wir|uns|unser|unsere|sie|ihr|ihre)\b|\?$|^#)/iu';

    /** Líneas de metadatos "Etiqueta: valor" que a veces siguen al encabezado; se descartan. */
    private const META = '/^(?:location|locations|reports to|reporting to|direct reports|department|team|employment type|job type|'
        . 'type|salary|compensation|start date|travel|work schedule|schedule|ubicación|ubicacion|lugar de trabajo|horario|'
        . 'modalidad|jornada|salario|sueldo|reporta a|standort|arbeitszeit)\s*:/iu';

    private const BULLET ='/^(?:[-•·*–—▪◦●✓✔✅]\s*|\d{1,2}[.)]\s+)/u';

    private const MAX_HEADING_LEN = 70;
    private const MAX_LINES       = 40;
    private const MAX_CHARS       = 6000;

    /**
     * @return array{headline: string, text: string}|null
     */
    public static function extract(?string $description): ?array
    {
        $plain = HtmlText::toPlain(JobFunctions::asHtml($description));
        if ($plain === null) {
            return null;
        }

        $lines = explode("\n", $plain);
        $count = count($lines);
        $firstProse = null;

        // Si hay varias secciones candidatas ("What you'll do" en prosa y luego "Responsibilities"
        // con lista), se prefiere la primera con lista; la prosa queda como alternativa.
        for ($i = 0; $i < $count; $i++) {
            if (! self::isFunctionsHeading($lines[$i])) {
                continue;
            }

            $section = self::collect($lines, $i + 1);
            if ($section === null) {
                continue;
            }

            $found = ['headline' => self::cleanHeading($lines[$i]), 'text' => $section['text']];

            if ($section['bullets']) {
                return $found;
            }

            $firstProse ??= $found;
        }

        return $firstProse;
    }

    /**
     * Recoge las líneas de la sección a partir de $start; null si no es confiable.
     *
     * @return array{text: string, bullets: bool}|null
     */
    private static function collect(array $lines, int $start): ?array
    {
        $out = [];
        $hasBullets = false;
        $closed = false;

        for ($j = $start, $n = count($lines); $j < $n; $j++) {
            $line = $lines[$j];

            if (self::isHeadingLike($line)) {
                $norm = str_replace(['’', '‘'], "'", $line);

                // Un segundo encabezado de funciones justo después del primero ("What you'll do" → "In this role, you will:") es parte del título.
                if ($out === [] && self::isFunctionsHeading($line)) {
                    continue;
                }

                if (preg_match(self::STOP, $norm) || self::isFunctionsHeading($line)
                    || ($out !== [] && preg_match(self::OTHER_SECTION, $norm))) {
                    $closed = true;
                    break;
                }
            }

            // Tras una lista, una línea larga de prosa marca el fin de la sección.
            if ($hasBullets && ! self::isBullet($line) && mb_strlen($line) > 60) {
                $closed = true;
                break;
            }

            if (preg_match(self::META, $line)) {
                continue;
            }

            $out[] = $line;
            $hasBullets = $hasBullets || self::isBullet($line);
        }

        // Subtítulos sueltos al final (sin contenido después) no son funciones.
        while ($out !== [] && self::isHeadingLike(end($out))) {
            array_pop($out);
        }

        if ($out === [] || (! $hasBullets && ! $closed)) {
            return null;
        }

        $text = implode("\n", $out);

        if (count($out) > self::MAX_LINES || mb_strlen($text) > self::MAX_CHARS) {
            return null;
        }

        return ['text' => $text, 'bullets' => $hasBullets];
    }

    private static function isFunctionsHeading(string $line): bool
    {
        if (! self::isHeadingLike($line)) {
            return false;
        }

        $norm = self::normalize($line);

        foreach (self::HEADINGS as $heading) {
            if ($norm === $heading) {
                return true;
            }
            if (str_starts_with($norm, $heading) && preg_match(self::HEADING_SUFFIX, mb_substr($norm, mb_strlen($heading)))) {
                return true;
            }
        }

        return false;
    }

    /** Línea corta, propia, que no es ítem de lista ni termina como una oración. */
    private static function isHeadingLike(string $line): bool
    {
        $line = trim($line);

        return $line !== ''
            && mb_strlen($line) <= self::MAX_HEADING_LEN
            && ! self::isBullet($line)
            && ! preg_match('/[.;,!]$/u', $line);
    }

    private static function isBullet(string $line): bool
    {
        return (bool) preg_match(self::BULLET, ltrim($line));
    }

    private static function normalize(string $line): string
    {
        $s = mb_strtolower(trim($line));
        $s = str_replace(['’', '‘', '`', '´'], "'", $s);
        $s = preg_replace('/^[\s#*¿¡【\[]+|[\s:：?*】\]]+$/u', '', $s) ?? $s;

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private static function cleanHeading(string $line): string
    {
        return trim(preg_replace('/^[\s#*【\[]+|[\s:：*】\]]+$/u', '', trim($line)) ?? $line);
    }
}
