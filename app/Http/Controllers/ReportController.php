<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Support;
class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
  public function show($id)
{
    $support = Support::with([
        'area',
        'creator',
        'client',
        'motivoCita',
        'tipoCita',
        'diaEspera',
        'internalState',
        'externalState',
        'supportType',
        'project'
    ])->findOrFail($id);

    // transformar cliente a formato frontend
    $supportArray = $support->toArray();
    $supportArray['client'] = $support->client ? $support->client->toFrontend() : null;

    return Inertia::render('reports/Show', [
        'support' => $supportArray
    ]);
}
}
