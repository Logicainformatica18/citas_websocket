<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\JpegEncoder;
use Inertia\Inertia;
use App\Jobs\ProcessTransactionBlockJob;

class TransactionController extends Controller
{
    /**
     * Listar transacciones con bloques y líneas (tabla Inertia o JSON).
     */
    public function index(Request $request)
    {
        $transactions = Transaction::with(['blocks', 'lines'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($transactions);
        }

        return Inertia::render('transactions/TransactionsTable', [
            'transactions' => $transactions,
        ]);
    }

    /**
     * Subir imagen completa, dividirla y enviar bloques al Job.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ]);

        // Crear transacción vacía
        $transaction = Transaction::create([
            'status' => 'pending',
        ]);

        // Guardar imagen original
        $pathOriginal = $request->file('image')->store('uploads/transactions/originals', 'public');
        $transaction->file_1 = $pathOriginal;
        $transaction->save();

        // Procesar con Intervention
        $image = Image::read($request->file('image')->getRealPath());
        $width = $image->width();
        $height = $image->height();
        $blockHeight = intval($height / 3); // dividir en 3 partes

        $blocks = [];
        for ($i = 0; $i < 3; $i++) {
            $y = $i * $blockHeight;
            $cropHeight = ($i === 2) ? $height - $y : $blockHeight;

            $cropped = $image->crop($width, $cropHeight, 0, $y);

            $blockName = 'uploads/transactions/blocks/' . uniqid("block_{$transaction->id}_{$i}_") . '.jpg';

            // ✅ Encoder correcto para Intervention v3
            Storage::disk('public')->put(
                $blockName,
                $cropped->encode(new JpegEncoder(90))
            );

            $block = TransactionBlock::create([
                'transaction_id' => $transaction->id,
                'file_path'      => $blockName,
                'x'              => 0,
                'y'              => $y,
                'width'          => $width,
                'height'         => $cropHeight,
            ]);

            $blocks[] = $block;

            // Lanzar Job para procesar cada bloque con OpenAI
            ProcessTransactionBlockJob::dispatch($block);
        }

        return response()->json([
            'ok' => true,
            'transaction' => $transaction,
            'blocks' => $blocks,
            'message' => 'Imagen cargada y bloques enviados a análisis',
        ]);
    }

    /**
     * Mostrar una transacción con líneas y bloques.
     */
    public function show($id)
    {
        $transaction = Transaction::with(['blocks', 'lines'])->findOrFail($id);

        return response()->json($transaction);
    }

    /**
     * Eliminar una transacción y sus relaciones.
     */
    public function destroy($id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->file_1) {
            Storage::disk('public')->delete($transaction->file_1);
        }

        foreach ($transaction->blocks as $block) {
            if ($block->file_path) {
                Storage::disk('public')->delete($block->file_path);
            }
        }

        $transaction->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Transaction deleted successfully',
        ]);
    }

    /**
     * Obtener últimas líneas analizadas.
     */
    public function fetchLines()
    {
        return \App\Models\TransactionLine::orderBy('id', 'desc')->limit(50)->get();
    }
}
