<?php

namespace App\Services\JobOffers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decide si el TÍTULO de una oferta corresponde a un rol que no es de TI, con la lista de
 * job_title_exclusion_keywords. Mismo criterio que docs/produccion/herramientas/exportar_periodos_ti.py:
 * el título se compara en minúsculas y sin tildes (normalize()); los patrones de la semilla son sus
 * PATRONES_EXCLUIR.
 *
 *   - 'exclude'    si el título coincide, la oferta se excluye…
 *   - 'exception'  …salvo que coincida también con una excepción activa (la excepción gana).
 *   - match_mode 'text': literal (whole_word = palabra completa); 'regex': expresión PCRE.
 *   - \b y las clases de caracteres usan Unicode (*UCP), como el módulo re de Python.
 *
 * Rendimiento: se prueba primero una sola expresión con todas las alternativas; solo si coincide se
 * busca qué patrón fue (el 80-90 % de los títulos no coincide con ninguno).
 */
class TitleExclusionMatcher
{
    public const TABLE = 'job_title_exclusion_keywords';

    /** Recarga la lista en procesos largos (worker de colas) pasado este tiempo. */
    private const TTL_SECONDS = 60;

    private static ?self $shared = null;

    private static ?int $sharedAt = null;

    /** @var array<int, array{id:int, regex:string, type:string, category:?string, pattern:string}> */
    private array $excludes = [];

    /** @var array<int, array{id:int, regex:string, type:string, category:?string, pattern:string}> */
    private array $exceptions = [];

    private ?string $excludeAny = null;

    private ?string $exceptionAny = null;

    /** @param  iterable<object|array>  $keywords  filas de la tabla (solo se usan las que se pasan) */
    public function __construct(iterable $keywords)
    {
        foreach ($keywords as $k) {
            $k = (object) $k;
            $regex = self::compile((string) $k->pattern, (string) $k->match_mode, (bool) $k->whole_word);
            if ($regex === null) {
                continue;   // expresión inválida guardada a mano: se ignora (la interfaz no lo permite)
            }
            $entry = [
                'id' => (int) $k->id,
                'regex' => $regex,
                'type' => (string) $k->type,
                'category' => $k->category ?? null,
                'pattern' => (string) $k->pattern,
            ];
            if ($entry['type'] === 'exception') {
                $this->exceptions[$entry['id']] = $entry;
            } else {
                $this->excludes[$entry['id']] = $entry;
            }
        }
        ksort($this->excludes);   // atribución determinista: gana el patrón más antiguo
        ksort($this->exceptions);
        $this->excludeAny = self::union($this->excludes);
        $this->exceptionAny = self::union($this->exceptions);
    }

    /** Matcher con los patrones ACTIVOS (compartido en el proceso, se recarga cada 60 s). */
    public static function active(): self
    {
        if (self::$shared === null || self::$sharedAt < time() - self::TTL_SECONDS) {
            self::$shared = new self(self::tableExists()
                ? DB::table(self::TABLE)->where('is_active', 1)->orderBy('id')->get()
                : []);
            self::$sharedAt = time();
        }

        return self::$shared;
    }

    /** Matcher con TODOS los patrones (activos o no), para contar coincidencias. */
    public static function all(): self
    {
        return new self(self::tableExists() ? DB::table(self::TABLE)->orderBy('id')->get() : []);
    }

    public static function flush(): void
    {
        self::$shared = null;
    }

    public static function tableExists(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasTable(self::TABLE);
    }

    public function isEmpty(): bool
    {
        return $this->excludes === [];
    }

    /**
     * Patrón que excluye el título, o null si la oferta es válida.
     *
     * @return array{id:int, category:?string, pattern:string}|null
     */
    public function excludedBy(?string $title): ?array
    {
        if ($this->excludeAny === null || $title === null || $title === '') {
            return null;
        }
        $t = self::normalize($title);
        if (! preg_match($this->excludeAny, $t)) {
            return null;
        }
        if ($this->exceptionAny !== null && preg_match($this->exceptionAny, $t)) {
            return null;
        }
        foreach ($this->excludes as $e) {
            if (preg_match($e['regex'], $t)) {
                return ['id' => $e['id'], 'category' => $e['category'], 'pattern' => $e['pattern']];
            }
        }

        return null;
    }

    /** ¿Una excepción de esta lista protege el título? */
    public function isProtected(?string $title): bool
    {
        return $this->exceptionAny !== null && $title !== null && $title !== ''
            && preg_match($this->exceptionAny, self::normalize($title)) === 1;
    }

    /** Id del patrón que excluye el título (o null). */
    public function excludedId(?string $title): ?int
    {
        return $this->excludedBy($title)['id'] ?? null;
    }

    /**
     * Ids de TODOS los patrones (excluir y excepción) que coinciden con el título, sin aplicar
     * excepciones. Para contar coincidencias por patrón.
     *
     * @return array<int, int>
     */
    public function matchingIds(string $normalizedTitle): array
    {
        $ids = [];
        foreach ([[$this->excludeAny, $this->excludes], [$this->exceptionAny, $this->exceptions]] as [$any, $list]) {
            if ($any !== null && preg_match($any, $normalizedTitle)) {
                foreach ($list as $e) {
                    if (preg_match($e['regex'], $normalizedTitle)) {
                        $ids[] = $e['id'];
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Minúsculas y sin tildes, como normalizar() del script de Python
     * ('Analista Informático' → 'analista informatico').
     */
    public static function normalize(string $text): string
    {
        return mb_strtolower(self::stripAccents($text), 'UTF-8');
    }

    /**
     * Expresión PCRE lista para preg_match, o null si el patrón no es válido.
     * 'text' se normaliza igual que el título (así "Informática" y "informatica" son lo mismo).
     */
    public static function compile(string $pattern, string $matchMode, bool $wholeWord): ?string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return null;
        }
        // regex: se quitan las tildes del patrón (no toca los metacaracteres) y se compara sin
        // distinguir mayúsculas (flag i), así "Informática" o "\bSales\b" funcionan igual
        $body = $matchMode === 'regex'
            ? self::escapeDelimiter(self::stripAccents($pattern))
            : preg_quote(self::normalize($pattern), '~');
        if ($wholeWord) {
            $body = '\b(?:' . $body . ')\b';
        }
        $regex = '~(*UCP)' . $body . '~iu';

        return @preg_match($regex, '') === false ? null : $regex;
    }

    private static function stripAccents(string $text): string
    {
        if (! class_exists(\Normalizer::class)) {
            return $text;
        }
        $text = \Normalizer::normalize($text, \Normalizer::FORM_KD) ?: $text;

        return preg_replace('/\p{Mn}+/u', '', $text) ?? $text;
    }

    /** Mensaje de error si el patrón no compila (para validar el formulario). */
    public static function error(string $pattern, string $matchMode, bool $wholeWord): ?string
    {
        if (trim($pattern) === '') {
            return 'El patrón está vacío.';
        }
        $regex = self::compile($pattern, $matchMode, $wholeWord);
        if ($regex === null) {
            return 'La expresión regular no es válida: revisa paréntesis, corchetes y caracteres especiales.';
        }
        if (preg_match('/\\\\[1-9]|\\\\[gk]\{?[\w<\']/', $pattern)) {
            return 'No se admiten referencias a grupos (\\1, \\g, \\k): los patrones se combinan en una sola expresión.';
        }
        if (preg_match($regex, '') === 1) {
            return 'El patrón coincide con un texto vacío (excluiría todas las ofertas).';
        }

        return null;
    }

    /** ¿El título tiene alguna palabra de TI? (PATRONES_TI y PATRON_SIGLAS_TI del script). */
    public static function hasTiWord(string $title): bool
    {
        static $ti = null;
        static $acr = null;
        if ($ti === null) {
            $ti = '~(*UCP)(?:' . implode('|', array_map(fn ($p) => self::escapeDelimiter($p), config('job_title_exclusions.ti_patterns', []))) . ')~iu';
            $acr = '~' . self::escapeDelimiter((string) config('job_title_exclusions.ti_acronyms', '(?!)')) . '~u';
        }

        return preg_match($ti, self::normalize($title)) === 1 || preg_match($acr, $title) === 1;
    }

    private static function union(array $list): ?string
    {
        if ($list === []) {
            return null;
        }
        // Cada alternativa sin su delimitador, (*UCP) ni flags; se agrupan en una sola expresión
        $parts = array_map(fn ($e) => substr($e['regex'], strlen('~(*UCP)'), -strlen('~iu')), $list);

        return '~(*UCP)(?:' . implode('|', $parts) . ')~iu';
    }

    /** Escapa el delimitador ~ que no esté ya escapado. */
    private static function escapeDelimiter(string $body): string
    {
        return preg_replace('/(?<!\\\\)((?:\\\\\\\\)*)~/', '$1\~', $body);
    }
}
