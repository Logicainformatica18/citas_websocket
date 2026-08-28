<?php

namespace App\Http\Controllers;

use App\Models\SelectionDetail;
use App\Models\Survey;
use App\Models\SurveyClient;
use App\Models\SurveyDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SurveyClientController extends Controller
{
    public function show($id)
    {
        $survey = Survey::findOrFail($id);
        $questions = SurveyDetail::with('selection.details')
            ->where('survey_id', $survey->id)
            ->where('visible', 'yes')
            ->orderBy('created_at')
            ->get();

        return inertia('surveys/public', [
            'survey' => $survey,
            'questions' => $questions,
        ]);
    }

    public function store(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);
        $data = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'survey_detail_id' => 'required|integer|exists:survey_details,id',
            'type' => 'required|in:short_answer,number,email,date,file,multiple_option,selection',
            'answer' => 'nullable|string|max:65535',
            'option' => 'nullable|string|max:255',
            'selection_detail_id' => 'nullable|integer|exists:selection_details,id',
        ]);
        $question = SurveyDetail::where('survey_id', $survey->id)->findOrFail($data['survey_detail_id']);
        $response = [
            'survey_detail_id' => $question->id,
            'client_id' => $data['client_id'],
        ];

        if ($question->type === 'multiple_option') {
            $selected = $data['option'] ?? null;
            $response['option'] = $selected === null ? [] : [$selected];
            if ($question->evaluate === 'yes') {
                $response['answer'] = $selected !== null && ($selected === $question->correct || $selected === (string) ((int) $question->correct - 1)) ? 2 : 0;
            } else {
                $response['answer'] = $selected;
            }
        } elseif ($question->type === 'selection') {
            $selectionDetail = SelectionDetail::where('selection_id', $question->selection_id)
                ->findOrFail($data['selection_detail_id'] ?? 0);
            $response['selection_detail_id'] = $selectionDetail->id;
            $response['answer'] = (string) $selectionDetail->id;
        } elseif ($question->type === 'file' && $request->hasFile('answer')) {
            $response['answer'] = $request->file('answer')->store('survey-answers', 'public');
        } else {
            $response['answer'] = $data['answer'] ?? null;
        }

        SurveyClient::create($response);

        return response()->json(['message' => 'Respuesta guardada']);
    }

    public function associated($id)
    {
        return response()->json([
            'selection_details' => SelectionDetail::where('associate_detail_id', $id)
                ->orderBy('description')->get(),
        ]);
    }
}