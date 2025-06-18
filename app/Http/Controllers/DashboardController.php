<?php

namespace App\Http\Controllers;

use App\Models\Support;
use App\Models\SupportDetail;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Total de tickets (soportes)
        $totalSupports = Support::count();

        // Total de detalles
        $totalDetails = SupportDetail::count();

        // Cantidad por tipo global (Simple / Múltiple)
        $statusGlobal = Support::select('status_global', DB::raw('COUNT(*) as count'))
            ->groupBy('status_global')
            ->pluck('count', 'status_global');

        // Cantidad por prioridad
        $priorities = SupportDetail::select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // Cantidad por estado interno (nombre del estado)
        $internalStates = SupportDetail::select('internal_states.description', DB::raw('COUNT(*) as count'))
            ->leftJoin('internal_states', 'support_details.internal_state_id', '=', 'internal_states.id')
            ->groupBy('internal_states.description')
            ->pluck('count', 'internal_states.description');

        // Cantidad por estado externo
        $externalStates = SupportDetail::select('external_states.description', DB::raw('COUNT(*) as count'))
            ->leftJoin('external_states', 'support_details.external_state_id', '=', 'external_states.id')
            ->groupBy('external_states.description')
            ->pluck('count', 'external_states.description');

        return Inertia::render('dashboards/index', [
            'stats' => [
                'totalSupports'   => $totalSupports,
                'totalDetails'    => $totalDetails,
                'statusGlobal'    => $statusGlobal,
                'priorities'      => $priorities,
                'internalStates'  => $internalStates,
                'externalStates'  => $externalStates,
            ],
        ]);
    }
}
