<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use Inertia\Inertia;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    /**
     * Lista todos los pagos.
     */
public function index()
{
    $payments = Payment::latest()->paginate(20);
    $projects = Project::select('id_proyecto', 'descripcion')->get();

    return Inertia::render('payment/index', [
        'projects' => $projects,
        // si no usas tabla en esta vista puedes omitir payments
        'payments' => $payments,
    ]);
}

    /**
     * Guarda un nuevo pago.
     */
public function store(Request $request)
{
    $validated = $request->validate([
        'email'          => 'required|email|max:150',
        'dni'            => 'required|string|max:20',
        'full_name'      => 'required|string|max:200',
        'receipt_number' => 'nullable|string|max:100',
        'amount'         => 'required|numeric|min:0',
        'details'        => 'nullable|string',
        'project_id'     => 'nullable|integer',
        'mz_lote'        => 'nullable|string|max:50',
        'file_1'         => 'nullable|file|max:5120',
        'file_2'         => 'nullable|file|max:5120',
        'file_3'         => 'nullable|file|max:5120',
    ]);

    if ($request->hasFile('file_1')) {
        $validated['file_1'] = fileStore($request->file('file_1'), 'uploads/payments', 'file1');
    }
    if ($request->hasFile('file_2')) {
        $validated['file_2'] = fileStore($request->file('file_2'), 'uploads/payments', 'file2');
    }
    if ($request->hasFile('file_3')) {
        $validated['file_3'] = fileStore($request->file('file_3'), 'uploads/payments', 'file3');
    }

    $payment = \App\Models\Payment::create($validated);

    // Aquí podrías disparar tu notificación por email
    // Mail::to($payment->email)->send(new PaymentNotificationMail($payment));

    return redirect("pagos");
        
}



    /**
     * Muestra un pago específico.
     */
    public function show($id)
    {
        $payment = Payment::findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $payment
        ]);
    }

    /**
     * Actualiza un pago.
     */
    public function update(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'email'          => 'required|email|max:150',
            'dni'            => 'required|string|max:20',
            'full_name'      => 'required|string|max:200',
            'receipt_number' => 'nullable|string|max:100',
            'amount'         => 'required|numeric|min:0',
            'details'        => 'nullable|string',
            'project_id'     => 'nullable|integer',
            'mz_lote'        => 'nullable|string|max:50',
            'file_1'         => 'nullable|file|max:5120',
            'file_2'         => 'nullable|file|max:5120',
            'file_3'         => 'nullable|file|max:5120',
        ]);

        // Actualizar archivos si hay nuevos
        if ($request->hasFile('file_1')) {
            $validated['file_1'] = fileUpdate($request->file('file_1'), 'uploads/payments', $payment->file_1);
        }
        if ($request->hasFile('file_2')) {
            $validated['file_2'] = fileUpdate($request->file('file_2'), 'uploads/payments', $payment->file_2);
        }
        if ($request->hasFile('file_3')) {
            $validated['file_3'] = fileUpdate($request->file('file_3'), 'uploads/payments', $payment->file_3);
        }

        $payment->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Payment updated successfully',
            'data' => $payment
        ]);
    }

    /**
     * Elimina un pago.
     */
    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);

        // Borrar archivos si existen
        if ($payment->file_1) {
            fileDestroy($payment->file_1, 'uploads/payments');
        }
        if ($payment->file_2) {
            fileDestroy($payment->file_2, 'uploads/payments');
        }
        if ($payment->file_3) {
            fileDestroy($payment->file_3, 'uploads/payments');
        }

        $payment->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }
}
