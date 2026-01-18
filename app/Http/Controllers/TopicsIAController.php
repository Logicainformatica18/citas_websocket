<?php

namespace App\Http\Controllers;

use App\Models\TrendTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Services\RunTrendTopicService;
use App\Jobs\RunTrendTopicJob;

class TopicsIAController extends Controller
{
    /* ======================================================
     * 📄 Listado general (Inertia)
     ====================================================== */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $intent = $request->get('intent');

        $topics = TrendTopic::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('topic_name', 'like', "%{$search}%")
                      ->orWhere('search_query', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($intent, fn ($q) => $q->where('intent', $intent))
            ->orderBy('active', 'desc')
            ->orderBy('last_run_status', 'asc')
            ->orderBy('fail_count', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('topicsIA/Index', [
            'topics' => $topics->through(fn ($t) => [
                'id'                   => $t->id,
                'topic_name'           => $t->topic_name,
                'topic_slug'           => $t->topic_slug,
                'search_query'         => $t->search_query,

                // 🔥 NUEVOS
                'intent'               => $t->intent,
                'execution_mode'       => $t->execution_mode,
                'last_run_status'      => $t->last_run_status,
                'last_run_message'     => $t->last_run_message,

                'category'             => $t->category,
                'subcategory'          => $t->subcategory,
                'importance_weight'    => $t->importance_weight,

                'active'               => $t->active,
                'fail_count'           => $t->fail_count,
                'last_fail_at'         => optional($t->last_fail_at)->format('Y-m-d H:i'),
                'success_count'        => $t->success_count,
                'last_success_at'      => optional($t->last_success_at)->format('Y-m-d H:i'),
                'auto_disabled_reason' => $t->auto_disabled_reason,
                'min_required_results' => $t->min_required_results,

                'created_at'           => optional($t->created_at)->format('Y-m-d'),
            ]),

            'filters' => [
                'search' => $search,
                'intent' => $intent,
            ],
        ]);
    }

    /* ======================================================
     * 📄 API JSON paginada
     ====================================================== */
 public function fetchPaginated(Request $request)
{
    $search = $request->get('search');
    $intent = $request->get('intent');

    $topics = TrendTopic::query()
        ->when($search, function ($q) use ($search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('topic_name', 'like', "%{$search}%")
                   ->orWhere('search_query', 'like', "%{$search}%");
            });
        })
        ->when($intent, fn ($q) => $q->where('intent', $intent))
        ->orderBy('active', 'desc')
        ->paginate(10)
        ->withQueryString();

    // 🔥 TRANSFORMAR SOLO LOS ITEMS
    $topics->getCollection()->transform(fn ($t) => [
        'id'                   => $t->id,
        'topic_name'           => $t->topic_name,
        'topic_slug'           => $t->topic_slug,
        'search_query'         => $t->search_query,

        'intent'               => $t->intent,
        'execution_mode'       => $t->execution_mode,
        'last_run_status'      => $t->last_run_status,
        'last_run_message'     => $t->last_run_message,

        'category'             => $t->category,
        'subcategory'          => $t->subcategory,
        'importance_weight'    => $t->importance_weight,
        'min_required_results' => $t->min_required_results,

        'active'               => $t->active,
        'fail_count'           => $t->fail_count,
        'success_count'        => $t->success_count,
        'last_fail_at'         => optional($t->last_fail_at)->format('Y-m-d H:i'),
        'last_success_at'      => optional($t->last_success_at)->format('Y-m-d H:i'),

        'created_at'           => optional($t->created_at)->format('Y-m-d'),
    ]);

    return response()->json($topics);
}


    /* ======================================================
     * 🆕 Crear Topic IA
     ====================================================== */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic_name'            => 'required|string|max:255',
            'search_query'          => 'required|string',
            'intent'                => 'required|in:certification,technology_trend,skill,workforce,mixed',
            'category'              => 'nullable|string|max:150',
            'subcategory'           => 'nullable|string|max:150',
            'importance_weight'     => 'nullable|integer|min:1|max:10',
            'min_required_results'  => 'nullable|integer|min:1|max:10',
        ]);

        return DB::transaction(function () use ($validated) {

            $validated['topic_slug']     = Str::slug($validated['topic_name']);
            $validated['active']         = 1;
            $validated['execution_mode'] = 'manual';
            $validated['last_run_status'] = 'idle';

            $topic = TrendTopic::create($validated);

            return response()->json([
                'message' => '✅ Topic IA creado correctamente.',
                'topic'   => $topic,
            ], 201);
        });
    }

    /* ======================================================
     * ✏️ Actualizar Topic IA
     ====================================================== */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'topic_name'            => 'required|string|max:255',
            'search_query'          => 'required|string',
            'intent'                => 'required|in:certification,technology_trend,skill,workforce,mixed',
            'category'              => 'nullable|string|max:150',
            'subcategory'           => 'nullable|string|max:150',
            'importance_weight'     => 'nullable|integer|min:1|max:10',
            'min_required_results'  => 'nullable|integer|min:1|max:10',
            'active'                => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($validated, $request, $id) {

            $topic = TrendTopic::findOrFail($id);

            $updateData = [
                'topic_name'           => $validated['topic_name'],
                'topic_slug'           => Str::slug($validated['topic_name']),
                'search_query'         => $validated['search_query'],
                'intent'               => $validated['intent'],
                'category'             => $validated['category'] ?? null,
                'subcategory'          => $validated['subcategory'] ?? null,
                'importance_weight'    => $validated['importance_weight'] ?? 1,
                'min_required_results' => $validated['min_required_results'] ?? 3,
            ];

            if ($request->has('active')) {
                $updateData['active'] = $validated['active'];
            }

            $topic->update($updateData);

            return response()->json([
                'message' => '✅ Topic IA actualizado correctamente.',
                'topic'   => $topic,
            ]);
        });
    }

    public function status($id)
{
    $topic = TrendTopic::findOrFail($id);

    return response()->json([
        'last_run_status'  => $topic->last_run_status,
        'last_run_message' => $topic->last_run_message,
        'fail_count'       => $topic->fail_count,
        'success_count'    => $topic->success_count,
        'last_success_at'  => $topic->last_success_at,
        'last_fail_at'     => $topic->last_fail_at,
    ]);
}
public function run(int $id)
{
    $topic = TrendTopic::findOrFail($id);

    // 🔥 permitir re-ejecución si está colgado
    if ($topic->last_run_status === 'running') {

        // si lleva más de 5 min, se asume colgado
        if ($topic->updated_at->diffInMinutes(now()) < 5) {
            return response()->json([
                'status' => 'already_running'
            ], 200);
        }
    }

    $topic->update([
        'last_run_status'  => 'queued',
        'last_run_message' => 'Encolado para ejecución',
    ]);

    RunTrendTopicJob::dispatch($topic->id);

    return response()->json(['status' => 'queued']);
}




    /* ======================================================
     * 🗑️ Eliminar Topic IA
     ====================================================== */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {

            $topic = TrendTopic::findOrFail($id);
            $topic->delete();

            return response()->json([
                'message' => '🗑️ Topic IA eliminado correctamente.',
            ]);
        });
    }

    /* ======================================================
     * 🔄 Activar / Desactivar manualmente
     ====================================================== */
    public function toggle($id, Request $request)
    {
        $topic = TrendTopic::findOrFail($id);
        $topic->active = $request->active;
        $topic->save();

        return response()->json(['success' => true]);
    }

    /* ======================================================
     * 🔧 Reactivar topic desactivado por IA
     ====================================================== */
    public function reactivate($id)
    {
        $topic = TrendTopic::findOrFail($id);

        $topic->active = 1;
        $topic->fail_count = 0;
        $topic->auto_disabled_reason = null;
        $topic->last_fail_at = null;
        $topic->last_run_status = 'idle';
        $topic->last_run_message = null;

        $topic->save();

        return response()->json([
            'message' => '🔄 Topic IA reactivado correctamente.',
            'topic'   => $topic
        ]);
    }
}
