<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

class RolesController extends Controller
{
    public function getData(array $instruction)
    {
        // 🔹 Agrupamos por title (roles más comunes)
        $query = JobOffer::select('title', DB::raw('COUNT(*) as total'))
            ->groupBy('title')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'action'  => 'roles',
            'results' => $query,
            'message' => "👔 Aquí tienes los roles más solicitados en las ofertas.",
        ]);
    }
}
