<?php

namespace App\Console\Commands\Catalogo;

use App\Models\Syllabus;
use App\Services\Catalog\CatalogResolver;
use App\Support\CourseNameSimilarity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solo lectura. Lenguajes, tecnologías y metodologías que creó la extracción de sílabos
 * (registrados en structured_data['catalogo']), para revisarlos y decidir alias en
 * config/catalogo.php. Muestra también lo que entró por alias o normalización y las
 * categorías desconocidas que se asignaron a 'other'.
 *
 * Sugerencias por cada entidad creada:
 *  - nombres parecidos del mismo catálogo o de market_entities (≥ 75 %): candidatos a alias;
 *  - nombre igual a una entidad de mercado sin vincular: para tecnologías, la vincula
 *    `php artisan market:fix-null-entities`; para lenguajes y metodologías ese comando no
 *    lo hace y hay que asignar market_entity_id a mano.
 */
class CatalogoNuevas extends Command
{
    protected $signature = 'catalogo:nuevas
        {--desde= : Solo sílabos procesados desde esta fecha (AAAA-MM-DD)}
        {--origen= : upload o silabos_v2}
        {--modelo= : Solo sílabos de silabos_v2 de un modelo (isil o scl)}';

    protected $description = 'Entidades del catálogo creadas por la extracción de sílabos, con sugerencias de alias y de vínculo con el mercado';

    private const MIN_PERCENT = 75;

    private const LABELS = ['technologies' => 'Tecnologías', 'languages' => 'Lenguajes', 'methodologies' => 'Metodologías'];

    private const MARKET_TYPE = ['technologies' => 'technology', 'languages' => 'language', 'methodologies' => 'methodology'];

    private const PIVOTS = [
        'technologies' => ['course_technology', 'technology_id'],
        'languages' => ['course_language', 'language_id'],
        'methodologies' => ['course_methodology', 'methodology_id'],
    ];

    public function handle(): int
    {
        $created = [];
        $other = [];
        $counts = [];

        foreach ($this->syllabi() as $s) {
            foreach ($s->structured_data['catalogo'] ?? [] as $type => $entries) {
                foreach ($entries as $e) {
                    $counts[$type][$e['via']] = ($counts[$type][$e['via']] ?? 0) + 1;
                    if ($type === 'categories' && $e['via'] === 'other') {
                        $other[$e['recibido']][] = $s->id;
                    } elseif ($e['via'] === 'creado' && isset(self::LABELS[$type])) {
                        $created[$type][$e['id']]['recibido'][$e['recibido']] = true;
                        $created[$type][$e['id']]['silabos'][$s->id] = true;
                    }
                }
            }
        }

        if (!$counts) {
            $this->info('Ningún sílabo con registro de catálogo para estos filtros.');
            return self::SUCCESS;
        }

        $fixNull = false;
        foreach (self::LABELS as $type => $label) {
            $fixNull = $this->showCreated($type, $label, $created[$type] ?? []) || $fixNull;
        }

        if ($other) {
            $this->newLine();
            $this->line('<options=bold>Categorías desconocidas asignadas a other</> (candidatas a alias en config/catalogo.php)');
            $this->table(['Tipo devuelto por la IA', 'Sílabos'], collect($other)
                ->map(fn ($ids, $name) => [$name, implode(', ', array_unique($ids))])->values()->all());
        }

        $this->newLine();
        $this->line('<options=bold>Resumen</> (solo lo que no fue coincidencia exacta)');
        $this->table(['Catálogo', 'Creados', 'Por alias', 'Por normalización', 'A other'], collect(self::LABELS + ['categories' => 'Categorías'])
            ->map(fn ($label, $type) => [$label, $counts[$type]['creado'] ?? 0, $counts[$type]['alias'] ?? 0,
                $counts[$type]['normalizado'] ?? 0, $counts[$type]['other'] ?? 0])->values()->all());

        if ($fixNull) {
            $this->newLine();
            $this->warn('Hay tecnologías nuevas con el mismo nombre que una entidad de mercado sin vincular.');
            $this->line('  `php artisan market:fix-null-entities` las vincularía. ESCRIBE en la base: llena');
            $this->line('  technologies.market_entity_id por nombre exacto y el market_entity_id vacío de');
            $this->line('  technology_job, language_job y methodology_job (de todas las ofertas, no solo de sílabos).');
        }

        return self::SUCCESS;
    }

    private function syllabi()
    {
        $query = Syllabus::whereNotNull('structured_data');
        if ($desde = $this->option('desde')) {
            $query->where('updated_at', '>=', $desde);
        }
        if ($origen = $this->option('origen')) {
            $query->where('source', $origen);
        }
        if ($modelo = $this->option('modelo')) {
            $query->where('source', Syllabus::SOURCE_SILABOS_V2)->where('external_model', $modelo);
        }

        return $query->orderBy('id')->cursor();
    }

    /** Devuelve true si alguna tecnología nueva se vincularía con market:fix-null-entities. */
    private function showCreated(string $type, string $label, array $created): bool
    {
        $this->newLine();
        $this->line("<options=bold>$label creadas por sílabos</>: " . count($created));
        if (!$created) {
            return false;
        }

        $table = (new (CatalogResolver::TYPES[$type]))->getTable();
        $hasMarket = CatalogResolver::hasMarketEntity($table);
        $market = $this->marketEntities($type);
        $catalog = DB::table($table)->get(['id', 'name']);
        [$pivot, $fk] = self::PIVOTS[$type];

        $fixNull = false;
        $rows = [];
        foreach ($created as $id => $info) {
            $row = DB::table($table)->where('id', $id)->first();
            if (!$row) {
                $rows[] = [$id, '(ya no existe)', implode(', ', array_keys($info['recibido'])), implode(', ', array_keys($info['silabos'])), '', '', ''];
                continue;
            }

            $suggestions = [];
            $exactMarket = $market->first(fn ($m) => mb_strtolower($m->name) === mb_strtolower($row->name));
            $linked = $hasMarket && $row->market_entity_id;
            if (!$linked && $exactMarket) {
                if ($type === 'technologies') {
                    $suggestions[] = "market:fix-null-entities la vincula con «{$exactMarket->name}» ({$exactMarket->id})";
                    $fixNull = true;
                } else {
                    $suggestions[] = "igual a la entidad de mercado {$exactMarket->id}; vincular a mano";
                }
            }
            foreach ($this->similar($row, $catalog) as $s) {
                $suggestions[] = "parecida a «{$s->name}» ({$s->id}, {$s->pct} %)";
            }
            foreach ($this->similar($row, $market) as $s) {
                if (!$exactMarket || $s->id !== $exactMarket->id) {
                    $suggestions[] = "parecida a la entidad de mercado «{$s->name}» ({$s->pct} %)";
                }
            }

            $rows[] = [
                $id,
                $row->name,
                implode(', ', array_keys($info['recibido'])),
                implode(', ', array_keys($info['silabos'])),
                DB::table($pivot)->where($fk, $id)->count(),
                $linked ? "sí ({$row->market_entity_id})" : 'no',
                $suggestions ? implode("\n", $suggestions) : '—',
            ];
        }

        $this->table(['ID', 'Nombre', 'Recibido como', 'Sílabos', 'Cursos', 'Mercado', 'Sugerencias'], $rows);

        return $fixNull;
    }

    private function marketEntities(string $type)
    {
        if (!Schema::hasTable('market_entities')) {
            return collect();
        }

        return DB::table('market_entities')->where('entity_type', self::MARKET_TYPE[$type])->get(['id', 'name']);
    }

    /** Hasta 3 nombres parecidos (≥ MIN_PERCENT), sin contar el propio. */
    private function similar(object $row, $candidates): array
    {
        return $candidates
            ->filter(fn ($c) => !($c->id === $row->id && $c->name === $row->name))
            ->map(fn ($c) => (object) ['id' => $c->id, 'name' => $c->name, 'pct' => CourseNameSimilarity::percent($row->name, $c->name)])
            ->filter(fn ($c) => $c->pct >= self::MIN_PERCENT)
            ->sortByDesc('pct')
            ->take(3)
            ->values()
            ->all();
    }
}
