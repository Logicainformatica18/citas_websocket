<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_slin' => 'required|string',
            'Codigo' => 'required|string',
            'Razon_Social' => 'required|string',
            'T.Doc.' => 'required|string',
            'DNI' => 'required|string',
            'NIT' => 'required|string',
            'Direccion' => 'required|string',
            'Ubigeo' => 'required|string',
            'Departamento' => 'required|string',
            'Provincia' => 'required|string',
            'Distrito' => 'required|string',
            'Telefono' => 'required|string',
            'Email' => 'required|email',
            'clave' => 'required|string',
            'c_clave' => 'required|boolean',
            'ref_telefono1' => 'required|integer',
            'ref_telefono2' => 'required|integer',
            'comentario' => 'nullable|string',
            'canal' => 'nullable|string',
            'habilitado' => 'required|boolean',
        ]);

        $validated['id_rol'] = 2;

        Client::create($validated);

        return redirect()->back()->with('success', 'Cliente creado con éxito');
    }

    public function show($id)
    {
        return response()->json(Client::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'id_slin' => 'required|string',
            'Codigo' => 'required|string',
            'Razon_Social' => 'required|string',
            'T.Doc.' => 'required|string',
            'DNI' => 'required|string',
            'NIT' => 'required|string',
            'Direccion' => 'required|string',
            'Ubigeo' => 'required|string',
            'Departamento' => 'required|string',
            'Provincia' => 'required|string',
            'Distrito' => 'required|string',
            'Telefono' => 'required|string',
            'Email' => 'required|email',
            'clave' => 'required|string',
            'c_clave' => 'required|boolean',
            'ref_telefono1' => 'required|integer',
            'ref_telefono2' => 'required|integer',
            'comentario' => 'nullable|string',
            'canal' => 'nullable|string',
            'habilitado' => 'required|boolean',
        ]);

        $validated['id_rol'] = 2;

        Client::findOrFail($id)->update($validated);

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

        return response()->json(['message' => 'Clientes eliminados en lote']);
    }
public function searchByName(Request $request)
{
    $q = $request->input('q');

    return Client::where('DNI', 'like', "%$q%")
        ->orWhere('Razon_Social', 'like', "%$q%")
        ->limit(10)
        ->get([
            'id_cliente as id',
            'DNI as dni',
            'Razon_Social as names',
            'Telefono as cellphone',
            'Email as email',
            'Direccion as address'
        ]);
}

}


