<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

class TechnologiesController extends Controller
{
    public function getData(array $instruction)
    {
        // 🔹 Agrupamos ofertas por campo "technologies" (ejemplo si lo guardas como texto o JSON)
        $query = JobOffer::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(technologies, '$[0]')) as tech"))
            ->selectRaw('COUNT(*) as total')
            ->groupBy('tech')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'action'  => 'technologies',
            'results' => $query,
            'message' => "🚀 Aquí tienes las tecnologías más demandadas en las ofertas recientes.",
        ]);
    }
}
