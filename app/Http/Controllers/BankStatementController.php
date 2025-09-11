<?php

namespace App\Http\Controllers;

use App\Models\BankStatement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankStatementController extends Controller
{
    public function index(Request $request)
    {
        $query = BankStatement::query();

        // Filtro por número de operación
        if ($request->filled('operation_number')) {
            $query->where('operation_number', 'LIKE', '%' . $request->operation_number . '%');
        }

        $statements = $query->orderBy('id', 'desc')->paginate(15);

        if ($request->wantsJson()) {
            return response()->json([
                'statements' => $statements,
            ]);
        }

        return Inertia::render('bank_statements/index', [
            'statements' => $statements,
            'filters' => $request->only('operation_number'),
        ]);
    }

    public function show($id)
    {
        $statement = BankStatement::findOrFail($id);

        return response()->json([
            'statement' => $statement,
        ]);
    }
}
