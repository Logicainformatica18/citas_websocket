<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Mail\PaymentNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class PaymentsController extends Controller
{
    /**
     * Lista todos los pagos.
     */
    public function index()
    {
        $payments = Payment::latest()->paginate(20);
        $projects = Project::select('id_proyecto', 'descripcion')->get();

        return Inertia::render('payment/PaymentsIndex', [
            'projects' => $projects,
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
        'file_1'         => 'required|file|max:5120', // voucher original
    ]);

    // 📂 Subir archivo a public/uploads/payments
    $validated['file_1'] = fileStore($request->file('file_1'), 'uploads/payments', 'file1');

    // Ruta absoluta para OCR
    $fullPath = public_path("uploads/payments/".$validated['file_1']);

    if (!file_exists($fullPath)) {
        \Log::error("❌ Archivo no encontrado en ruta: {$fullPath}");
        return back()->withErrors(['file_1' => 'No se pudo acceder al archivo para OCR.']);
    }

    \Log::info("📂 Procesando OCR en archivo: {$fullPath}");

    // 📡 Llamar a API OCR
    $ocrResponse = Http::attach(
        'file',
        file_get_contents($fullPath),
        basename($fullPath)
    )->post('http://127.0.0.1:8000/ocr/image');

    $ocrText = $ocrResponse->successful() ? $ocrResponse->json('text') : null;

    if (!$ocrText) {
        // ❌ Si falla OCR, borrar archivo y no guardar registro
        fileDestroy($validated['file_1'], 'uploads/payments');
        return back()->withErrors(['file_1' => 'Error al procesar el OCR del voucher.']);
    }

    // 🔍 Validar código de operación contra OCR
    if (!empty($validated['receipt_number']) && !str_contains($ocrText, $validated['receipt_number'])) {
        // ❌ Si no coincide, borrar archivo
        fileDestroy($validated['file_1'], 'uploads/payments');
        return back()->withErrors([
            'receipt_number' => 'El código ingresado no coincide con el voucher (OCR).'
        ]);
    }

    // Guardar OCR en file_3
    $validated['file_3'] = $ocrText;

    // Crear registro
    $payment = Payment::create($validated);

    // 🔔 Notificación (no bloquea respuesta)
    dispatch(function () use ($payment) {
        Mail::to($payment->email)->send(new PaymentNotificationMail($payment));
    })->afterCommit()->afterResponse();

    return redirect("pagos")->with('success', 'Pago registrado correctamente.');
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
        ]);

        if ($request->hasFile('file_1')) {
            $validated['file_1'] = fileUpdate($request->file('file_1'), 'uploads/payments', $payment->file_1);

            // 📡 Reprocesar OCR si cambia file_1
            $filePath = $request->file('file_1')->getRealPath();
            $ocrResponse = Http::attach(
                'file', file_get_contents($filePath), $request->file('file_1')->getClientOriginalName()
            )->post('http://127.0.0.1:8000/ocr/image');

            $ocrText = $ocrResponse->successful() ? $ocrResponse->json('text') : null;

            if ($ocrText) {
                $validated['file_3'] = $ocrText;
            }
        }

        if ($request->hasFile('file_2')) {
            $validated['file_2'] = fileUpdate($request->file('file_2'), 'uploads/payments', $payment->file_2);
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

        if ($payment->file_1) {
            fileDestroy($payment->file_1, 'uploads/payments');
        }
        if ($payment->file_2) {
            fileDestroy($payment->file_2, 'uploads/payments');
        }

        $payment->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }
}
