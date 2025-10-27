<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\AI\WorkModeAIController;
use App\Http\Controllers\AI\CityDemandAIController;

class DashboardController extends Controller
{
    public function index()
    {


        // 🔹 Datos iniciales para los cards
        try {
           // $workmode = app(WorkModeAIController::class)->index()->getData(true);


        } catch (\Throwable $e) {
            Log::error("❌ Error obteniendo datos iniciales en Dashboard", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return Inertia::render('dashboards/Dashboard', [
            'initialData' => [
                'workmode'   => $workmode ?? null,
                 'cityDemand' => $cityDemand ?? null,
            ]
        ]);
    }
}
