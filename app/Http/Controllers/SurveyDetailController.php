<?php

namespace App\Http\Controllers;

use App\Models\Selection;
use App\Models\Survey;
use App\Models\SurveyDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SurveyDetailController extends Controller
{
    public function index($id)
    {
        $survey = Survey::findOrFail($id);
        return Inertia::render('surveys/questions', [
            'survey' => $survey,
            'selections' => Selection::orderBy('created_at')->get(),
            'questions' => $this->questions($survey)->paginate(10),
        ]);
    }

    public function fetchPaginated($id)
    {
        $survey = Survey::findOrFail($id);
        return response()->json([
            'survey' => $survey,
            'questions' => $this->questions($survey)->paginate(10),
            'selections' => Selection::orderBy('created_at')->get(),
        ]);
    }

    public function store(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);
        $data = $this->prepared($request);
        $data['survey_id'] = $survey->id;
        $question = SurveyDetail::create($data);
        return response()->json(['message' => 'Pregunta creada', 'question' => $question->load('selection')]);
    }

    public function update(Request $request, $id)
    {
        $question = SurveyDetail::findOrFail($id);
        $question->update($this->prepared($request));
        return response()->json(['message' => 'Pregunta actualizada', 'question' => $question->load('selection')]);
    }

    public function destroy($id)
    {
        $question = SurveyDetail::findOrFail($id);
        DB::table('survey_clients')->where('survey_detail_id', $question->id)->delete();
        $question->delete();
        return response()->json(['message' => 'Pregunta eliminada']);
    }

    private function questions(Survey $survey)
    {
        return SurveyDetail::with('selection')->where('survey_id', $survey->id)
            ->where('visible', 'yes')->orderBy('created_at');
    }

    private function prepared(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'question' => 'required|string|max:255',
            'detail' => 'nullable|string|max:255',
            'detail_2' => 'nullable|string|max:255',
            'detail_3' => 'nullable|string|max:255',
            'type' => 'required|in:short_answer,number,email,date,file,multiple_option,selection',
            'option' => 'nullable|array|max:10',
            'option.*' => 'nullable|string|max:255',
            'correct' => 'nullable|string|max:255',
            'point' => 'nullable|string',
            'requerid' => 'nullable|in:yes,not',
            'evaluate' => 'nullable|in:yes,not',
            'initialize' => 'nullable|in:yes,not',
            'category' => 'nullable|string|max:255',
            'enumeration' => 'nullable|string|max:255',
            'visible' => 'nullable|in:yes,not',
            'state' => 'nullable|string|max:255',
            'selection_id' => 'nullable|integer|exists:selections,id',
        ]);

        $data['question'] = Str::upper($data['question']);
        $data['option'] = $data['type'] === 'multiple_option'
            ? collect($request->input('option', []))->map(fn ($option) => trim((string) $option))
                ->filter(fn ($option) => $option !== '')->values()->all()
            : [];
        if ($data['type'] !== 'multiple_option') {
            $data['correct'] = null;
        }
        if ($data['type'] !== 'selection') {
            $data['selection_id'] = null;
        }
        $data['enumeration'] = $data['enumeration'] ?? '0';
        $data['initialize'] = $data['initialize'] ?? 'not';
        $data['category'] = $data['category'] ?? 'all';
        $data['evaluate'] = $data['evaluate'] ?? 'not';
        $data['visible'] = $data['visible'] ?? 'yes';

        return $data;
    }
}