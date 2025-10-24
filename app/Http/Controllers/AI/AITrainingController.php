<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AITraining;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AITrainingController extends Controller
{
    /**
     * 📋 Listado de entrenamientos IA
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $topic = $request->get('topic');

        $trainings = AITraining::query()
            ->when($search, fn($q) =>
                $q->where('prompt', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhere('interpreter', 'like', "%$search%")
            )
            ->when($topic, fn($q) => $q->where('topic', $topic))
            ->orderBy('topic')
            ->orderByDesc('id')
            ->paginate(10);

        $topics = AITraining::select('topic')->distinct()->pluck('topic');

        // 🔹 JSON para peticiones AJAX
        if ($request->wantsJson()) {
            return response()->json([
                'trainings' => $trainings,
                'topics' => $topics
            ]);
        }

        // 🔹 Render para Inertia (primera carga)
        return Inertia::render('AITraining/AITrainingsIndex', [
            'trainings' => $trainings,
            'topics' => $topics
        ]);
    }

    /**
     * 🧩 Crear un nuevo entrenamiento IA
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic'           => 'required|string|max:150',
            'prompt'          => 'required|string',
            'interpreter'     => 'required|string',
            'component'       => 'nullable|string|max:150',
            'description'     => 'nullable|string',
            'tags'            => 'nullable|array',
            'is_active'       => 'boolean',
            'has_ai_response' => 'boolean',
        ]);

        $validated['tags'] = $validated['tags'] ?? [];
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['has_ai_response'] = $validated['has_ai_response'] ?? true;

        $training = AITraining::create($validated);

        return response()->json([
            'message' => '✅ Entrenamiento IA creado correctamente',
            'training' => $training
        ]);
    }

    /**
     * 📄 Mostrar un entrenamiento específico
     */
    public function show($id)
    {
        $training = AITraining::findOrFail($id);
        return response()->json(['training' => $training]);
    }

    /**
     * ✏️ Actualizar un entrenamiento existente
     */
    public function update(Request $request, $id)
    {
        $training = AITraining::findOrFail($id);

        $validated = $request->validate([
            'topic'           => 'required|string|max:150',
            'prompt'          => 'required|string',
            'interpreter'     => 'required|string',
            'component'       => 'nullable|string|max:150',
            'description'     => 'nullable|string',
            'tags'            => 'nullable|array',
            'is_active'       => 'boolean',
            'has_ai_response' => 'boolean',
        ]);

        $training->update($validated);

        return response()->json([
            'message' => '✅ Entrenamiento IA actualizado correctamente',
            'training' => $training
        ]);
    }

    /**
     * ❌ Eliminar un entrenamiento
     */
    public function destroy($id)
    {
        $training = AITraining::findOrFail($id);
        $training->delete();

        return response()->json(['message' => '🗑️ Entrenamiento IA eliminado correctamente']);
    }

    /**
     * 🔁 Alternar estado activo/inactivo
     */
    public function toggleActive($id)
    {
        $training = AITraining::findOrFail($id);
        $training->is_active = !$training->is_active;
        $training->save();

        return response()->json([
            'message' => $training->is_active
                ? '✅ Entrenamiento activado'
                : '🚫 Entrenamiento desactivado',
            'is_active' => $training->is_active
        ]);
    }

    /**
     * 🤖 Alternar uso de IA
     */
    public function toggleAI($id)
    {
        $training = AITraining::findOrFail($id);
        $training->has_ai_response = !$training->has_ai_response;
        $training->save();

        return response()->json([
            'message' => $training->has_ai_response
                ? '🤖 Respuesta IA activada'
                : '💤 Respuesta IA desactivada',
            'has_ai_response' => $training->has_ai_response
        ]);
    }

    /**
     * 🧬 Duplicar un entrenamiento
     */
    public function duplicate($id)
    {
        $original = AITraining::findOrFail($id);
        $copy = $original->replicate();
        $copy->prompt = '[Copia] ' . $original->prompt;
        $copy->is_active = false;
        $copy->save();

        return response()->json([
            'message' => '📋 Copia creada correctamente',
            'training' => $copy
        ]);
    }
}
