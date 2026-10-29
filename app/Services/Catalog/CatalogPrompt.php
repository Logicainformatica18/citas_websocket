<?php

namespace App\Services\Catalog;

use App\Models\Language;
use App\Models\Methodology;
use App\Models\Technology;
use App\Models\TechnologyCategory;

/**
 * Lista de nombres existentes que va en el prompt de extracción, para que la IA use
 * esos nombres exactos cuando se trate de lo mismo. ~1 800 tokens con el catálogo actual.
 *
 * Se arma de la base y cada worker la reutiliza config('catalogo.prompt.cache_segundos').
 */
class CatalogPrompt
{
    private static ?array $cached = null;

    public function text(): string
    {
        $ttl = (int) config('catalogo.prompt.cache_segundos', 300);
        if (!self::$cached || $ttl <= 0 || self::$cached['at'] + $ttl < time()) {
            self::$cached = ['at' => time(), 'text' => $this->build()];
        }

        return self::$cached['text'];
    }

    public static function forget(): void
    {
        self::$cached = null;
    }

    private function build(): string
    {
        $join = fn ($names) => collect($names)->sort(SORT_NATURAL | SORT_FLAG_CASE)->implode('; ');

        return "Lenguajes: " . $join(Language::pluck('name')) . "\n"
            . "Tecnologías: " . $join($this->technologies()) . "\n"
            . "Metodologías: " . $join(Methodology::pluck('name')) . "\n"
            . "Categorías de tecnología (campo 'tipo'): " . $join(TechnologyCategory::pluck('name'));
    }

    private function technologies(): array
    {
        $max = (int) config('catalogo.prompt.max_tecnologias', 0);
        if ($max <= 0) {
            return Technology::pluck('name')->all();
        }

        // Con límite: primero las usadas en más cursos y, a igual uso, las ligadas al mercado.
        $query = Technology::withCount('courses')->orderByDesc('courses_count');
        if (CatalogResolver::hasMarketEntity('technologies')) {
            $query->orderByRaw('market_entity_id IS NULL');
        }

        return $query->orderBy('id')->limit($max)->pluck('name')->all();
    }
}
