<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $surveys = Survey::with('created_bys')->orderBy('id', 'desc')->paginate(10);

        if ($request->wantsJson()) {
            return response()->json(['surveys' => $surveys]);
        }

        return Inertia::render('surveys/index', ['surveys' => $surveys]);
    }

    public function fetchPaginated()
    {
        return response()->json([
            'surveys' => Survey::with('created_bys')->orderBy('id', 'desc')->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $data['front_page'] = $this->storeFrontPage($request);
        $survey = Survey::create($data);

        return response()->json(['message' => 'Encuesta creada', 'survey' => $survey->load('created_bys')]);
    }

    public function show($id)
    {
        return response()->json(['survey' => Survey::with('created_bys')->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $frontPage = $this->storeFrontPage($request);

        if ($frontPage !== null) {
            if ($survey->front_page) {
                Storage::disk('public')->delete($survey->front_page);
            }
            $data['front_page'] = $frontPage;
        }

        $survey->update($data);

        return response()->json(['message' => 'Encuesta actualizada', 'survey' => $survey->load('created_bys')]);
    }

    public function destroy($id)
    {
        Survey::findOrFail($id)->delete();
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

    private function validated(Request $request): array
    {
        return $request->validate([
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
            'url' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'state' => 'required|in:public,private',
        ], [
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede superar los 255 caracteres.',
            'visible.boolean' => 'El campo visible debe ser verdadero o falso.',
            'email_confirmation.boolean' => 'El campo de confirmación por email debe ser verdadero o falso.',
            'date_end.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'front_page.image' => 'El archivo debe ser una imagen.',
            'state.required' => 'Debes elegir si la encuesta es pública o privada.',
            'state.in' => 'El estado debe ser público o privado.',
        ]);
    }

    private function storeFrontPage(Request $request): ?string
    {
        return $request->hasFile('front_page')
            ? $request->file('front_page')->store('imageusers', 'public')
            : null;
    }
}