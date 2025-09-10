<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Mail\PaymentNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        'operation_number' => 'nullable|string|max:100',
        'transaction_code' => 'nullable|string|max:100',
        'amount'           => 'nullable|numeric|min:0',
        'details'          => 'nullable|string',
        'project_id'       => 'nullable|integer',
        'mz_lote'          => 'nullable|string|max:50',
        'date'             => 'nullable|date',
        'code_client'      => 'nullable|string|max:100',
        'file_1'           => 'required|string|max:255', // 👈 ahora ya no es `file`, sino la RUTA del archivo subido antes
    ]);

    \Log::info("💾 Guardando pago", $validated);

    // 🟢 Estado inicial
    $validated['state'] = 'pendiente';

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

    public function recognize(Request $request)
    {
        $request->validate([
            'file_1' => 'required|file|max:5120', // 5 MB máx
        ]);

        // 1. Guardar archivo en storage/public/uploads/payments
        $path = $request->file('file_1')->store('uploads/payments', 'public');
        $imageUrl = asset("storage/" . $path);

        Log::info("📂 Voucher subido", [
            'path' => $path,
            'url' => $imageUrl,
        ]);

        // 2. Llamar al procesador OpenAI
        $data = $this->recognizeVoucher($imageUrl);

        // 3. Devolver JSON al frontend
        return response()->json($data);
    }
 public function recognizeVoucher(string $imageUrl): array{
    $systemPrompt = <<<SYS
Eres un clasificador y extractor de vouchers peruanos.
Tareas, en este orden y en una sola pasada:
1) Clasificar el voucher en una de las casuísticas definidas (campo type).
2) Extraer campos normalizados.
3) Devolver SOLO JSON que cumpla exactamente el json_schema indicado por el cliente.
Si no hay datos, usa null. Si no estás seguro del tipo, usa el que mejor coincida y baja confidence.

Reglas clave:
- fecha en YYYY-MM-DD (convierte desde “Viernes, 22 Agosto 2025 – 01:29 p.m.”, “30/04/2023 – 05:02 pm”, etc.).
- monto numérico (sin “S/” ni comas).
- moneda como PEN o USD.
- numero_operacion y codigo_transaccion como strings.
- NUNCA devuelvas texto fuera del JSON.
SYS;

    $userPrompt = <<<USR
Clasifica la imagen del voucher y extrae campos.
Casuísticas válidas (type) — deben coincidir EXACTO con estos nombres:

app-pagos de servicio
Señales: “¡Pago de servicio exitoso!”, logo BCP, bloques “Pagado a”, “Desde”, “Número de operación”.
Formato: S/ con separador de miles, fecha con día/hora.

agente bcp-pagos de servicio
Señales: encabezado “AGENTE BCP”, establecimiento (BOTICA/BODEGA), dirección/RUC, “NÚM. OPERACIÓN”.
Formato: papel térmico, monoespaciado.

link de niubis
Señales: “Constancia de pago”, “Pago Aprobado”, bloque “Pago”, etiquetas como “DNI / Pasaporte”, “Transacción”, “Monto de la transacción”, correo.
Formato: constancia digital de pasarela Niubiz/niubis (link).

pago presencial
Señales: encabezado “niubiz:”, “VISA/BOLETA DE VENTA”, TERM/LOT, monto centrado (S/ xx.xx).
Formato: POS físico (comprobante impreso).

app interbancario
Señales: logo y UI de banco (p.ej., Scotiabank), “Transferencia a otro banco”, número de orden, “Cuenta de origen/destino”, CCI destino, titular destino.
Formato: app/portal bancario (interbancario).

presencial banco
Señales: “SERVICIO DE RECAUDACIÓN BCP”, “Cuenta a abonar” (Cuenta Recaudo Soles), “Depósito N°”, fecha/hora, monto.
Formato: boleta de ventanilla bancaria BCP.

transferencia interbancaria
Señales: fondo verde “Operación exitosa”, comisión/ITF, logos de bancos, “Tipo de operación: Transferencia interbancaria”, CCI destino, número de operación largo, beneficiario.
Formato: constancia digital de transferencia interbancaria.

pago directo a bcp
Señales: logo BCP + “¡Pago de servicio exitoso!”, Titular visible, “Pagado a” con código (p.ej. PH4LWC4), “Desde”, “Número de operación”.

yape app
Señales: fondo morado, logo Yape, “¡Yapeaste el servicio!”, servicio, código de cliente, titular, número de operación.

Si ninguna coincide razonablemente, devolver type: "desconocido" y confidence < 0.5.

Campos a extraer (normalizados)
- type (una de las casuísticas anteriores)
- confidence (0..1)
- fecha (YYYY-MM-DD)
- hora (HH:MM, 24h, opcional si existe)
- monto (number)
- moneda (PEN/USD)
- numero_operacion (string o null)
- codigo_transaccion (string o null; en pasarelas puede venir como GUID/alfanumérico)
- titular (pagador; string o null)
- pagado_a (beneficiario/empresa; string o null)
- servicio (p.ej., Pagos Varios; string o null)
- codigo_cliente (string o null)
- banco_origen (string o null)
- cuenta_origen (string o null; puede ser enmascarado ****1095)
- banco_destino (string o null)
- cuenta_destino (string o null)
- cci_destino (string o null)
- glosa (string o null)

Devuelve SOLO un objeto JSON con esas claves.
USR;

try {
    Log::info("📤 Enviando request a OpenAI recognizeVoucher", [
        'imageUrl' => $imageUrl,
    ]);

    $res = Http::withToken(config('services.openai.key'))
        ->timeout(90)
        ->post('https://api.openai.com/v1/chat/completions', [
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "text", "text" => $userPrompt],
                        ["type" => "image_url", "image_url" => ["url" => $imageUrl]],
                    ]
                ],
            ],
            "max_tokens" => 900,
        ]);

    if ($res->successful()) {
        $raw = $res->json('choices.0.message.content') ?? '{}';
        Log::info("📥 Respuesta cruda OpenAI", ['raw' => $raw]);

        // 🚀 Limpiar bloque de Markdown si lo devuelve
        $clean = trim($raw);
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```[a-zA-Z]*\n?/', '', $clean); // quita ```json o ```
            $clean = preg_replace('/```$/', '', $clean); // quita cierre ```
        }

        $data = json_decode($clean, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            Log::info("✅ JSON válido parseado", ['data' => $data]);
            return $data;
        }

        Log::warning("⚠️ JSON inválido desde OpenAI", [
            'raw'   => $raw,
            'clean' => $clean,
            'error' => json_last_error_msg(),
        ]);
    }
} catch (\Throwable $e) {
    Log::error("❌ Excepción en recognizeVoucher", [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
}


        // Fallback seguro
        return [
            "type" => "desconocido",
            "confidence" => 0,
            "fecha" => null,
            "hora" => null,
            "monto" => null,
            "moneda" => null,
            "numero_operacion" => null,
            "codigo_transaccion" => null,
            "titular" => null,
            "pagado_a" => null,
            "servicio" => null,
            "codigo_cliente" => null,
            "banco_origen" => null,
            "cuenta_origen" => null,
            "banco_destino" => null,
            "cuenta_destino" => null,
            "cci_destino" => null,
            "glosa" => null,
        ];
    }


}
