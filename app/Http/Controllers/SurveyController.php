<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $surveys = $this->listado()->paginate(10);

        if ($request->wantsJson()) {
            return response()->json(['surveys' => $surveys]);
        }

        return Inertia::render('surveys/index', ['surveys' => $surveys]);
    }

    public function fetchPaginated()
    {
        return response()->json(['surveys' => $this->listado()->paginate(10)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $data['front_page'] = $this->storeFrontPage($request);
        $survey = Survey::create($data);

        return response()->json([
            'message' => 'Encuesta creada',
            'survey' => $this->listado()->findOrFail($survey->id),
        ]);
    }

    public function show($id)
    {
        return response()->json(['survey' => Survey::with('created_bys')->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);
        $data = $this->validated($request, $survey->id);
        $data['created_by'] = Auth::id();
        $frontPage = $this->storeFrontPage($request);

        if ($frontPage !== null) {
            if ($survey->front_page) {
                Storage::disk('public')->delete($survey->front_page);
            }
            $data['front_page'] = $frontPage;
        }

        $survey->update($data);

        return response()->json([
            'message' => 'Encuesta actualizada',
            'survey' => $this->listado()->findOrFail($survey->id),
        ]);
    }

    public function destroy($id)
    {
        $survey = Survey::findOrFail($id);

        // Las FK de survey_details.survey_id y survey_clients.survey_detail_id
        // están en RESTRICT: sin este chequeo el delete revienta con un 1451 y
        // el front recibe una excepción en vez de un mensaje.
        $preguntas = DB::table('survey_details')->where('survey_id', $survey->id)->count();

        if ($preguntas > 0) {
            $respuestas = DB::table('survey_clients')
                ->join('survey_details', 'survey_details.id', '=', 'survey_clients.survey_detail_id')
                ->where('survey_details.survey_id', $survey->id)
                ->count();

            return response()->json([
                'message' => "No se puede eliminar: la encuesta tiene {$preguntas} preguntas y {$respuestas} respuestas asociadas. Eliminá primero las preguntas.",
            ], 409);
        }

        if ($survey->front_page) {
            Storage::disk('public')->delete($survey->front_page);
        }

        $survey->delete();

        return response()->json(['message' => 'Encuesta eliminada']);
    }

    public function notify($id)
    {
        $survey = Survey::with('created_bys')->findOrFail($id);

        if (!$survey->email_confirmation || !$survey->created_bys?->email) {
            return response()->json(['message' => 'La encuesta no tiene confirmación por email activa']);
        }

        Mail::raw('La encuesta "' . $survey->title . '" tiene nuevas respuestas.', function ($message) use ($survey) {
            $message->to($survey->created_bys->email)
                ->subject('Nuevas respuestas en la encuesta');
        });

        return response()->json(['message' => 'Notificación enviada']);
    }

    /**
     * Listado con los dos conteos que muestra la tabla del front.
     *
     * Van como subconsultas correlacionadas y no como withCount para no
     * depender de relaciones en el modelo, y para poder filtrar por
     * completed_at. Es una sola query, sin N+1.
     *
     * Criterio de los conteos, el mismo que usa el dashboard:
     * solo participantes que terminaron (completed_at IS NOT NULL).
     * Las sesiones abandonadas no cuentan.
     */
    private function listado(): Builder
    {
        $respuestas = DB::table('survey_clients')
            ->join('survey_details', 'survey_details.id', '=', 'survey_clients.survey_detail_id')
            ->join('clients', 'clients.id', '=', 'survey_clients.client_id')
            ->whereColumn('survey_details.survey_id', 'surveys.id')
            ->whereNotNull('clients.completed_at')
            ->where('survey_clients.answer', '<>', 'no_respondido')
            ->selectRaw('count(*)');

        $participantes = DB::table('clients')
            ->join('survey_clients', 'survey_clients.client_id', '=', 'clients.id')
            ->join('survey_details', 'survey_details.id', '=', 'survey_clients.survey_detail_id')
            ->whereColumn('survey_details.survey_id', 'surveys.id')
            ->whereNotNull('clients.completed_at')
            ->selectRaw('count(distinct clients.id)');

        return Survey::query()
            ->with('created_bys')
            ->select('surveys.*')
            ->selectSub($respuestas, 'answers_count')
            ->selectSub($participantes, 'participants_count')
            ->orderBy('surveys.id', 'desc');
    }

    private function validated(Request $request, ?int $surveyId = null): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'detail' => 'nullable|string',
            'front_page' => 'nullable|image|max:5120',
            'visible' => 'nullable|boolean',
            'email_confirmation' => 'nullable|boolean',
            'password' => 'nullable|string|max:255',
            'pollster_r' => 'nullable|integer',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'type' => 'nullable|string|max:255',
            'state' => 'required|in:public,private',
        ];

        $urlRule = 'nullable|string|max:100|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
        if ($surveyId !== null) {
            $urlRule .= '|unique:surveys,url,' . $surveyId;
        } else {
            $urlRule .= '|unique:surveys,url';
        }
        $rules['url'] = $urlRule;

        return $request->validate($rules, [
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede superar los 255 caracteres.',
            'visible.boolean' => 'El campo visible debe ser verdadero o falso.',
            'email_confirmation.boolean' => 'El campo de confirmación por email debe ser verdadero o falso.',
            'date_end.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'front_page.image' => 'El archivo debe ser una imagen.',
            'state.required' => 'Debes elegir si la encuesta es pública o privada.',
            'state.in' => 'El estado debe ser público o privado.',
            'url.regex' => 'El enlace solo puede contener minúsculas, números y guiones.',
            'url.unique' => 'Ya existe otra encuesta con ese enlace.',
        ]);
    }

    private function storeFrontPage(Request $request): ?string
    {
        return $request->hasFile('front_page')
            ? $request->file('front_page')->store('imageusers', 'public')
            : null;
    }
}
