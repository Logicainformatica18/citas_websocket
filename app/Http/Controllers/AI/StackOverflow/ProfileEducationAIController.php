<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileEducationAIController extends Controller
{
    /**
     * 📊 Devuelve metadata para filtros dinámicos
     */
    public function metadata()
    {
        $cleanDistinct = fn($column) =>
            StackOverflowSurvey::select($column)
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column);

        return response()->json([
            'years'       => StackOverflowSurvey::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'countries'   => $cleanDistinct('country'),
            'remote_work' => $cleanDistinct('remote_work'),
            'employment'  => $cleanDistinct('employment'),
            'org_sizes'   => $cleanDistinct('org_size'),
            'industries'  => $cleanDistinct('industry'),
        ]);
    }

    /**
     * 📈 Devuelve datos del nivel educativo
     */
    public function getData(Request $request)
    {
        $year      = $request->get('year', 2024);
        $countries = $request->get('countries', []);
        $mode      = $request->get('mode', 'general');

        $baseQuery = StackOverflowSurvey::query()
            ->where('year', $year)
            ->whereNotNull('ed_level')
            ->where('ed_level', '<>', '');

        if (!empty($countries)) {
            $baseQuery->whereIn('country', $countries);
        }

        $data = match ($mode) {
            'by_country' => $baseQuery
                ->select('country', 'ed_level', DB::raw('COUNT(*) as total'))
                ->whereNotNull('country')->where('country', '<>', '')
                ->groupBy('country', 'ed_level')
                ->orderBy('country')
                ->get(),
            'by_remote' => $baseQuery
                ->select('remote_work', 'ed_level', DB::raw('COUNT(*) as total'))
                ->whereNotNull('remote_work')->where('remote_work', '<>', '')
                ->groupBy('remote_work', 'ed_level')
                ->orderBy('remote_work')
                ->get(),
            'by_employment' => $baseQuery
                ->select('employment', 'ed_level', DB::raw('COUNT(*) as total'))
                ->whereNotNull('employment')->where('employment', '<>', '')
                ->groupBy('employment', 'ed_level')
                ->orderBy('employment')
                ->get(),
            'by_orgsize' => $baseQuery
                ->select('org_size', 'ed_level', DB::raw('COUNT(*) as total'))
                ->whereNotNull('org_size')->where('org_size', '<>', '')
                ->groupBy('org_size', 'ed_level')
                ->orderBy('org_size')
                ->get(),
            'by_industry' => $baseQuery
                ->select('industry', 'ed_level', DB::raw('COUNT(*) as total'))
                ->whereNotNull('industry')->where('industry', '<>', '')
                ->groupBy('industry', 'ed_level')
                ->orderBy('industry')
                ->get(),
            default => $baseQuery
                ->select('ed_level', DB::raw('COUNT(*) as total'))
                ->groupBy('ed_level')
                ->orderByRaw('COUNT(*) DESC')
                ->get(),
        };

        $data = $data->filter(fn($d) =>
            ($d->total ?? 0) > 0 &&
            !empty($d->ed_level ?? $d->country ?? $d->remote_work ?? $d->employment ?? $d->org_size ?? $d->industry)
        )->values();

        return response()->json([
            'mode' => $mode,
            'year' => $year,
            'total' => $baseQuery->count(),
            'education_levels' => $data,
        ]);
    }
}
