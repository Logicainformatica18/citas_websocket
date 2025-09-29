<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

class WorkModeController extends Controller
{
    public function getData(array $instruction)
    {
        $query = JobOffer::select('modality', DB::raw('COUNT(*) as total'))
            ->groupBy('modality')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'action'  => 'workmode',
            'results' => $query,
            'message' => "💼 Distribución de modalidades de trabajo encontradas en las ofertas.",
        ]);
    }
}
