<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Listar solo pagos validados, con paginación
     */
 public function index(Request $request)
{
    $perPage = $request->get('per_page', 20);

    $payments = Payment::with('project')
        ->where('state', 'validado')
        ->latest('date')
        ->paginate($perPage);

    // Mapear solo los campos necesarios
    $payments->getCollection()->transform(function ($payment) {
        return [
            'id' => $payment->id,
            'dni' => $payment->dni,
            'date' => $payment->date,
            'operation_number' => $payment->operation_number,
            'amount' => $payment->amount,
            'state_slim' => $payment->state_slim,
            'file_1_url' => $payment->file_1
                ? url('uploads/payments/' . $payment->file_1)
                : null,
            'project' => $payment->project?->descripcion,
        ];
    });

    return response()->json($payments);
}


    /**
     * Mostrar un pago validado
     */
public function show(Payment $payment)
{
    if ($payment->state !== 'validado') {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    return response()->json([
        'id'               => $payment->id,
        'dni'              => $payment->dni,
        'date'             => $payment->date,
        'operation_number' => $payment->operation_number,
        'amount'           => $payment->amount,
        'state_slim'       => $payment->state_slim,
        'file_1_url'       => $payment->file_1
                                ? url('uploads/payments/' . $payment->file_1)
                                : null,
        'project'          => $payment->project?->descripcion,
    ]);
}


    /**
     * Cambiar el estado Slim de un pago
     */
public function updateStateSlim(Request $request, Payment $payment)
{
    $request->validate([
        'state_slim' => 'required|in:aprobado,observado', // solo acepta estos estados
    ]);

    $payment->state_slim = $request->state_slim;
    $payment->save();

    return response()->json([
        'ok'      => true,
        'message' => "Estado actualizado correctamente",
        'payment' => [
            'id'               => $payment->id,
            'dni'              => $payment->dni,
            'date'             => $payment->date,
            'operation_number' => $payment->operation_number,
            'amount'           => $payment->amount,
            'state_slim'       => $payment->state_slim,
            'file_1_url'       => $payment->file_1
                                    ? url('uploads/payments/' . $payment->file_1)
                                    : null,
            'project'          => $payment->project?->descripcion,
        ],
    ]);
}


}
