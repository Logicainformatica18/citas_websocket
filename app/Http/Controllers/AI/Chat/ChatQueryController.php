<?php

namespace App\Http\Controllers\AI\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatQueryController extends Controller
{
    public function interpret(Request $request)
    {
        $question = strtolower(trim($request->input('question')));

        $match = DB::table('report_queries')
            ->whereRaw('LOWER(question) LIKE ?', ["%{$question}%"])
            ->orWhereRaw('LOWER(description) LIKE ?', ["%{$question}%"])
            ->orWhereRaw('LOWER(category) LIKE ?', ["%{$question}%"])
            ->first();

        if (!$match) {
            return response()->json(['message' => 'No se encontró una métrica relacionada.']);
        }

        return response()->json([
            'message' => 'Consulta encontrada.',
            'category' => $match->category,
            'component' => $match->component,
            'interpreter' => $match->interpreter,
            'description' => $match->description,
        ]);
    }
}
