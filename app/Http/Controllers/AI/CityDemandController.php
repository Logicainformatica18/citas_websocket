<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Http\Request;

class CityDemandController extends Controller
{
    public function getData(array $instruction)
    {
        // Ejemplo: contar ofertas por ciudad
        $query = JobOffer::selectRaw('location, COUNT(*) as total')
            ->groupBy('location')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'action' => 'city_demand',
            'results' => $query,
            'message' => "📊 Aquí tienes la demanda potencial por ciudad/país.",
        ]);
    }
}
