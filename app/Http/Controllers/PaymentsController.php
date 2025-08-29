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
        'file_1'           => 'required|file|max:5120',
    ]);

    // 📂 Subir archivo
    $validated['file_1'] = fileStore($request->file('file_1'), 'uploads/payments', 'file1');

    // Ruta absoluta
    $fullPath = public_path("uploads/payments/" . $validated['file_1']);
    if (!file_exists($fullPath)) {
        \Log::error("❌ Archivo no encontrado en ruta: {$fullPath}");
        return back()->withErrors(['file_1' => 'No se pudo acceder al archivo para OCR.']);
    }

    \Log::info("📂 Procesando OCR con OpenAI en archivo: {$fullPath}");

    // 📡 Llamada a OpenAI Vision API
    $ocrText = null;
    try {
        // Generar URL pública para la imagen
        $imageUrl = asset("uploads/payments/" . $validated['file_1']);

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                "model" => "gpt-4o-mini", // o "gpt-4.1" si lo tienes habilitado
                "messages" => [
                    [
                        "role" => "system",
                        "content" => "Eres un OCR extractor. Devuelve únicamente el texto encontrado en la imagen, en texto plano.
                                      Si detectas una fecha, inclúyela explícitamente en el formato 'fecha: DD/MM/YYYY'.
                                      No devuelvas explicaciones, solo texto plano."
                    ],
                    [
                        "role" => "user",
                        "content" => [
                            [
                                "type" => "image_url",
                                "image_url" => [
                                    "url" => $imageUrl
                                ]
                            ]
                        ]
                    ]
                ],
                "max_tokens" => 500
            ]);

        if ($response->successful()) {
            $ocrText = $response->json('choices.0.message.content') ?? null;
        } else {
            \Log::error("❌ Error en OpenAI OCR: " . $response->body());
        }
    } catch (\Throwable $e) {
        \Log::error("❌ Excepción al llamar OpenAI OCR: " . $e->getMessage());
    }

    // Guardar OCR en file_3
    if ($ocrText) {
        $validated['file_3'] = $ocrText;
    }

    // 🧠 Determinar identificador
    $idToMatch = $validated['operation_number']
        ?? $validated['receipt_number']
        ?? $validated['transaction_code']
        ?? null;

    // 🟢 Calcula state
    $validated['state'] = $this->computeState($ocrText, $idToMatch);

    // ✅ Crear registro
    $payment = Payment::create($validated);

    // 🔔 Notificación
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
