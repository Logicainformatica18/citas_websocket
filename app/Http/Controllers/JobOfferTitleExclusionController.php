<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeTitleExclusionsJob;
use App\Jobs\ApplyTitleExclusionsJob;
use App\Jobs\DeleteExcludedOffersJob;
use App\Jobs\RestoreDeletedOffersJob;
use App\Jobs\SuggestTitleExclusionsJob;
use App\Models\JobOfferProcess;
use App\Models\JobTitleExclusionKeyword;
use App\Services\JobOffers\TitleExclusionMatcher;
use App\Services\JobOffers\TitleExclusionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Lista de exclusión por título del módulo /job-offers: administración de patrones, vista previa
 * (coincidencias y ejemplos), "Sugerir con IA", "Analizar basura", "Excluir de los indicadores",
 * "Eliminar definitivamente" y su reversión. Todo lo pesado corre en la cola por defecto
 * (job_offer_processes registra quién, cuándo y cuántas).
 */
class JobOfferTitleExclusionController extends Controller
{
    /** Procesos que modifican ofertas o leen la tabla completa: uno a la vez. */
    private const EXCLUSIVE = ['apply', 'analyze', 'delete', 'restore'];

    public const CONFIRM_WORD = 'ELIMINAR';

    public function __construct(private TitleExclusionService $service)
    {
    }

    public function index()
    {
        $latest = fn (string $type) => JobOfferProcess::where('type', $type)->latest('id')->first()?->toApi();

        return Inertia::render('job_offers/title_exclusions/index', [
            'keywords' => JobTitleExclusionKeyword::orderBy('id')->get()->map(fn ($k) => $this->keywordApi($k)),
            'categories' => config('job_title_exclusions.categories'),
            'summary' => $this->service->summary(),
            'latest' => [
                'apply' => $latest('apply'),
                'analyze' => $latest('analyze'),
                'delete' => $latest('delete'),
                'restore' => $latest('restore'),
                'suggest' => $latest('suggest'),
            ],
            'processes' => JobOfferProcess::where('type', '<>', 'export')->latest('id')->limit(30)->get()
                ->map(fn ($p) => $this->processSummary($p)),
            'confirmWord' => self::CONFIRM_WORD,
        ]);
    }

    /* ---------------- Patrones ---------------- */

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['origin'] = 'manual';
        $keyword = JobTitleExclusionKeyword::create($data);
        TitleExclusionService::touchList();

        return response()->json(['keyword' => $this->keywordApi($keyword), 'message' => 'Patrón agregado.']);
    }

    public function update(Request $request, JobTitleExclusionKeyword $keyword)
    {
        $keyword->update($this->validated($request, $keyword));
        TitleExclusionService::touchList();

        return response()->json(['keyword' => $this->keywordApi($keyword->fresh()), 'message' => 'Patrón actualizado.']);
    }

    public function toggle(JobTitleExclusionKeyword $keyword)
    {
        $keyword->update(['is_active' => ! $keyword->is_active]);
        TitleExclusionService::touchList();

        return response()->json(['keyword' => $this->keywordApi($keyword), 'message' => $keyword->is_active ? 'Patrón activado.' : 'Patrón desactivado.']);
    }

    public function destroy(JobTitleExclusionKeyword $keyword)
    {
        $marked = DB::table('job_offers')->where('title_exclusion_id', $keyword->id)->count();
        $keyword->delete();
        TitleExclusionService::touchList();

        return response()->json(['message' => $marked > 0
            ? "Patrón eliminado. {$marked} ofertas siguen marcadas con él hasta que ejecutes \"Excluir de los indicadores\"."
            : 'Patrón eliminado.']);
    }

    /** Vista previa de un patrón (nuevo o en edición): coincidencias y ejemplos de títulos. */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'pattern' => 'required|string|max:255',
            'match_mode' => ['required', Rule::in(['text', 'regex'])],
            'whole_word' => 'boolean',
            'type' => ['required', Rule::in(['exclude', 'exception'])],
            'keyword_id' => 'nullable|integer',
        ]);
        if ($error = TitleExclusionMatcher::error($data['pattern'], $data['match_mode'], (bool) ($data['whole_word'] ?? false))) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json($this->service->preview(
            $data['pattern'], $data['match_mode'], (bool) ($data['whole_word'] ?? false), $data['type'], 40, $data['keyword_id'] ?? null
        ));
    }

    /** Ejemplos de un patrón guardado. */
    public function examples(JobTitleExclusionKeyword $keyword)
    {
        return response()->json($this->service->preview(
            $keyword->pattern, $keyword->match_mode, $keyword->whole_word, $keyword->type, 40, $keyword->id
        ) + ['keyword' => $this->keywordApi($keyword)]);
    }

    /* ---------------- Procesos en cola ---------------- */

    /** "Excluir de los indicadores": marca las ofertas con la lista activa y recalcula los rankings. */
    public function apply()
    {
        if ($busy = JobOfferProcess::busy(self::EXCLUSIVE)) {
            return $this->busy($busy);
        }
        $p = JobOfferProcess::start('apply', ['refresh' => true, 'origin' => 'interfaz']);
        ApplyTitleExclusionsJob::dispatch($p->id);

        return response()->json(['process' => $p->toApi(), 'message' => 'Exclusión encolada.']);
    }

    public function analyze(Request $request)
    {
        $data = $request->validate(['year' => 'nullable|integer|min:2015|max:2100', 'period' => ['nullable', Rule::in(['s1', 's2'])]]);
        if ($busy = JobOfferProcess::busy(self::EXCLUSIVE)) {
            return $this->busy($busy);
        }
        $p = JobOfferProcess::start('analyze', array_filter($data));
        AnalyzeTitleExclusionsJob::dispatch($p->id);

        return response()->json(['process' => $p->toApi(), 'message' => 'Análisis encolado.']);
    }

    /** "Eliminar definitivamente" las ofertas del último análisis (con respaldo). */
    public function deleteOffers(Request $request)
    {
        $data = $request->validate(['analysis_id' => 'required|integer', 'confirm' => 'required|string']);
        if ($data['confirm'] !== self::CONFIRM_WORD) {
            return response()->json(['message' => 'Escribe ' . self::CONFIRM_WORD . ' para confirmar.'], 422);
        }
        $analysis = JobOfferProcess::where('type', 'analyze')->where('status', 'done')->latest('id')->first();
        if (! $analysis || $analysis->id !== (int) $data['analysis_id']) {
            return response()->json(['message' => 'Solo se puede eliminar con el último análisis terminado. Vuelve a analizar.'], 422);
        }
        if (($analysis->result['excluded'] ?? 0) === 0) {
            return response()->json(['message' => 'El análisis no tiene ofertas para eliminar.'], 422);
        }
        if ($busy = JobOfferProcess::busy(self::EXCLUSIVE)) {
            return $this->busy($busy);
        }
        $p = JobOfferProcess::start('delete', [
            'analysis_id' => $analysis->id,
            'offers' => $analysis->result['excluded'],
            'associations' => $analysis->result['associations_total'] ?? null,
        ]);
        DeleteExcludedOffersJob::dispatch($p->id);

        return response()->json(['process' => $p->toApi(), 'message' => 'Eliminación encolada (con respaldo).']);
    }

    public function restore(JobOfferProcess $process)
    {
        abort_unless($process->type === 'delete' && $process->status === 'done', 422, 'Solo se restaura una eliminación terminada.');
        if (! ($process->result['restorable'] ?? false)) {
            return response()->json(['message' => 'Esa eliminación ya se restauró.'], 422);
        }
        if ($busy = JobOfferProcess::busy(self::EXCLUSIVE)) {
            return $this->busy($busy);
        }
        $p = JobOfferProcess::start('restore', ['delete_process_id' => $process->id]);
        RestoreDeletedOffersJob::dispatch($p->id);

        return response()->json(['process' => $p->toApi(), 'message' => 'Restauración encolada.']);
    }

    public function suggest()
    {
        if ($busy = JobOfferProcess::busy(['suggest'])) {
            return $this->busy($busy);
        }
        $p = JobOfferProcess::start('suggest');
        SuggestTitleExclusionsJob::dispatch($p->id);

        return response()->json(['process' => $p->toApi(), 'message' => 'Consulta a la IA encolada.']);
    }

    /** Aprueba sugerencias de la IA (una, varias o todas): se agregan a la lista como activas. */
    public function approve(Request $request, JobOfferProcess $process)
    {
        abort_unless($process->type === 'suggest' && $process->status === 'done', 422);
        $keys = $request->validate(['keys' => 'required|array|min:1', 'keys.*' => 'string'])['keys'];

        $result = $process->result;
        $created = 0;
        $skipped = 0;
        foreach ($result['suggestions'] as &$s) {
            if (! in_array($s['key'], $keys, true) || $s['status'] !== 'pending') {
                continue;
            }
            $exists = JobTitleExclusionKeyword::where(['type' => $s['type'], 'match_mode' => $s['match_mode'],
                'whole_word' => $s['whole_word'], 'pattern' => $s['pattern']])->exists();
            if ($exists || TitleExclusionMatcher::error($s['pattern'], $s['match_mode'], $s['whole_word'])) {
                $s['status'] = 'skipped';
                $skipped++;
                continue;
            }
            JobTitleExclusionKeyword::create([
                'pattern' => $s['pattern'], 'type' => $s['type'], 'match_mode' => $s['match_mode'],
                'whole_word' => $s['whole_word'], 'category' => $s['category'], 'is_active' => true,
                'origin' => 'ai', 'notes' => trim('IA: ' . $s['reason'] . ' (aprobado por ' . (JobOfferProcess::userLabel(auth()->user()) ?? '?') . ')'),
            ]);
            $s['status'] = 'approved';
            $created++;
        }
        unset($s);
        $process->result = $result;
        $process->save();
        if ($created) {
            TitleExclusionService::touchList();
        }

        return response()->json(['message' => "{$created} patrón(es) agregado(s)" . ($skipped ? ", {$skipped} omitido(s) (ya existían)" : '') . '. Ejecuta "Excluir de los indicadores" para aplicarlos.',
            'process' => $process->toApi()]);
    }

    /** Descarta sugerencias (quedan registradas como rechazadas). */
    public function reject(Request $request, JobOfferProcess $process)
    {
        abort_unless($process->type === 'suggest' && $process->status === 'done', 422);
        $keys = $request->validate(['keys' => 'required|array|min:1', 'keys.*' => 'string'])['keys'];
        $result = $process->result;
        foreach ($result['suggestions'] as &$s) {
            if (in_array($s['key'], $keys, true) && $s['status'] === 'pending') {
                $s['status'] = 'rejected';
            }
        }
        unset($s);
        $process->result = $result;
        $process->save();

        return response()->json(['process' => $process->toApi()]);
    }

    public function status(JobOfferProcess $process)
    {
        return response()->json(['process' => $process->toApi(), 'summary' => $this->service->summary()]);
    }

    /* ---------------- Utilidades ---------------- */

    private function validated(Request $request, ?JobTitleExclusionKeyword $current = null): array
    {
        $data = $request->validate([
            'pattern' => 'required|string|max:255',
            'type' => ['required', Rule::in(['exclude', 'exception'])],
            'match_mode' => ['required', Rule::in(['text', 'regex'])],
            'whole_word' => 'boolean',
            'category' => 'nullable|string|max:60',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['pattern'] = trim($data['pattern']);
        $data['whole_word'] = (bool) ($data['whole_word'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if ($data['match_mode'] === 'text') {
            $data['pattern'] = TitleExclusionMatcher::normalize($data['pattern']);
        }

        if ($error = TitleExclusionMatcher::error($data['pattern'], $data['match_mode'], $data['whole_word'])) {
            abort(response()->json(['message' => $error, 'errors' => ['pattern' => [$error]]], 422));
        }
        $duplicate = JobTitleExclusionKeyword::where(['type' => $data['type'], 'match_mode' => $data['match_mode'],
            'whole_word' => $data['whole_word'], 'pattern' => $data['pattern']])
            ->when($current, fn ($q) => $q->where('id', '<>', $current->id))->exists();
        if ($duplicate) {
            abort(response()->json(['message' => 'Ese patrón ya está en la lista.', 'errors' => ['pattern' => ['Duplicado']]], 422));
        }

        return $data;
    }

    private function keywordApi(JobTitleExclusionKeyword $k): array
    {
        return [
            'id' => $k->id,
            'pattern' => $k->pattern,
            'type' => $k->type,
            'match_mode' => $k->match_mode,
            'whole_word' => $k->whole_word,
            'category' => $k->category,
            'is_active' => $k->is_active,
            'origin' => $k->origin,
            'notes' => $k->notes,
            'matches_count' => $k->matches_count,
            'counted_at' => $k->counted_at?->format('Y-m-d H:i'),
            'updated_at' => $k->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /** Fila del historial (sin el detalle pesado del resultado). */
    private function processSummary(JobOfferProcess $p): array
    {
        $api = $p->toApi();
        $r = $p->result ?? [];
        $api['result'] = array_intersect_key($r, array_flip([
            'excluded', 'newly_excluded', 'restored_to_valid', 'offers_deleted', 'offers_restored', 'backup_rows',
            'restorable', 'rankings', 'received', 'dry_run', 'cleared', 'associations_total', 'restored_at',
        ]));
        if ($p->type === 'suggest') {
            $api['result']['approved'] = collect($r['suggestions'] ?? [])->where('status', 'approved')->count();
        }

        return $api;
    }

    private function busy(JobOfferProcess $busy)
    {
        return response()->json([
            'message' => 'Hay otro proceso en curso: ' . (JobOfferProcess::LABELS[$busy->type] ?? $busy->type) . " (#{$busy->id}). Espera a que termine.",
            'process' => $busy->toApi(),
        ], 409);
    }
}
