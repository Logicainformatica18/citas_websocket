<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileEducationAIController extends Controller
{
    /**
     * 📊 Devuelve metadata para filtros dinámicos (años y países)
     */
    public function metadata()
    {
        return response()->json([
            'years' => StackOverflowSurvey::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'countries' => StackOverflowSurvey::select('country')
                ->whereNotNull('country')
                ->distinct()
                ->orderBy('country')
                ->pluck('country'),
            'remote_work' => StackOverflowSurvey::select('remote_work')
                ->whereNotNull('remote_work')
                ->distinct()
                ->orderBy('remote_work')
                ->pluck('remote_work'),
            'employment' => StackOverflowSurvey::select('employment')
                ->whereNotNull('employment')
                ->distinct()
                ->orderBy('employment')
                ->pluck('employment'),
            'org_sizes' => StackOverflowSurvey::select('org_size')
                ->whereNotNull('org_size')
                ->distinct()
                ->orderBy('org_size')
                ->pluck('org_size'),
            'industries' => StackOverflowSurvey::select('industry')
                ->whereNotNull('industry')
                ->distinct()
                ->orderBy('industry')
                ->pluck('industry'),
        ]);
    }

    /**
     * 📈 Devuelve datos del nivel educativo (modo general, país o modalidad laboral)
     */
    public function getData(Request $request)
    {
        $year = $request->get('year', 2024);
        $countries = $request->get('countries', []);
        $mode = $request->get('mode', 'general'); // general | by_country | by_remote | by_employment | by_orgsize | by_industry

        // Filtro base por año
        $baseQuery = StackOverflowSurvey::query()->where('year', $year);
        if (!empty($countries)) {
            $baseQuery->whereIn('country', $countries);
        }

        switch ($mode) {
            case 'by_country':
                // 🔹 Distribución educativa por país (Top 10 países con más registros)
                $topCountries = StackOverflowSurvey::select('country')
                    ->where('year', $year)
                    ->whereNotNull('country')
                    ->groupBy('country')
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(10)
                    ->pluck('country');

                $data = StackOverflowSurvey::select('country', 'ed_level', DB::raw('COUNT(*) as total'))
                    ->where('year', $year)
                    ->whereIn('country', $topCountries)
                    ->whereNotNull('ed_level')
                    ->groupBy('country', 'ed_level')
                    ->orderBy('country')
                    ->get();
                break;

            case 'by_remote':
                // 🔹 Distribución educativa por modalidad laboral
                $data = $baseQuery
                    ->select('remote_work', 'ed_level', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('ed_level')
                    ->groupBy('remote_work', 'ed_level')
                    ->orderBy('remote_work')
                    ->get();
                break;

            case 'by_employment':
                // 🔹 Distribución educativa por tipo de empleo
                $data = $baseQuery
                    ->select('employment', 'ed_level', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('ed_level')
                    ->groupBy('employment', 'ed_level')
                    ->orderBy('employment')
                    ->get();
                break;

            case 'by_orgsize':
                // 🔹 Distribución educativa por tamaño de organización
                $data = $baseQuery
                    ->select('org_size', 'ed_level', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('ed_level')
                    ->groupBy('org_size', 'ed_level')
                    ->orderBy('org_size')
                    ->get();
                break;

            case 'by_industry':
                // 🔹 Distribución educativa por industria
                $data = $baseQuery
                    ->select('industry', 'ed_level', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('ed_level')
                    ->groupBy('industry', 'ed_level')
                    ->orderBy('industry')
                    ->get();
                break;

            default:
                // 🔹 Distribución general (como tu gráfico actual)
                $data = $baseQuery
                    ->select('ed_level', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('ed_level')
                    ->groupBy('ed_level')
                    ->orderByDesc('total')
                    ->get();
                break;
        }

        return response()->json([
            'mode' => $mode,
            'year' => $year,
            'total' => $baseQuery->count(),
            'education_levels' => $data,
        ]);
    }
}
