<?php

namespace App\Http\Controllers;

use App\Services\AiClassification\ModuleService;
use App\Services\AiClassification\Prompts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Módulo "Clasificación con IA" (administración): resumen, prompts y versiones, probar versión, revisión manual.
 * Acciones livianas: ninguna llama a la IA (las pruebas quedan pendientes y las ejecuta ai:classify o ai:prompt-test).
 */
class AiClassificationController extends Controller
{
    public function __construct(private ModuleService $service)
    {
    }

    public function index()
    {
        return Inertia::render('ai_classification/index', [
            'summary' => $this->service->summary(),
            'prompts' => $this->service->prompts(),
            'tests' => $this->service->tests(),
            'models' => array_keys(config('ai_classification.prices')),
        ]);
    }

    public function summary()
    {
        return response()->json($this->service->summary());
    }

    public function toggle(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $this->service->setEnabled($data['enabled'], $this->user($request));

        return response()->json(['message' => $data['enabled'] ? 'Clasificación con IA activada' : 'Clasificación con IA APAGADA (sin llamadas a la IA)',
            'summary' => $this->service->summary()]);
    }

    /* ---------------- prompts ---------------- */

    public function storeVersion(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(Prompts::KINDS)],
            'body' => 'required|string|min:50|max:60000',
            'model' => ['required', Rule::in(array_keys(config('ai_classification.prices')))],
            'note' => 'nullable|string|max:500',
        ]);
        $id = $this->attempt(fn () => $this->service->createVersion($data['kind'], $data['body'], $data['model'], $data['note'] ?? null, $this->user($request)));

        return response()->json(['message' => 'Borrador guardado. Pruébalo antes de activarlo.', 'id' => $id, 'prompts' => $this->service->prompts()]);
    }

    public function activate(Request $request, int $version)
    {
        $this->attempt(fn () => $this->service->activate($version, $this->user($request)));

        return response()->json(['message' => 'Versión activada: la usan las próximas corridas de ai:classify', 'prompts' => $this->service->prompts()]);
    }

    public function diff(int $version)
    {
        $v = DB::table('ai_prompt_versions')->where('id', $version)->first();
        abort_unless($v, 404);
        $prev = DB::table('ai_prompt_versions')->where('prompt_id', $v->prompt_id)->where('version', '<', $v->version)->orderByDesc('version')->first();

        return response()->json(['version' => $v->version, 'previous' => $prev?->version,
            'diff' => ModuleService::diff((string) ($prev->body ?? ''), (string) $v->body)]);
    }

    /* ---------------- pruebas ---------------- */

    public function storeTest(Request $request)
    {
        $data = $request->validate(['version_id' => 'required|integer', 'sample_size' => 'required|integer|min:50|max:200']);
        $id = $this->attempt(fn () => $this->service->createTest($data['version_id'], $data['sample_size'], $this->user($request)));

        return response()->json(['message' => "Prueba #{$id} registrada: se ejecuta en la siguiente corrida de ai:classify "
            . "(jobs:run-all) o a mano con php artisan ai:prompt-test {$id}", 'tests' => $this->service->tests()]);
    }

    public function tests()
    {
        return response()->json($this->service->tests());
    }

    /* ---------------- revisión manual ---------------- */

    public function offerGroups(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(ModuleService::REVIEW_STATUSES)], 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);

        return response()->json($this->service->offerGroups($data['status'] ?? 'dudosa', $data['search'] ?? null, (int) ($data['page'] ?? 1)));
    }

    public function decideGroup(Request $request)
    {
        $data = $request->validate(['title_key' => 'required|string|size:32', 'from' => ['required', Rule::in(ModuleService::REVIEW_STATUSES)], 'to' => ['required', Rule::in(['ti', 'no_ti'])]]);
        $n = $this->attempt(fn () => $this->service->decideGroup($data['title_key'], $data['from'], $data['to'], $this->user($request)));

        return response()->json(['message' => "{$n} ofertas marcadas como " . ($data['to'] === 'ti' ? 'TI' : 'No TI') . ' (manual)']);
    }

    public function decideOffer(Request $request, int $offer)
    {
        $data = $request->validate(['to' => ['required', Rule::in(['ti', 'no_ti'])]]);
        $this->attempt(fn () => $this->service->decideOffer($offer, $data['to'], $this->user($request)));

        return response()->json(['message' => 'Oferta marcada como ' . ($data['to'] === 'ti' ? 'TI' : 'No TI') . ' (manual)']);
    }

    public function trends(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(ModuleService::TREND_STATUSES)], 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);

        return response()->json($this->service->trendList($data['status'] ?? 'dudosa', $data['search'] ?? null, (int) ($data['page'] ?? 1)));
    }

    public function decideTrend(Request $request, int $trend)
    {
        $data = $request->validate(['to' => ['required', Rule::in(['relevante', 'ruido'])]]);
        $this->attempt(fn () => $this->service->decideTrend($trend, $data['to'], $this->user($request)));

        return response()->json(['message' => 'Reporte marcado como ' . $data['to'] . ' (manual)']);
    }

    /** Errores de validación del servicio (RuntimeException) → 422 con el mensaje. */
    private function attempt(callable $fn)
    {
        try {
            return $fn();
        } catch (\RuntimeException $e) {
            abort(response()->json(['message' => $e->getMessage()], 422));
        }
    }

    private function user(Request $request): string
    {
        $u = $request->user();

        return mb_substr(trim(($u->name ?? '') . ' <' . ($u->email ?? $u->id ?? '') . '>'), 0, 150);
    }
}
