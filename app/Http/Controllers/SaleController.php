<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Cliente;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        if ($request->wantsJson()) {
            $sales = Sale::with(['client', 'project'])
                ->orderByDesc('id')
                ->get()
                ->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'code' => $sale->code,
                        'holder' => $sale->holder,
                        'stage' => $sale->stage,
                        'mz_lote' => $sale->mz_lote,
                        'state' => $sale->state,
                        'cliente' => $sale->client?->Razon_Social,
                        'proyecto' => $sale->project?->descripcion,
                    ];
                });

            return response()->json($sales);
        }

        $sales = Sale::with(['client', 'project'])
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function ($sale) {
                return [
                    'id' => $sale->id,
                    'code' => $sale->code,
                    'holder' => $sale->holder,
                    'stage' => $sale->stage,
                    'mz_lote' => $sale->mz_lote,
                    'state' => $sale->state,
                    'cliente' => $sale->client?->Razon_Social,
                    'proyecto' => $sale->project?->descripcion,
                ];
            });

        return Inertia::render('sales/index', [
            'sales' => $sales,
        ]);
    }


public function fetchPaginated()
{
    return response()->json(
        Sale::with(['client', 'project']) // 👈 Añadido with()
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function ($sale) {
                return [
                    'id' => $sale->id,
                    'code' => $sale->code,
                    'holder' => $sale->holder,
                    'stage' => $sale->stage,
                    'mz_lote' => $sale->mz_lote,
                    'state' => $sale->state,
                    'cliente' => $sale->client->Razon_Social ?? null,
                    'proyecto' => $sale->project->descripcion ?? null,
                ];
            })
    );
}

 public function store(Request $request)
{
    // ✅ 1. Validar los datos recibidos
    $validated = $request->validate([
        'id_cliente'  => 'nullable|integer|exists:clientes,id_cliente',
        'code'        => 'nullable|string|max:255',
        'holder'      => 'nullable|string|max:255',
        'stage'       => 'nullable|string|max:255',
        'mz_lote'     => 'nullable|string|max:255',
        'state'       => 'nullable|string|max:255',
        'project_id'  => 'nullable|integer|exists:proyecto,id_proyecto',
    ]);

    // 🛠️ 2. Crear la venta manualmente, campo por campo
    $sale = new Sale();
    $sale->id_cliente  = $request->input('id_cliente');
    $sale->code        = $request->input('code');
    $sale->holder      = $request->input('holder');
    $sale->stage       = $request->input('stage');
    $sale->mz_lote     = $request->input('mz_lote');
    $sale->state       = $request->input('state');
    $sale->project_id  = $request->input('project_id');
    $sale->save();

    // 🔄 3. Cargar relaciones para enviar datos útiles al frontend
    $sale->load(['client', 'project']);

    // 📤 4. Devolver respuesta detallada
    return response()->json([
        'message' => '✅ Venta registrada correctamente',
        'sale' => [
            'id'       => $sale->id,
            'code'     => $sale->code,
            'holder'   => $sale->holder,
            'stage'    => $sale->stage,
            'mz_lote'  => $sale->mz_lote,
            'state'    => $sale->state,
            'cliente'  => $sale->client->Razon_Social ?? null,
            'proyecto' => $sale->project->descripcion ?? null,
        ]
    ]);
}


    public function update(Request $request, $id)
    {
        $sale = Sale::findOrFail($id);

        $validated = $request->validate([
            'id_cliente' => 'nullable|integer|exists:clientes,id_cliente',
            'code' => 'nullable|string|max:255',
            'holder' => 'nullable|string|max:255',
            'stage' => 'nullable|string|max:255',
            'mz_lote' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer|exists:proyecto,id_proyecto',
        ]);

        $sale->update($validated);

        return response()->json(['message' => '✅ Updated', 'sale' => $sale]);
    }

    public function show($id)
    {
        $sale = Sale::with(['client', 'project'])->findOrFail($id);

        return response()->json([
            'sale' => [
                'id' => $sale->id,
                'code' => $sale->code,
                'holder' => $sale->holder,
                'stage' => $sale->stage,
                'mz_lote' => $sale->mz_lote,
                'state' => $sale->state,
                'cliente' => $sale->client?->Razon_Social,
                'proyecto' => $sale->project?->descripcion,
            ],
        ]);
    }

    public function destroy($id)
    {
        $sale = Sale::findOrFail($id);
        $sale->delete();

        return response()->json(['message' => '✅ Deleted successfully']);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Sale::whereIn('id', $ids)->delete();

        return response()->json(['message' => '✅ Deleted']);
    }
}
