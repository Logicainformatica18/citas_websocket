<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductsExport;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::latest()->orderBy('id', 'desc')->paginate(7);
        return Inertia::render('products/index', [
            'products' => $products,
        ]);
    }

    public function fetchPaginated()
    {
        return response()->json(Product::latest()->orderBy('id', 'desc')->paginate(7));
    }

    public function store(Request $request)
    {
        $this->validateProduct($request);

        $product = new Product();
        $product->fill($request->except('file_1'));

        if ($request->hasFile('file_1')) {
            $product->file_1 = fileStore($request->file('file_1'), 'uploads');
        }

        $product->save();

        return response()->json([
            'message' => '✅ Producto creado correctamente',
            'product' => $product,
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $this->validateProduct($request, $id);

        $product->fill($request->except('file_1'));

        if ($request->hasFile('file_1')) {
            $product->file_1 = fileUpdate($request->file('file_1'), 'uploads', $product->file_1);
        }

        $product->save();

        return response()->json([
            'message' => '✅ Producto actualizado correctamente',
            'product' => $product,
        ]);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json(['product' => $product]);
    }

    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Product::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Eliminados correctamente']);
    }

    public function exportExcel($id)
    {
        return Excel::download(new ProductsExport($id), "productos_transferencia_{$id}.xlsx");
    }

    public function searchByDescription(Request $request)
    {
        $term = $request->input('q');

        $products = Product::where('description', 'like', '%' . $term . '%')
            ->limit(10)
            ->get(['id', 'description']);

        return response()->json($products);
    }

    private function validateProduct(Request $request, $id = null)
    {
        $request->validate([
            'code' => "required|string|max:50|unique:products,code,{$id}",
            'sku' => "required|string|max:50|unique:products,sku,{$id}",
            'description' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:50',
            'state' => 'nullable|string|max:50',
            'quantity' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:100',
            'file_1' => 'nullable|file|max:2048',
        ]);
    }
}
