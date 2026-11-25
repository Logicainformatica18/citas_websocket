<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\DB;

/**
 * "Probar versión": clasifica una muestra (50-200) con la versión vigente y con el borrador, con las mismas
 * líneas que usa la clasificación real (ofertas de título específico: título y empresas; genéricas: con la
 * descripción; tendencias: como en ai:classify), y guarda la concordancia, los cambios uno por uno y el costo.
 * No cambia ningún estado. La ejecuta ai:classify (pruebas pendientes) o ai:prompt-test.
 */
class PromptTester
{
    public function __construct(private OpenAiClient $ai, private OfferClassifier $offers, private TrendClassifier $trends)
    {
    }

    public function run(object $test, RunLog $run): void
    {
        DB::table('ai_prompt_tests')->where('id', $test->id)->update(['status' => 'en_curso', 'updated_at' => now()]);
        try {
            $base = Prompts::version((int) $test->base_version_id);
            $draft = Prompts::version((int) $test->draft_version_id);
            [$items, $lines] = $test->kind === 'offers' ? $this->offerSample((int) $test->sample_size) : $this->trendSample((int) $test->sample_size);
            $cost = 0.0;
            $answers = [];
            foreach (['base' => $base, 'draft' => $draft] as $tag => $v) {
                foreach (array_chunk($lines, 50, true) as $chunk) {
                    $res = $this->ai->chat($v->model, $v->body, implode("\n", $chunk));
                    $run->usage($v->model, $res['in'], $res['cached'], $res['out']);
                    $cost += Cost::usd($v->model, $res['in'], $res['cached'], $res['out']);
                    foreach ($res['rows'] as $x) {
                        $answers[(int) ($x['id'] ?? 0)][$tag] = [
                            'c' => $test->kind === 'offers' ? OfferClassifier::cls($x['c'] ?? null) : TrendClassifier::cls($x['c'] ?? null, $x['cat'] ?? null),
                            'cat' => $x['cat'] ?? null, 'm' => $x['m'] ?? null, 'p' => $x['p'] ?? null, 'd' => $x['d'] ?? null,
                        ];
                    }
                }
            }
            $same = 0;
            $both = 0;
            foreach ($items as $i => &$item) {
                $item['base'] = $answers[$i]['base'] ?? null;
                $item['draft'] = $answers[$i]['draft'] ?? null;
                if ($item['base'] && $item['draft']) {
                    $both++;
                    $same += (int) ($item['base']['c'] === $item['draft']['c']);
                }
            }
            unset($item);
            DB::table('ai_prompt_tests')->where('id', $test->id)->update([
                'status' => 'terminada', 'agreement' => $both ? round(100 * $same / $both, 2) : null, 'cost_usd' => round($cost, 5),
                'result' => json_encode(['compared' => $both, 'same' => $same, 'items' => array_values($items)], JSON_UNESCAPED_UNICODE),
                'ran_at' => now(), 'updated_at' => now(),
            ]);
            $run->summary['tests'][] = ['test' => $test->id, 'agreement' => $both ? round(100 * $same / $both, 1) : null, 'cost_usd' => round($cost, 4)];
        } catch (\Throwable $e) {
            DB::table('ai_prompt_tests')->where('id', $test->id)->update(['status' => 'fallida', 'error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now()]);
            $run->error("Prueba {$test->id}: " . $e->getMessage());
        }
    }

    /** Ofertas al azar que no excluyó una keyword. */
    private function offerSample(int $n): array
    {
        $rows = DB::table('job_offers as jo')->where(fn ($q) => $q->whereNull('jo.ti_origin')->orWhere('jo.ti_origin', '<>', 'keyword'))
            ->where('jo.ti_status', '<>', 'en_proceso')->inRandomOrder()->limit($n)
            ->selectRaw('jo.id, jo.title, jo.company, jo.source, jo.ti_status, jo.ti_origin, ' . OfferClassifier::DESC . ' AS d')->get();
        $chars = (int) config('ai_classification.description_chars', 2000);
        $items = [];
        $lines = [];
        $i = 0;
        foreach ($rows as $r) {
            $generic = TitleKey::isGeneric((string) $r->title);
            $text = $generic ? OfferClassifier::clean(OfferClassifier::plain($r->d), $chars) : '';
            $items[++$i] = ['id' => $r->id, 'title' => $r->title, 'company' => $r->company, 'source' => $r->source,
                'generic' => $generic, 'current' => $r->ti_status, 'origin' => $r->ti_origin];
            $lines[$i] = $i . "\t" . OfferClassifier::clean($r->title, 200) . "\t" . OfferClassifier::clean($r->company, 80) . "\t" . $text;
        }

        return [$items, $lines];
    }

    /** Tendencias al azar que no caen en una regla. */
    private function trendSample(int $n): array
    {
        $rows = DB::table('entity_trends')->whereNotIn('discovered_by', config('ai_classification.trend_rule_sources', []))
            ->where(fn ($q) => $q->whereNull('relevance_origin')->orWhere('relevance_origin', '<>', 'regla'))
            ->inRandomOrder()->limit($n * 2)->get(['id', 'discovered_by', 'source_url', 'source_title', 'trend_name', 'relevance_status', 'relevance_origin']);
        $rows = $rows->filter(fn ($r) => TrendClassifier::ruleFor($r->discovered_by, $r->source_url) === null)->take($n)->values();
        [$map, $lines] = $this->trends->lines($rows->pluck('id')->all());
        $byId = $rows->keyBy('id');
        $items = [];
        foreach ($map as $i => $id) {
            $r = $byId[$id];
            $items[$i] = ['id' => $id, 'title' => $r->source_title ?: $r->trend_name, 'url' => $r->source_url,
                'current' => $r->relevance_status, 'origin' => $r->relevance_origin];
        }

        return [$items, $lines];
    }
}
