<?php

namespace App\Services\Matching;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decide si el texto de una oferta (título + cuerpo) menciona una entidad de mercado
 * (lenguaje, tecnología, certificación…), con las reglas de market_entity_aliases.
 *
 * Reglas por entidad (kind):
 *   alias     texto que confirma la entidad. case_sensitive: distingue mayúsculas (R, Go, Swift).
 *             is_weak: solo cuenta si hay una regla 'context' cerca (o un alias fuerte en la oferta).
 *   negative  contexto que anula un hallazgo ("R&D", "Go-to", "Swift code"). window: caracteres
 *             alrededor del hallazgo; vacío/0 = el negativo debe solaparse con el hallazgo.
 *   context   lo que necesita un alias débil. window: caracteres alrededor; vacío = toda la oferta.
 *             El título cuenta siempre entero como contexto (es corto y describe el puesto).
 * Sin reglas 'alias', se usa el nombre de la entidad (sin distinguir mayúsculas, fuerte).
 *
 * Límites de palabra propios (\b falla con C#, C++, .NET, F#): antes no puede haber letra, dígito,
 * "_" ni "@"; después tampoco, ni "+" ni "#". Así "C" no coincide con "C#"/"C++", "Java" no
 * coincide con "JavaScript" y "SQL" no coincide con "MySQL" (no se deduce una entidad de otra).
 */
final class EntityTextMatcher
{
    public const CONFIRMED = 'confirmed';
    public const REJECTED = 'rejected';     // hay mención, pero una regla la descarta
    public const NO_MENTION = 'no_mention'; // ni siquiera sin distinguir mayúsculas

    public const REASON_CASE = 'case';                 // solo aparece con otras mayúsculas
    public const REASON_NEGATIVE = 'negative';         // un contexto negativo anula todos los hallazgos
    public const REASON_NO_CONTEXT = 'weak_no_context'; // alias débil sin contexto

    private const LEFT = '(?<![\p{L}\p{N}_@])';
    private const RIGHT = '(?![\p{L}\p{N}_+#@])';
    private const SNIPPET = 60;

    /** @var array<string, self> */
    private static array $instances = [];

    /** @var array<int, array{name: string, aliases: list<array>, negative: list<array>, context: list<array>}> */
    private array $entities = [];

    /**
     * @param  array<int, array{name: string, rules?: list<array>}>  $entities
     *         rules: [kind, pattern, case_sensitive?, is_regex?, is_weak?, window?]
     */
    public function __construct(array $entities)
    {
        foreach ($entities as $id => $entity) {
            $this->entities[(int) $id] = $this->compile($entity['name'], $entity['rules'] ?? []);
        }
    }

    /**
     * Matcher de un tipo de entidad (market_entities.entity_type) con sus reglas de la base.
     * Una instancia por proceso y tipo; flush() la descarta (p. ej. tras cambiar reglas).
     */
    public static function for(string $entityType, bool $includeInactive = false): self
    {
        $key = $entityType . ($includeInactive ? ':all' : ':active');

        return self::$instances[$key] ??= self::load($entityType, $includeInactive);
    }

    public static function flush(): void
    {
        self::$instances = [];
    }

    private static function load(string $entityType, bool $includeInactive): self
    {
        $entities = DB::table('market_entities')
            ->where('entity_type', $entityType)
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', 1))
            ->pluck('name', 'id');

        $rules = [];
        if ($entities->isNotEmpty() && Schema::hasTable('market_entity_aliases')) {
            foreach (DB::table('market_entity_aliases')->whereIn('market_entity_id', $entities->keys())->get() as $row) {
                $rules[$row->market_entity_id][] = (array) $row;
            }
        }

        $input = [];
        foreach ($entities as $id => $name) {
            $input[$id] = ['name' => $name, 'rules' => $rules[$id] ?? []];
        }

        return new self($input);
    }

    /** @return list<int> */
    public function entityIds(): array
    {
        return array_keys($this->entities);
    }

    public function has(int $entityId): bool
    {
        return isset($this->entities[$entityId]);
    }

    /** ¿Tiene reglas propias en market_entity_aliases? (sin ellas se usa solo el nombre) */
    public function hasRules(int $entityId): bool
    {
        return $this->entities[$entityId]['explicit'] ?? false;
    }

    public function name(int $entityId): ?string
    {
        return $this->entities[$entityId]['name'] ?? null;
    }

    /**
     * Todas las entidades que menciona la oferta (scrapers de feed).
     *
     * @return array<int, array{field: string, alias: string, snippet: string}>
     */
    public function match(string $title, ?string $body = null): array
    {
        $fields = $this->fields($title, $body);
        $found = [];
        foreach ($this->entities as $id => $entity) {
            $hit = $this->evaluate($entity, $fields);
            if ($hit['verdict'] === self::CONFIRMED) {
                $found[$id] = ['field' => $hit['field'], 'alias' => $hit['alias'], 'snippet' => $hit['snippet']];
            }
        }

        return $found;
    }

    /** ¿La oferta menciona la entidad? (scrapers de búsqueda: confirmar lo que devolvió la API) */
    public function confirms(int $entityId, string $title, ?string $body = null): bool
    {
        return $this->explain($entityId, $title, $body)['verdict'] === self::CONFIRMED;
    }

    /**
     * Veredicto con su motivo (auditoría y depuración).
     *
     * @return array{verdict: string, reason: ?string, field: ?string, alias: ?string, snippet: ?string}
     */
    public function explain(int $entityId, string $title, ?string $body = null): array
    {
        if (! isset($this->entities[$entityId])) {
            return ['verdict' => self::NO_MENTION, 'reason' => 'unknown_entity', 'field' => null, 'alias' => null, 'snippet' => null];
        }

        return $this->evaluate($this->entities[$entityId], $this->fields($title, $body));
    }

    /** Texto plano: sin HTML ni entidades, espacios unidos. NO pasa a minúsculas. */
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $text = html_entity_decode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<[^>]*>/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    // ------------------------------------------------------------------

    /** @return array<string, string> */
    private function fields(string $title, ?string $body): array
    {
        return ['title' => self::normalize($title), 'body' => self::normalize($body)];
    }

    private function compile(string $name, array $rules): array
    {
        $entity = ['name' => $name, 'explicit' => false, 'aliases' => [], 'negative' => [], 'context' => []];

        foreach ($rules as $rule) {
            $rule = (array) $rule;
            $kind = $rule['kind'] ?? 'alias';
            if (! isset($entity[$kind === 'alias' ? 'aliases' : $kind])) {
                continue;
            }
            $pattern = (string) $rule['pattern'];
            $cs = (bool) ($rule['case_sensitive'] ?? false);
            $body = ! empty($rule['is_regex'])
                ? $pattern
                : str_replace(' ', '[\s\-–—]+', preg_quote($pattern, '/'));

            $compiled = [
                'pattern' => $pattern,
                'regex' => '/' . self::LEFT . '(?:' . $body . ')' . self::RIGHT . '/u' . ($cs ? '' : 'i'),
                'loose' => '/' . self::LEFT . '(?:' . $body . ')' . self::RIGHT . '/ui',
                'weak' => (bool) ($rule['is_weak'] ?? false),
                'window' => isset($rule['window_chars']) ? (int) $rule['window_chars'] : (isset($rule['window']) ? (int) $rule['window'] : null),
            ];
            $entity[$kind === 'alias' ? 'aliases' : $kind][] = $compiled;
        }

        $entity['explicit'] = $entity['aliases'] !== [];
        if ($entity['aliases'] === []) {
            $body = str_replace(' ', '[\s\-–—]+', preg_quote($name, '/'));
            $regex = '/' . self::LEFT . '(?:' . $body . ')' . self::RIGHT . '/ui';
            $entity['aliases'][] = ['pattern' => $name, 'regex' => $regex, 'loose' => $regex, 'weak' => false, 'window' => null];
        }

        // Fuertes primero: si un alias fuerte confirma, no hace falta evaluar los débiles.
        usort($entity['aliases'], fn ($a, $b) => $a['weak'] <=> $b['weak']);

        return $entity;
    }

    /** @return list<array{0: int, 1: int}> [inicio, fin) en bytes */
    private static function find(string $regex, string $text): array
    {
        if ($text === '' || ! preg_match_all($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $out = [];
        foreach ($m[0] as [$str, $pos]) {
            $out[] = [$pos, $pos + strlen($str)];
        }

        return $out;
    }

    private function evaluate(array $entity, array $fields): array
    {
        $reasons = [];

        foreach ($entity['aliases'] as $alias) {
            foreach ($fields as $field => $text) {
                foreach (self::find($alias['regex'], $text) as [$start, $end]) {
                    if ($this->negated($entity['negative'], $text, $start, $end)) {
                        $reasons[self::REASON_NEGATIVE] = [$field, $alias['pattern'], $text, $start, $end];

                        continue;
                    }
                    if ($alias['weak'] && ! $this->hasContext($entity['context'], $fields, $field, $start, $end)) {
                        $reasons[self::REASON_NO_CONTEXT] = [$field, $alias['pattern'], $text, $start, $end];

                        continue;
                    }

                    return $this->result(self::CONFIRMED, null, $field, $alias['pattern'], $text, $start, $end);
                }
            }
        }

        foreach ([self::REASON_NEGATIVE, self::REASON_NO_CONTEXT] as $reason) {
            if (isset($reasons[$reason])) {
                return $this->result(self::REJECTED, $reason, ...$reasons[$reason]);
            }
        }

        // ¿Aparece con otras mayúsculas? (p. ej. "SWIFT" o "go" cuando el alias es "Swift"/"Go")
        foreach ($entity['aliases'] as $alias) {
            foreach ($fields as $field => $text) {
                $hits = self::find($alias['loose'], $text);
                if ($hits !== []) {
                    return $this->result(self::REJECTED, self::REASON_CASE, $field, $alias['pattern'], $text, ...$hits[0]);
                }
            }
        }

        return ['verdict' => self::NO_MENTION, 'reason' => null, 'field' => null, 'alias' => null, 'snippet' => null];
    }

    private function negated(array $negatives, string $text, int $start, int $end): bool
    {
        foreach ($negatives as $neg) {
            $w = $neg['window'] ?? 0;
            foreach (self::find($neg['regex'], $text) as [$ns, $ne]) {
                if ($ns < $end + $w && $ne > $start - $w) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasContext(array $contexts, array $fields, string $field, int $start, int $end): bool
    {
        foreach ($contexts as $ctx) {
            if ($ctx['window'] === null) {
                foreach ($fields as $text) {
                    if ($text !== '' && preg_match($ctx['regex'], $text)) {
                        return true;
                    }
                }

                continue;
            }
            if ($field !== 'title' && $fields['title'] !== '' && preg_match($ctx['regex'], $fields['title'])) {
                return true;
            }
            foreach (self::find($ctx['regex'], $fields[$field]) as [$cs, $ce]) {
                if ($cs < $end + $ctx['window'] && $ce > $start - $ctx['window']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function result(string $verdict, ?string $reason, string $field, string $alias, string $text, int $start, int $end): array
    {
        $from = max(0, $start - self::SNIPPET);
        $snippet = mb_strcut($text, $from, ($end - $from) + self::SNIPPET, 'UTF-8');

        return [
            'verdict' => $verdict,
            'reason' => $reason,
            'field' => $field,
            'alias' => $alias,
            'snippet' => ($from > 0 ? '…' : '') . $snippet . ($end + self::SNIPPET < strlen($text) ? '…' : ''),
        ];
    }
}
