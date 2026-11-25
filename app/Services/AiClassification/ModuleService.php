<?php

namespace App\Services\AiClassification;

use App\Services\Indicators\IndicatorScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Acciones livianas del módulo "Clasificación con IA" (todo lo que llama a la IA corre en los comandos de consola):
 * resumen, revisión manual, prompts y versiones, pruebas de versión (quedan pendientes) e interruptor.
 */
class ModuleService
{
    public const REVIEW_STATUSES = ['dudosa', 'no_ti', 'ti'];

    public const TREND_STATUSES = ['dudosa', 'ruido', 'relevante'];

    /* =================== resumen =================== */

    public function summary(): array
    {
        $offers = DB::table('job_offers')->selectRaw('ti_status s, ti_origin o, COUNT(*) n')->groupBy('ti_status', 'ti_origin')->get();
        $trends = DB::table('entity_trends')->selectRaw('relevance_status s, relevance_origin o, COUNT(*) n')->groupBy('relevance_status', 'relevance_origin')->get();
        $runs = DB::table('ai_runs')->orderByDesc('id')->limit(15)->get()->map(fn ($r) => [
            'id' => $r->id, 'command' => $r->command, 'kind' => $r->kind, 'dry_run' => (bool) $r->dry_run, 'status' => $r->status,
            'processed' => $r->processed, 'inherited' => $r->inherited, 'by_rule' => $r->by_rule, 'calls' => $r->calls,
            'tokens_in' => $r->tokens_in, 'tokens_out' => $r->tokens_out, 'cost_usd' => (float) $r->cost_usd,
            'errors' => $r->errors, 'started_at' => $r->started_at, 'finished_at' => $r->finished_at,
        ]);
        $settings = AiSettings::row();

        return [
            'offers' => $offers, 'trends' => $trends, 'runs' => $runs,
            'cost_month' => (float) DB::table('ai_runs')->where('started_at', '>=', now()->startOfMonth())->sum('cost_usd'),
            'cost_total' => (float) DB::table('ai_runs')->sum('cost_usd'),
            'batches' => DB::table('ai_batches')->orderByDesc('id')->limit(10)
                ->get(['id', 'kind', 'pass', 'status', 'provider_status', 'requests', 'items', 'cost_usd', 'error', 'submitted_at', 'applied_at']),
            'batches_in_flight' => BatchRunner::inFlight(),
            'settings' => [
                'enabled' => (bool) ($settings->enabled ?? false),
                'trends_filter_enabled' => (bool) ($settings->trends_filter_enabled ?? false),
                'history_submitted_at' => $settings->history_submitted_at ?? null,
                'offers_history_max_id' => (int) ($settings->offers_history_max_id ?? 0),
                'trends_history_max_id' => (int) ($settings->trends_history_max_id ?? 0),
                'updated_by' => $settings->updated_by ?? null, 'updated_at' => $settings->updated_at ?? null,
            ],
        ];
    }

    public function setEnabled(bool $on, string $by): void
    {
        AiSettings::update(['enabled' => $on], $by);
    }

    /* =================== revisión manual: ofertas =================== */

    /**
     * Ofertas en un estado, agrupadas por título (clave normalizada), con número de ofertas y ejemplos. Solo
     * decisiones de la IA o manuales (las de keyword se corrigen con las excepciones de la exclusión por título).
     */
    public function offerGroups(string $status, ?string $search, int $page, int $per = 30): array
    {
        $q = DB::table('job_offers')->where('ti_status', $status)->whereIn('ti_origin', ['ia', 'manual'])->whereNotNull('title_key');
        if ($search) {
            $q->where('title', 'like', '%' . $search . '%');
        }
        $total = (clone $q)->distinct()->count('title_key');
        $groups = $q->groupBy('title_key')->selectRaw('title_key, MIN(title) AS title, COUNT(*) AS offers, SUM(ti_origin = \'manual\') AS manual')
            ->orderByDesc('offers')->orderBy('title_key')->offset(($page - 1) * $per)->limit($per)->get();
        $keys = $groups->pluck('title_key')->all();
        $tc = DB::table('ai_title_classifications')->whereIn('title_key', $keys)->get()->keyBy('title_key');
        $chars = 300;
        $out = [];
        foreach ($groups as $g) {
            $examples = DB::table('job_offers as jo')->leftJoin('ai_classification_results as r', 'r.id', '=', 'jo.ti_result_id')
                ->where('jo.title_key', $g->title_key)->where('jo.ti_status', $status)->whereIn('jo.ti_origin', ['ia', 'manual'])
                ->orderByDesc('jo.id')->limit(5)
                ->selectRaw('jo.id, jo.title, jo.company, jo.source, jo.published_at, jo.url, jo.ti_origin, r.reason, r.category, r.confidence, r.pass, '
                    . 'SUBSTRING(' . OfferClassifier::DESC . ', 1, 1200) AS d')
                ->get()->map(function ($e) use ($chars) {
                    $e->d = OfferClassifier::clean(OfferClassifier::plain($e->d), $chars);

                    return $e;
                });
            $t = $tc[$g->title_key] ?? null;
            $out[] = [
                'title_key' => $g->title_key, 'title' => $g->title, 'offers' => (int) $g->offers, 'manual' => (int) $g->manual,
                'generic' => TitleKey::isGeneric((string) $g->title),
                'title_decision' => $t ? ['status' => $t->status, 'depends' => (bool) $t->depends_on_description, 'category' => $t->category,
                    'reason' => $t->reason, 'confidence' => $t->confidence, 'origin' => $t->origin] : null,
                'examples' => $examples,
            ];
        }

        return ['total' => $total, 'page' => $page, 'per' => $per, 'groups' => $out];
    }

    /**
     * Decisión manual sobre un grupo (título) en un estado. Título específico: todas sus ofertas que no son de
     * keyword pasan al estado elegido y la decisión del título queda manual (la heredan las ofertas futuras).
     * Título genérico: solo las ofertas del grupo (cada una puede ser distinta).
     */
    public function decideGroup(string $titleKey, string $fromStatus, string $to, string $by): int
    {
        $this->checkOffer($to);
        $sample = DB::table('job_offers')->where('title_key', $titleKey)->orderByDesc('id')->value('title');
        if ($sample === null) {
            throw new RuntimeException('No hay ofertas con ese título');
        }
        $generic = TitleKey::isGeneric($sample);

        return DB::transaction(function () use ($titleKey, $fromStatus, $to, $by, $sample, $generic) {
            $q = DB::table('job_offers')->where('title_key', $titleKey)
                ->where(fn ($w) => $w->whereNull('ti_origin')->orWhereIn('ti_origin', ['ia', 'manual']))
                ->whereIn('ti_status', $generic ? [$fromStatus] : ['pendiente', 'ti', 'no_ti', 'dudosa']);
            $n = (clone $q)->count();
            $rid = DB::table('ai_classification_results')->insertGetId([
                'kind' => 'offer_title', 'item_key' => $titleKey, 'status' => $to, 'origin' => 'manual', 'pass' => 'manual',
                'reason' => $generic ? "Revisión manual del grupo (estaban en {$fromStatus})" : 'Revisión manual del título',
                'user_name' => $by, 'items_affected' => $n, 'created_at' => now(),
            ]);
            $q->update(['ti_status' => $to, 'ti_origin' => 'manual', 'ti_result_id' => $rid, 'ti_batch_id' => null]);
            if (! $generic) {
                DB::table('ai_title_classifications')->updateOrInsert(['title_key' => $titleKey], [
                    'title' => mb_substr($sample, 0, 500), 'status' => $to, 'depends_on_description' => false,
                    'reason' => 'Revisión manual', 'confidence' => 100, 'origin' => 'manual', 'result_id' => $rid,
                    'prompt_version_id' => null, 'model' => null, 'updated_at' => now(), 'created_at' => now(),
                ]);
            }
            IndicatorScope::bumpCacheVersion();

            return $n;
        });
    }

    /** Decisión manual sobre una sola oferta. */
    public function decideOffer(int $id, string $to, string $by): void
    {
        $this->checkOffer($to);
        $offer = DB::table('job_offers')->where('id', $id)->first(['id', 'ti_origin']);
        if (! $offer || $offer->ti_origin === 'keyword') {
            throw new RuntimeException('La oferta no existe o la excluyó una keyword (corrígela con una excepción de la exclusión por título)');
        }
        $rid = DB::table('ai_classification_results')->insertGetId([
            'kind' => 'offer', 'item_key' => (string) $id, 'status' => $to, 'origin' => 'manual', 'pass' => 'manual',
            'reason' => 'Revisión manual', 'user_name' => $by, 'items_affected' => 1, 'created_at' => now(),
        ]);
        DB::table('job_offers')->where('id', $id)->update(['ti_status' => $to, 'ti_origin' => 'manual', 'ti_result_id' => $rid, 'ti_batch_id' => null]);
        IndicatorScope::bumpCacheVersion();
    }

    private function checkOffer(string $to): void
    {
        if (! in_array($to, ['ti', 'no_ti'], true)) {
            throw new RuntimeException('Estado no válido');
        }
    }

    /* =================== revisión manual: tendencias =================== */

    public function trendList(string $status, ?string $search, int $page, int $per = 30): array
    {
        $q = DB::table('entity_trends as et')->leftJoin('market_entities as me', 'me.id', '=', 'et.market_entity_id')
            ->leftJoin('ai_classification_results as r', 'r.id', '=', 'et.ai_result_id')
            ->where('et.relevance_status', $status)->whereIn('et.relevance_origin', ['ia', 'manual', 'regla']);
        if ($search) {
            $q->where(fn ($w) => $w->where('et.source_title', 'like', "%{$search}%")->orWhere('me.name', 'like', "%{$search}%"));
        }
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('et.id')->offset(($page - 1) * $per)->limit($per)
            ->get(['et.id', 'me.name AS entity', 'me.entity_type', 'et.trend_name', 'et.source_title', 'et.source_url', 'et.source_type',
                'et.source_snippet', 'et.discovered_by', 'et.year', 'et.quarter', 'et.relevance_origin', 'r.reason', 'r.category', 'r.confidence']);

        return ['total' => $total, 'page' => $page, 'per' => $per, 'rows' => $rows];
    }

    public function decideTrend(int $id, string $to, string $by): void
    {
        if (! in_array($to, ['relevante', 'ruido'], true)) {
            throw new RuntimeException('Estado no válido');
        }
        $rid = DB::table('ai_classification_results')->insertGetId([
            'kind' => 'trend', 'item_key' => (string) $id, 'status' => $to, 'origin' => 'manual', 'pass' => 'manual',
            'reason' => 'Revisión manual', 'user_name' => $by, 'items_affected' => 1, 'created_at' => now(),
        ]);
        DB::table('entity_trends')->where('id', $id)->update(['relevance_status' => $to, 'relevance_origin' => 'manual', 'ai_result_id' => $rid, 'ai_batch_id' => null]);
        IndicatorScope::bumpCacheVersion();
    }

    /* =================== prompts y versiones =================== */

    public function prompts(): array
    {
        $out = [];
        foreach (DB::table('ai_prompts')->orderBy('id')->get() as $p) {
            $versions = DB::table('ai_prompt_versions')->where('prompt_id', $p->id)->orderByDesc('version')->get();
            $usage = DB::table('ai_classification_results')->whereIn('prompt_version_id', $versions->pluck('id'))
                ->groupBy('prompt_version_id')->selectRaw('prompt_version_id, COUNT(*) n')->pluck('n', 'prompt_version_id');
            $out[] = [
                'id' => $p->id, 'kind' => $p->kind, 'name' => $p->name, 'active_version_id' => $p->active_version_id,
                'versions' => $versions->map(fn ($v) => [
                    'id' => $v->id, 'version' => $v->version, 'body' => $v->body, 'model' => $v->model, 'note' => $v->note,
                    'status' => $v->status, 'created_by' => $v->created_by, 'created_at' => $v->created_at,
                    'activated_by' => $v->activated_by, 'activated_at' => $v->activated_at, 'decisions' => (int) ($usage[$v->id] ?? 0),
                ])->values(),
            ];
        }

        return $out;
    }

    public function createVersion(string $kind, string $body, string $model, ?string $note, string $by): int
    {
        if (! array_key_exists($model, config('ai_classification.prices'))) {
            throw new RuntimeException('Modelo no admitido');
        }
        $p = DB::table('ai_prompts')->where('kind', $kind)->first();
        if (! $p) {
            throw new RuntimeException('Prompt desconocido');
        }

        return DB::transaction(function () use ($p, $body, $model, $note, $by) {
            $n = (int) DB::table('ai_prompt_versions')->where('prompt_id', $p->id)->lockForUpdate()->max('version') + 1;

            return DB::table('ai_prompt_versions')->insertGetId([
                'prompt_id' => $p->id, 'version' => $n, 'body' => $body, 'model' => $model, 'note' => $note, 'status' => 'borrador',
                'created_by' => $by, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Activa una versión (nueva o anterior: "volver a una versión"); la que estaba activa queda retirada. */
    public function activate(int $versionId, string $by): void
    {
        $v = DB::table('ai_prompt_versions')->where('id', $versionId)->first();
        if (! $v) {
            throw new RuntimeException('Versión inexistente');
        }
        DB::transaction(function () use ($v, $by) {
            DB::table('ai_prompt_versions')->where('prompt_id', $v->prompt_id)->where('status', 'activa')->update(['status' => 'retirada', 'updated_at' => now()]);
            DB::table('ai_prompt_versions')->where('id', $v->id)->update(['status' => 'activa', 'activated_by' => $by, 'activated_at' => now(), 'updated_at' => now()]);
            DB::table('ai_prompts')->where('id', $v->prompt_id)->update(['active_version_id' => $v->id, 'updated_at' => now()]);
        });
    }

    /** Diferencias por línea entre dos textos (LCS): [['=', línea], ['-', línea], ['+', línea]…]. */
    public static function diff(string $old, string $new): array
    {
        $a = explode("\n", str_replace("\r", '', $old));
        $b = explode("\n", str_replace("\r", '', $new));
        $n = count($a);
        $m = count($b);
        $l = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $l[$i][$j] = $a[$i] === $b[$j] ? $l[$i + 1][$j + 1] + 1 : max($l[$i + 1][$j], $l[$i][$j + 1]);
            }
        }
        $out = [];
        for ($i = 0, $j = 0; $i < $n || $j < $m;) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $out[] = ['=', $a[$i]];
                $i++;
                $j++;
            } elseif ($j < $m && ($i >= $n || $l[$i][$j + 1] >= $l[$i + 1][$j])) {
                $out[] = ['+', $b[$j++]];
            } else {
                $out[] = ['-', $a[$i++]];
            }
        }

        return $out;
    }

    /* =================== pruebas de versión =================== */

    public function createTest(int $draftVersionId, int $size, string $by): int
    {
        $v = DB::table('ai_prompt_versions as v')->join('ai_prompts as p', 'p.id', '=', 'v.prompt_id')
            ->where('v.id', $draftVersionId)->first(['v.id', 'p.kind', 'p.active_version_id']);
        if (! $v) {
            throw new RuntimeException('Versión inexistente');
        }
        if ((int) $v->active_version_id === (int) $v->id) {
            throw new RuntimeException('Esa versión ya es la vigente: elige un borrador');
        }

        return DB::table('ai_prompt_tests')->insertGetId([
            'kind' => $v->kind, 'draft_version_id' => $v->id, 'base_version_id' => $v->active_version_id,
            'sample_size' => max(50, min(200, $size)), 'status' => 'pendiente', 'created_by' => $by,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function tests(): array
    {
        return DB::table('ai_prompt_tests as t')
            ->leftJoin('ai_prompt_versions as d', 'd.id', '=', 't.draft_version_id')
            ->leftJoin('ai_prompt_versions as b', 'b.id', '=', 't.base_version_id')
            ->orderByDesc('t.id')->limit(20)
            ->get(['t.*', 'd.version AS draft_version', 'b.version AS base_version'])
            ->map(function ($t) {
                $t->result = $t->result ? json_decode($t->result, true) : null;

                return $t;
            })->all();
    }
}
