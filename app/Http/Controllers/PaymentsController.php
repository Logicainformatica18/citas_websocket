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
            'operation_number' => 'nullable|string|max:100', // <- opcional si ya lo usas
            'transaction_code' => 'nullable|string|max:100', // <- opcional si ya lo usas
            'amount'         => 'required|numeric|min:0',
            'details'        => 'nullable|string',
            'project_id'     => 'nullable|integer',
            'mz_lote'        => 'nullable|string|max:50',
            'date'           => 'nullable|date',
            'code_client'    => 'nullable|string|max:100',
            'file_1'         => 'required|file|max:5120', // voucher original
        ]);

        // 📂 Subir archivo a public/uploads/payments
        $validated['file_1'] = fileStore($request->file('file_1'), 'uploads/payments', 'file1');

        // Ruta absoluta para OCR
        $fullPath = public_path("uploads/payments/" . $validated['file_1']);

        if (!file_exists($fullPath)) {
            \Log::error("❌ Archivo no encontrado en ruta: {$fullPath}");
            return back()->withErrors(['file_1' => 'No se pudo acceder al archivo para OCR.']);
        }

        \Log::info("📂 Procesando OCR en archivo: {$fullPath}");

        // 📡 Llamar a API OCR (imagen por defecto; si subes PDF cambia a /ocr/pdf)
        $endpoint = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'pdf'
            ? 'http://127.0.0.1:8000/ocr/pdf'
            : 'http://127.0.0.1:8000/ocr/image';

        $ocrResponse = Http::attach(
            'file',
            file_get_contents($fullPath),
            basename($fullPath)
        )->post($endpoint);

        $ocrText = $ocrResponse->successful() ? ($ocrResponse->json('text') ?? null) : null;

        // Guardar OCR en file_3 (si existe)
        if ($ocrText) {
            $validated['file_3'] = $ocrText;
        }

        // 🧠 Determinar el identificador a validar (prioridad: operation_number > receipt_number > transaction_code)
        $idToMatch = $validated['operation_number']
            ?? $validated['receipt_number']
            ?? $validated['transaction_code']
            ?? null;

        // 🟢 Calcula el state según OCR vs identificador
        $validated['state'] = $this->computeState($ocrText, $idToMatch);

        // ✅ Crear registro (state se calcula en backend)
        $payment = Payment::create($validated);

        // 🔔 Notificación en cola
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
            'email'            => 'required|email|max:150',
            'dni'              => 'required|string|max:20',
            'full_name'        => 'required|string|max:200',
            'receipt_number'   => 'nullable|string|max:100',
            'operation_number' => 'nullable|string|max:100',
            'transaction_code' => 'nullable|string|max:100',
            'amount'           => 'required|numeric|min:0',
            'details'          => 'nullable|string',
            'project_id'       => 'nullable|integer',
            'mz_lote'          => 'nullable|string|max:50',
            'date'             => 'nullable|date',
            'code_client'      => 'nullable|string|max:100',
            'file_1'           => 'nullable|file|max:5120',
            'file_2'           => 'nullable|file|max:5120',
        ]);

        $newOcrText = null;

        if ($request->hasFile('file_1')) {
            $validated['file_1'] = fileUpdate($request->file('file_1'), 'uploads/payments', $payment->file_1);

            // 📡 Reprocesar OCR
            $filePath = $request->file('file_1')->getRealPath();
            $endpoint = strtolower($request->file('file_1')->getClientOriginalExtension()) === 'pdf'
                ? 'http://127.0.0.1:8000/ocr/pdf'
                : 'http://127.0.0.1:8000/ocr/image';

            $ocrResponse = Http::attach(
                'file', file_get_contents($filePath), $request->file('file_1')->getClientOriginalName()
            )->post($endpoint);

            $newOcrText = $ocrResponse->successful() ? ($ocrResponse->json('text') ?? null) : null;

            if ($newOcrText) {
                $validated['file_3'] = $newOcrText;
            }
        }

        if ($request->hasFile('file_2')) {
            $validated['file_2'] = fileUpdate($request->file('file_2'), 'uploads/payments', $payment->file_2);
        }

        // 🧠 ¿Recalcular state?
        // Si cambió el voucher (nuevo OCR), o cambió el identificador, recalculamos.
        $idToMatch = $validated['operation_number']
            ?? $validated['receipt_number']
            ?? $validated['transaction_code']
            ?? $payment->operation_number
            ?? $payment->receipt_number
            ?? $payment->transaction_code
            ?? null;

        $textForValidation = $newOcrText ?? $validated['file_3'] ?? $payment->file_3 ?? null;

        if ($idToMatch !== null || $newOcrText !== null) {
            $validated['state'] = $this->computeState($textForValidation, $idToMatch);
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

        if ($payment->file_1) fileDestroy($payment->file_1, 'uploads/payments');
        if ($payment->file_2) fileDestroy($payment->file_2, 'uploads/payments');

        $payment->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }

    // ==========================
    // Helpers privados
    // ==========================

    /**
     * Normaliza texto para hacer match robusto (minúsculas, sin espacios/guiones/caracteres no alfanum).
     */
    private function normalize(?string $s): string
    {
        if ($s === null) return '';
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9]+/u', '', $s);
        return $s ?? '';
    }

    /**
     * Calcula el estado según OCR vs identificador.
     * - validado: OCR contiene el identificador
     * - observado: hay identificador pero no match, o falla OCR
     * - registrado: no hay identificador para validar
     */
    private function computeState(?string $ocrText, ?string $idToMatch): string
    {
        if (empty($idToMatch)) {
            return 'registrado';
        }

        if (empty($ocrText)) {
            return 'observado';
        }

        $haystack = $this->normalize($ocrText);
        $needle   = $this->normalize($idToMatch);

        if ($needle === '') {
            return 'registrado';
        }

        // Match directo
        if (str_contains($haystack, $needle)) {
            return 'validado';
        }

        // Variante: O->0, I/L->1 por confusiones típicas de OCR
        $needleAlt = strtr($needle, ['o' => '0', 'i' => '1', 'l' => '1']);
        if ($needleAlt !== $needle && str_contains($haystack, $needleAlt)) {
            return 'validado';
        }

        return 'observado';
    }
}
