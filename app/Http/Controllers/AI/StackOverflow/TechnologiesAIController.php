<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;

class TechnologiesAIController extends Controller
{
    public function getData(Request $r)
    {
        $year = $r->get('year', 2024);
        $country = $r->get('country');

        $query = StackOverflowSurvey::where('year', $year);
        if ($country) $query->where('country', $country);

        $languages = $query->whereNotNull('language_have_worked_with')
            ->pluck('language_have_worked_with')
            ->flatMap(fn($l) => collect(explode(';', $l))->map('trim')->filter())
            ->countBy()->sortDesc()->take(10);

        return response()->json([
            'top_languages' => $languages,
        ]);
    }
}
