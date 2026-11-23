<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::paginate(10);

        return Inertia::render('clients/index', [
            'clients' => $clients,
        ]);
    }

    public function fetchPaginated()
    {
        return response()->json(
            Client::paginate(10)
        );
    }

    /**
     * Reglas comunes de store/update.
     *
     * - Obligatorios: los campos que envía el formulario de clientes
     *   (resources/js/pages/clients/modal.tsx). Antes se exigían 20 campos que
     *   el formulario no envía, así que crear/editar siempre devolvía 422.
     * - "T.Doc.": Laravel interpreta los puntos como claves anidadas, por lo
     *   que la regla 'T.Doc.' nunca se cumplía, y Eloquent tampoco puede
     *   escribir esa columna. Se recibe como "tipo_documento" (opcional) y se
     *   guarda con SQL crudo en saveTipoDocumento().
     */
    private function rules(): array
    {
        return [
            'Codigo' => 'required|string|max:50',
            'Razon_Social' => 'required|string|max:255',
            'DNI' => 'required|string|max:20',
            'Direccion' => 'required|string|max:255',
            'Telefono' => 'required|string|max:20',
            'Email' => 'required|email|max:150',
            'habilitado' => 'required|boolean',
            'canal' => 'nullable|string|max:50',
            'comentario' => 'nullable|string',

            'tipo_documento' => 'nullable|string|max:20',
            'id_slin' => 'nullable|string|max:50',
            'NIT' => 'nullable|string|max:20',
            'Ubigeo' => 'nullable|string|max:10',
            'Departamento' => 'nullable|string|max:100',
            'Provincia' => 'nullable|string|max:100',
            'Distrito' => 'nullable|string|max:100',
            'clave' => 'nullable|string|max:255',
            'c_clave' => 'nullable|boolean',
            'ref_telefono1' => 'nullable|integer',
            'ref_telefono2' => 'nullable|integer',
        ];
    }

    /** Escribe la columna heredada "T.Doc." (no se puede vía Eloquent). */
    private function saveTipoDocumento(Client $client, ?string $value): void
    {
        if ($value === null) {
            return;
        }
        $col = DB::getDriverName() === 'mysql' ? '`T.Doc.`' : '"T.Doc."';
        DB::update("UPDATE clientes SET {$col} = ? WHERE id_cliente = ?", [$value, $client->id_cliente]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $tipoDocumento = Arr::pull($validated, 'tipo_documento');

        $validated['id_rol'] = 2;

        $client = Client::create($validated);
        $this->saveTipoDocumento($client, $tipoDocumento);

        return redirect()->back()->with('success', 'Cliente creado con éxito');
    }

    public function show($id)
    {
        return response()->json(Client::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate($this->rules());
        $tipoDocumento = Arr::pull($validated, 'tipo_documento');

        $validated['id_rol'] = 2;

        $client = Client::findOrFail($id);
        $client->update($validated);
        $this->saveTipoDocumento($client, $tipoDocumento);

        return redirect()->back()->with('success', 'Cliente actualizado con éxito');
    }

    public function destroy($id)
    {
        Client::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Cliente eliminado');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Client::whereIn('id_cliente', $ids)->delete();

        return response()->json(['message' => 'Clientes eliminados en Lote']);
    }
public function searchByName(Request $request)
{
    $q = trim($request->input('q'));

    $clients = Client::query()
        ->with(['sales' => function ($query) {
            $query->select('id', 'id_cliente', 'project_id', 'mz_lote')
                  ->with(['project:id_proyecto,descripcion']);
        }])
        ->where('DNI', $q)
        ->orWhereRaw('LOWER(Razon_Social) LIKE ?', ["%" . strtolower($q) . "%"])
        ->limit(10)
        ->get() // 🔁 sin alias aquí
        ->map(function ($client) {
            return [
                'id' => $client->id_cliente,
                'dni' => $client->DNI,
                'names' => $client->Razon_Social,
                'cellphone' => $client->Telefono,
                'email' => $client->Email,
                'address' => $client->Direccion,
                'sales' => $client->sales, // ventas ya con proyecto
            ];
        });

    return response()->json($clients);
}




}


