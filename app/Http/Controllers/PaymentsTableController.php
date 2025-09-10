<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentsTableController extends Controller
{
    /**
     * Vista Inertia + soporte JSON (paginado).
     * Filtro: q (dni, full_name, email, receipt_number, code_client)
     */
    public function index(Request $request)
    {
        $query = Payment::query()
            ->select([
                'payments.id',
                'payments.email',
                'payments.dni',
                'payments.full_name',
                'payments.receipt_number',
                'payments.amount',
                'payments.project_id',
                'payments.mz_lote',
                'payments.date',
                'payments.code_client',
                'payments.file_1',       // 👈 para link del voucher
                'payments.created_at',
            ])
            ->with(['project:id_proyecto,descripcion']);

        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $like = '%' . strtolower($search) . '%';
                $q->whereRaw('LOWER(payments.dni) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.full_name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.email) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.receipt_number) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.code_client) LIKE ?', [$like]);
            });
        }

        $payments = $query
            ->orderBy('payments.id', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Para fetch/XHR
        if ($request->wantsJson()) {
            return response()->json($payments);
        }

        return Inertia::render('payments/PaymentsTable', [
            'payments' => $payments,
            'filters'  => [
                'q' => $request->get('q', ''),
            ],
        ]);
    }

    /**
     * Endpoint JSON para paginación por XHR.
     * Soporta q y per_page.
     */
    public function fetchPaginated(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(5, min($perPage, 100)); // 5–100
        $search  = trim($request->input('q', ''));

        $query = Payment::query()
            ->select([
                'payments.id',
                'payments.email',
                'payments.dni',
                'payments.full_name',
                'payments.receipt_number',
                'payments.amount',
                'payments.project_id',
                'payments.mz_lote',
                'payments.date',
                'payments.code_client',
                'payments.file_1',       // 👈 para link del voucher
                'payments.created_at',
            ])
            ->with(['project:id_proyecto,descripcion']);

        if ($search !== '') {
            $like = '%' . strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(payments.dni) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.full_name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.email) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.receipt_number) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(payments.code_client) LIKE ?', [$like]);
            });
        }

        $paginator = $query
            ->orderBy('payments.id','desc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($paginator);
    }

    /**
     * Mostrar pantalla de edición (SIN actualizar).
     * - Si es JSON: devuelve el pago + proyectos (para selects)
     * - Si no: renderiza Inertia 'payments/PaymentEdit' (tú decides el formulario; no implementa update).
     */
    public function edit(Request $request, $id)
{
    $payment = Payment::query()
        ->with(['project:id_proyecto,descripcion'])
        ->findOrFail($id);

    $projects = Project::select('id_proyecto', 'descripcion')->get();

    // Si pides JSON (ej: desde viewOCR en tu tabla)
    if ($request->wantsJson()) {
        return response()->json([
            'payment'  => $payment,
            'projects' => $projects,
        ]);
    }

    // Si navegas con window.location.href → Renderiza vista Inertia
    return Inertia::render('payments/PaymentEdit', [
        'payment'  => $payment,
        'projects' => $projects,
    ]);
}


    /**
     * Elimina un pago (y sus archivos si existen).
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

    /**
     * Eliminación masiva: ids: number[]
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['ok' => false, 'message' => 'No hay IDs para eliminar'], 422);
        }

        $payments = Payment::whereIn('id', $ids)->get();

        foreach ($payments as $p) {
            if ($p->file_1) fileDestroy($p->file_1, 'uploads/payments');
            if ($p->file_2) fileDestroy($p->file_2, 'uploads/payments');
        }

        Payment::whereIn('id', $ids)->delete();

        return response()->json(['ok' => true, 'message' => 'Pagos eliminados correctamente']);
    }
}
