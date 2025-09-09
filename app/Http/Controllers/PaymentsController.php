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
        'operation_number' => 'nullable|string|max:100',
        'transaction_code' => 'nullable|string|max:100',
        'amount'           => 'nullable|numeric|min:0',
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
        $imageUrl = asset("uploads/payments/" . $validated['file_1']);

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                "model" => "gpt-4o-mini",
                "messages" => [
                    [
                        "role" => "system",
                        "content" => "Eres un OCR extractor. Devuelve el texto plano del voucher tal cual,
                        y al final agrega en líneas separadas (usa exactamente este formato):

                        fecha: DD/MM/YYYY
                        numero_operacion: XXXX
                        monto: S/. 0.00

                        Reglas:
                        - La fecha SIEMPRE en formato DD/MM/YYYY (ejemplo: 16/02/2022).
                        - El monto SIEMPRE con dos decimales y con prefijo 'S/.'.
                        - Si no detectas algo, deja el valor vacío después de los dos puntos."
                    ],
                    [
                        "role" => "user",
                        "content" => [
                            [
                                "type" => "image_url",
                                "image_url" => ["url" => $imageUrl]
                            ]
                        ]
                    ]
                ],
                "max_tokens" => 800
            ]);

        if ($response->successful()) {
            $ocrText = $response->json('choices.0.message.content') ?? null;

        } else {
            \Log::error("❌ Error en OpenAI OCR: " . $response->body());
        }
    } catch (\Throwable $e) {
        \Log::error("❌ Excepción al llamar OpenAI OCR: " . $e->getMessage());
    }

    // Guardar OCR completo en file_3
    if ($ocrText) {
        $validated['file_3'] = $ocrText;

        // --- Extraer campos de las líneas finales ---
        if (preg_match('/fecha:\s*(\d{2}\/\d{2}\/\d{4})/i', $ocrText, $m)) {
            // IA ya lo devuelve en DD/MM/YYYY → lo pasamos a Y-m-d para la BD
            $validated['date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $m[1])->format('Y-m-d');
        }

        if (preg_match('/numero_operacion:\s*([0-9]+)/i', $ocrText, $m)) {
            $validated['operation_number'] = $m[1];
        }

        if (preg_match('/monto:\s*S\/\.\s*([0-9]+(?:\.[0-9]{2}))/i', $ocrText, $m)) {
            $validated['amount'] = floatval($m[1]);
        }
    }

    // 🧠 Determinar identificador
    $idToMatch = $validated['operation_number']
        ?? $validated['transaction_code']
        ?? null;

    // 🟢 Calcula state
    $validated['state'] = $this->computeState($validated['file_3'] ?? null, $idToMatch);

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


protected function recognizeVoucher(string $imageUrl): array
{
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
        $res = Http::withToken(env('OPENAI_API_KEY'))
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
                // Si tu cuenta soporta response_format JSON estricto en chat, puedes habilitar:
                // "response_format" => ["type" => "json_object"],
            ]);

        if ($res->successful()) {
            $raw = $res->json('choices.0.message.content') ?? '{}';
            $data = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
            \Log::warning("⚠️ JSON inválido desde OpenAI", ['raw' => $raw]);
        } else {
            \Log::error("❌ OpenAI classify error", ['body' => $res->body()]);
        }
    } catch (\Throwable $e) {
        \Log::error("❌ Excepción en recognizeVoucher", ['e' => $e->getMessage()]);
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
