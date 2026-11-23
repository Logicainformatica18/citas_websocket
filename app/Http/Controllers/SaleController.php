<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "Detalle Cliente": lotes (ventas) de cada cliente.
 * mz_lote se guarda con el formato "Mz. X Lt. Y" (lo arma el formulario a
 * partir de los campos Manzana y Lote).
 */
class SaleController extends Controller
{
    /** Formato único de respuesta para tabla, modal y formulario de solicitudes. */
    private function present(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'code' => $sale->code,
            'holder' => $sale->holder,
            'stage' => $sale->stage,
            'mz_lote' => $sale->mz_lote,
            'state' => $sale->state,
            'id_cliente' => $sale->id_cliente,
            'project_id' => $sale->project_id,
            // Textos usados por la tabla de "Detalle Cliente"
            'cliente' => $sale->client?->Razon_Social,
            'proyecto' => $sale->project?->descripcion,
            // Objetos usados por los formularios
            'client' => $sale->client ? [
                'id' => $sale->client->id_cliente,
                'names' => $sale->client->Razon_Social,
                'dni' => $sale->client->DNI,
            ] : null,
            'project' => $sale->project ? [
                'id_proyecto' => $sale->project->id_proyecto,
                'descripcion' => $sale->project->descripcion,
            ] : null,
        ];
    }

    private function rules(): array
    {
        return [
            'id_cliente' => 'required|integer|exists:clientes,id_cliente',
            'project_id' => 'required|integer|exists:proyecto,id_proyecto',
            'mz_lote' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'holder' => 'nullable|string|max:255',
            'stage' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
        ];
    }

    private function paginated()
    {
        return Sale::with(['client', 'project'])
            ->orderByDesc('id')
            ->paginate(10)
            ->through(fn (Sale $sale) => $this->present($sale));
    }

    public function index(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(
                Sale::with(['client', 'project'])->orderByDesc('id')->get()
                    ->map(fn (Sale $sale) => $this->present($sale))
            );
        }

        return Inertia::render('sales/index', [
            'sales' => $this->paginated(),
            'projects' => Project::where('habilitado', true)
                ->orderBy('descripcion')
                ->get(['id_proyecto', 'descripcion']),
        ]);
    }

    public function fetchPaginated()
    {
        return response()->json($this->paginated());
    }

    public function store(Request $request)
    {
        $sale = Sale::create($request->validate($this->rules()));

        return response()->json([
            'message' => '✅ Lote registrado correctamente',
            'sale' => $this->present($sale->load(['client', 'project'])),
        ]);
    }

    public function update(Request $request, $id)
    {
        $sale = Sale::findOrFail($id);
        $sale->update($request->validate($this->rules()));

        return response()->json([
            'message' => '✅ Lote actualizado',
            'sale' => $this->present($sale->load(['client', 'project'])),
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'sale' => $this->present(Sale::with(['client', 'project'])->findOrFail($id)),
        ]);
    }

    public function destroy($id)
    {
        Sale::findOrFail($id)->delete();

        return response()->json(['message' => '✅ Deleted successfully']);
    }

    public function bulkDelete(Request $request)
    {
        Sale::whereIn('id', $request->input('ids', []))->delete();

        return response()->json(['message' => '✅ Deleted']);
    }
}
